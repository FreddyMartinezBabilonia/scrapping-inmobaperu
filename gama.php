<?php
    use Symfony\Component\BrowserKit\HttpBrowser;
    use Symfony\Component\DomCrawler\Crawler;
    use Symfony\Component\HttpClient\HttpClient;

    date_default_timezone_set('America/Lima');

    header("Content-Type:application/json");

    $time_start = microtime(true);
    $date_start = date("Y-m-d H:i:s");
    echo $date_start . "\n";

    $config = parse_ini_file("/var/www/resources/env-babilonia.sh", true);
    extract($config);

    require '/var/www/resources/php/vendor/autoload.php';

    $global_db_environment = $MASTER_ENVIRONMENT;
    $global_db_type = "mongo";
    $global_db_credential = "app_user";
    require '/var/www/resources/php/babilonia-io/appConnection.php';

    $key_environment = $MASTER_ENVIRONMENT;
    $key_data = "internal";
    require '/var/www/resources/php/babilonia-io/globalCredential.php';

    $currency = $mongo->babilonia->currency;
    $listings = $mongo->babilonia->listings;

    $cursor_currency = $currency->find(
        array(
            '$and' => array(
                array("currency_code" => "PEN"),
                array("currency_type" => "SELL"),
            ),
        ),
        array(
            'limit' => intval(1),
            'sort' => array("created_at" => -1),
        ),
    );

    foreach ($cursor_currency as $currency) {
        $tc = floatval($currency["currency_value"]);
    }

    $user_email = ($MASTER_ENVIRONMENT == "prod" ? strval("GIULIANA.GIANNONI@YAHOO.COM") : strval("julio.lopez@babilonia.io"));
    $path = "https://gamainmobiliaria.pe";
    $path_data_website = __DIR__ . "/scraping_" . date("Ymd") . ".log";

    function get_string_between($string, $start, $end){
        $string = " ".$string;
        $ini = strpos($string, $start);
        if ($ini == 0) return "";
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    $message_login = new stdClass();
    $message_login->email = strval($user_email);
    $message_login->password = strval($MASTER_PASSWORD);
    $message_login->ipa = strval("172.16.0.61");
    $message_login->ua = strval("Apache/2.4.54 (Debian)");
    $message_login->sip = strval("email");
    $message_login = json_encode($message_login);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $URL_SERVICE . "/auth/login",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $message_login,
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "Accept-Language: " . $LANGUAGE_DEFAULT
        ),
    ));
    $answer_login_ok = curl_exec($curl);
    $answer_login_error = curl_error($curl);
    curl_close($curl);

    if ($answer_login_error) {
        header("HTTP/1.1 400 Bad Request");

        $error = array (
            "data" => array (
                "errors" => array (array (
                    "key" => "unknow",
                    "message" => $answer_login_error,
                    "payload" => array (
                        "code" => "unknow",
                    ),
                    "type" => strval("gama"),
                ))
            )
        );

        echo json_encode($error);
    }
    else {
        $answer_login_ok = json_decode($answer_login_ok);

        if (isset($answer_login_ok->data->tokens)) {
            $authorization = strval($answer_login_ok->data->tokens->type . " " . $answer_login_ok->data->tokens->authentication);
            $user_id = intval($answer_login_ok->data->tokens->user_id);
        }
        else {
            header("HTTP/1.1 400 Bad Request");

            $error = array (
                "data" => array (
                    "errors" => array (array (
                        "key" => "unknow",
                        "message" => $answer_login_error,
                        "payload" => array (
                            "code" => "unknow",
                        ),
                        "type" => strval("gama"),
                    ))
                )
            );

            echo json_encode($error);
            exit();
        }
    }

    echo $user_id . " - " . $authorization . "\n\n";

    $client = new HttpBrowser(HttpClient::create(['timeout' => 180]));

    $array_provider = array();
    $array_babilonia = array();
    $answer_error_delete_counter = 0;
    $answer_error_create_counter = 0;
    $answer_error_create_external_listing_id_counter = 0;
    $answer_error_update_counter = 0;

    print_r("
    _
    |_  _ |_ .| _  _ . _     |_|_  _  |_    |_   (_ _  _  |_  _  _  _ _
    |_)(_||_)||(_)| )|(_|,   |_| )(-  | )|_||_)  | (_)|   | )(_)|||(-_)
    \n\n\n\n");

    ##########################################################################################
    #GETTING PROVIDER DATA
    ##########################################################################################
    for ($page = 1; $page <= 30; $page++) {
        if ($page == 1) {
            $listing = $client->request('GET', $path . "/listings/browse/?keyword=&offer=&location=&listing-type=&bedrooms=&bathrooms=&min=&max=&orderby=date&order=desc");
        }
        else {
            $listing = $client->request('GET', $path . "/listings/browse/page/" . $page . "/?keyword&offer&location&listing-type&bedrooms&bathrooms&min&max&orderby=date&order=desc#038;offer&location&listing-type&bedrooms&bathrooms&min&max&orderby=date&order=desc");
        }

        if (
            $listing
                ->filter("body")
                ->filter("div.site-wrapper")
                ->filter("div.site-container")
                ->filter("div.site-main")
                ->filter("div.container")
                ->filter("div.row.gutter-60")
                ->filter("main.content.col-md-12")
                ->filter("article")
                ->filter("div.entry-content")
                ->filter("div.wpsight-listings-sc")
                ->text() == "Lo sentimos, no hay resultados para su búsqueda."
        ) {
            continue;
        }
        else {
            $listing
                ->filter("body")
                ->filter("div.site-wrapper")
                ->filter("div.site-container")
                ->filter("div.site-main")
                ->filter("div.container")
                ->filter("div.row.gutter-60")
                ->filter("main.content.col-md-12")
                ->filter("article")
                ->filter("div.entry-content")
                ->filter("div.wpsight-listings-sc")
                ->filter("div.wpsight-listings")
                ->filter("div.row.gutter-60")
                ->filter("div.listing-wrap.col-sm-4")
                ->filter("a")
                ->each(function($node) use(&$array_provider) {
                    if ($node->attr('href') <> "javascript:void(0)") {
                        $link = $node->attr('href');

                        $file_headers = @get_headers($link);

                        if($file_headers AND strpos($file_headers[0], '200')) {
                            array_push($array_provider, strval($link));
                        }
                    }
                });
        }
    }

    $array_provider = array_unique($array_provider);

    if (count($array_provider) >= 1) {
        ##########################################################################################
        #GETTING BABILONIA DATA
        ##########################################################################################
        $select_listings_babilonia = $listings->find(
            array('$and' => array(
                array('user_id' => intval($user_id)),
                array('external_data.id' => array('$ne' => null)),
                array('source' => strval('integration')),
            ))
        );

        foreach ($select_listings_babilonia as $babilonia) {
            array_push($array_babilonia, strtolower($babilonia["external_data"]["url"]));
        }

        ##########################################################################################
        #DELETING ADS
        ##########################################################################################
        $result_delete = array_diff($array_babilonia, $array_provider);
        $counter_delete = count($result_delete);
        $c_delete = $counter_delete;

        sort($result_delete);

        if(count($result_delete) >= 1) {
            foreach ($result_delete as &$link) {
                echo $link . "\n";

                $delete_babilonia = $listings->find(
                    array('$and' => array(
                        array('user_id' => intval($user_id)),
                        array('external_data.url' => strval($link)),
                    ))
                );

                foreach ($delete_babilonia as $del_babilonia) {
                    $array_listing_id = array(
                        $del_babilonia["id"],
                    );

                    $message_delete_listing = new stdClass();
                    $message_delete_listing->action = strval("delete");
                    $message_delete_listing->source = strval("integration");
                    $message_delete_listing->type = strval("listing");
                    $message_delete_listing->ids = $array_listing_id;
                    $message_delete_listing->reason = strval("scraping");
                    $message_delete_listing = json_encode($message_delete_listing);

                    print_r($message_delete_listing . "\n");

                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $URL_SERVICE . "/me/validation_data",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CUSTOMREQUEST => "DELETE",
                        CURLOPT_POSTFIELDS => $message_delete_listing,
                        CURLOPT_HTTPHEADER => array(
                            "Authorization: " . $authorization,
                            "Content-Type: application/json",
                            "Accept-Language: " . $LANGUAGE_DEFAULT
                        ),
                    ));
                    $answer_delete_ok = curl_exec($curl);
                    $answer_delete_error = curl_error($curl);
                    curl_close($curl);

                    if ($answer_delete_error) {
                        header("HTTP/1.1 400 Bad Request");

                        $error = array (
                            "data" => array (
                                "errors" => array (array (
                                    "key" => "unknow",
                                    "message" => $answer_delete_error,
                                    "payload" => array (
                                        "code" => "unknow",
                                    ),
                                    "type" => strval("contempo"),
                                ))
                            )
                        );

                        echo json_encode($error);
                    }
                    else {
                        $answer_delete_ok = json_decode($answer_delete_ok);

                        if ($answer_delete_ok->data->status) {
                            if ($answer_delete_ok->data->status == "ok") {
                                echo json_encode($answer_delete_ok) . "\n";
                                echo date("Y-m-d H:i:s") . "\n";
                                echo "Remains " . $c_delete-- . " of " . $counter_delete . " delete\n\n";
                            }
                            else {
                                $answer_error_delete_counter++;

                                echo json_encode($answer_delete_ok) . "\n";
                                echo date("Y-m-d H:i:s") . "\n";
                                echo "Remains " . $c_delete-- . " of " . $counter_delete . " delete\n\n";
                            }
                        }
                        else {
                            $answer_error_delete_counter;

                            echo json_encode($answer_delete_ok) . "\n";
                            echo date("Y-m-d H:i:s") . "\n";
                            echo "Remains " . $c_delete-- . " of " . $counter_delete . " delete\n\n";
                        }
                    }
                }
            }
        }

        $current_date = new DateTime("now", new DateTimeZone('+00:00'));

        $select_users_packages = $users_packages->find(
            array(
                '$and' => array(
                    array('user_id' => intval($user_id)),
                    array(
                        'expires_at' => array(
                            '$gte' => new MongoDB\BSON\UTCDateTime($current_date->format('U') * 1000),
                        )
                    ),
                ),
            ),
            array(
                'sort' => array(
                    'purchased_at' => -1
                ),
                'limit' => 1
            ),
            array(
                'id' => intval(1),
            ),
        );

        foreach ($select_users_packages as $user_package) {
            $user_package_id = intval($user_package["id"]);
        }

        if (isset($user_package_id)) {
            ##########################################################################################
            #CREATING ADS
            ##########################################################################################
            $array_external_listing_id_already_exist = array();

            $result_create = array_diff($array_provider, $array_babilonia);
            #$result_create = array("https://gamainmobiliaria.pe/listing/alquilo-departamento-amoblado-y-equipado-3dorm-84mts-calle-colon-537-miraflores/");
            $counter_create = count($result_create);
            $c_create = $counter_create;

            sort($result_create);

            if (count($result_create) >= 1) {
                foreach ($result_create as $link) {
                    echo $link . "\n";

                    $array_images = array();
                    $array_videos = array();
                    $array_360 = array();
                    $array_facilities = array();

                    $listing = $client->request('GET', $link);

                    $entire_website = $listing->html();

                    file_put_contents($path_data_website, $link . " - " . date("Y-m-d\TH:i:s\Z") . "\n" . $entire_website . "\n\n\n", FILE_APPEND | LOCK_EX);

                    $listing
                        ->filter("body")
                        ->filter("div.site-wrapper")
                        ->filter("div.site-container")
                        ->filter("div.site-main-top.listing-top")
                        ->filter("div.container")
                        ->filter("section.widget-section.section-widget_listing_image_slider")
                        ->filter("div.widget.widget_listing_image_slider")
                        ->filter("div.wpsight-listing-section.wpsight-listing-section-slider")
                        ->filter("div.wpsight-listing-slider")
                        ->filter("div")
                        ->filter("div")
                        ->filter("div")
                        ->filter("ul")
                        ->filter("li")
                        ->each(function ($node) use(&$array_images) {
                            $node
                                ->filter("img")
                                ->each(function ($node_img) {
                                    global $array_images;

                                    array_push($array_images, $node_img->attr("src"));
                                });
                        });

                    $property_type = strtolower($listing
                        ->filter("body")
                        ->filter("div.site-wrapper")
                        ->filter("div.site-container")
                        ->filter("div.site-main-top.listing-top")
                        ->filter("div.container")
                        ->filter("section.widget-section.section-widget_listing_title")
                        ->filter("div.widget.widget_listing_title")
                        ->filter("div.wpsight-listing-section.wpsight-listing-section-title")
                        ->filter("header.page-header")
                        ->filter("div.wpsight-listing-title.clearfix")
                        ->filter("h1.entry-title")
                        ->text());

                    if (
                        strtolower($property_type) == "vendo, terreno cercado con oficinas y servicios en av.b, el callao (km. 9.5 nestor gambetta)"
                    ) {
                        $property_type = "land";
                    }
                    else if (
                        strtolower($property_type) == "vendo, excelente casona de 2 pisos para oficina o comercio, en jr. miroquesada, lima"
                    ) {
                        $property_type = "commercial";
                    }
                    else {
                        if (
                            str_contains(strtolower($property_type), "casa")
                            OR strpos(strtolower($property_type), "casas") !== false
                        ) {
                            $property_type = "house";
                        }
                        else if (
                            str_contains(strtolower($property_type), "local comercial")
                            OR str_contains(strtolower($property_type), "local industrial")
                        ) {
                            $property_type = "commercial";
                        }
                        else if (
                            str_contains(strtolower($property_type), "dpto")
                            OR str_contains(strtolower($property_type), "dpto.")
                            OR str_contains(strtolower($property_type), "dpto,")
                            OR str_contains(strtolower($property_type), "dptos")
                            OR str_contains(strtolower($property_type), "dptos.")
                            OR str_contains(strtolower($property_type), "dptos,")
                            OR str_contains(strtolower($property_type), "loft")
                            OR str_contains(strtolower($property_type), "dúplex")
                            OR str_contains(strtolower($property_type), "duplex")
                            OR str_contains(strtolower($property_type), "duplex,")
                            OR str_contains(strtolower($property_type), "d\u00faplex")
                            OR str_contains(strtolower($property_type), "d\u00faplex,")
                            OR str_contains(strtolower($property_type), "departamento")
                            OR str_contains(strtolower($property_type), "flat")
                        ) {
                            $property_type = "apartment";
                        }
                        else if (
                            str_contains(strtolower($property_type), "oficina")
                            OR str_contains(strtolower($property_type), "of.")
                            OR str_contains(strtolower($property_type), "oficina,")
                            OR str_contains(strtolower($property_type), "oficinas")
                        ) {
                            $property_type = "office";
                        }
                        else if (
                            str_contains(strtolower($property_type), "terreno")
                            OR str_contains(strtolower($property_type), "terreno,")
                            OR str_contains(strtolower($property_type), "parcelas")
                        ) {
                            $property_type = "land";
                        }
                        else if (
                            str_contains(strtolower($property_type), "cochera")
                        ) {
                            $property_type = "parking";
                        }
                        else if (
                            str_contains(strtolower($property_type), "edificio")
                        ) {
                            $property_type = "building";
                        }
                        else {
                            unset($property_type);
                        }
                    }

                    if (
                        $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_description")
                            ->filter("div.widget.widget_listing_description")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-description")
                            ->filter("div.wpsight-listing-description")
                            ->count() > 0
                    ) {
                        $description = $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_description")
                            ->filter("div.widget.widget_listing_description")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-description")
                            ->filter("div.wpsight-listing-description")
                            ->html();

                        $description = str_replace("<br>", "", $description);
                    }

                    if (str_contains($description, " https://youtu")) {
                        $video_url = get_string_between($description, 'https://youtu', '<');
                        $video_url = strval("https://youtu" . $video_url);

                        array_push($array_videos, $video_url);
                    }

                    if (
                        $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_price")
                            ->filter("div.widget.widget_listing_price")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-info")
                            ->filter("div.wpsight-listing-info.clearfix")
                            ->filter("div.row.gutter-40")
                            ->filter("div.col-xs-6")
                            ->filter("div.wpsight-listing-price")
                            ->filter("span.listing-price-value")
                            ->count() > 0
                    ) {
                        $price = $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_price")
                            ->filter("div.widget.widget_listing_price")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-info")
                            ->filter("div.wpsight-listing-info.clearfix")
                            ->filter("div.row.gutter-40")
                            ->filter("div.col-xs-6")
                            ->filter("div.wpsight-listing-price")
                            ->filter("span.listing-price-value")
                            ->text();

                        $price = str_replace(array(",", ".",), array("", "",), $price);
                    }

                    $id_listing_external = $listing
                        ->filter("body")
                        ->filter("div.site-wrapper")
                        ->filter("div.site-container")
                        ->filter("div.site-main")
                        ->filter("div.container")
                        ->filter("div.row.gutter-60")
                        ->filter("main.content.col-md-8")
                        ->filter("div")
                        ->filter("div")
                        ->filter("section.widget-section.section-widget_listing_price")
                        ->filter("div.widget.widget_listing_price")
                        ->filter("div.wpsight-listing-section.wpsight-listing-section-info")
                        ->filter("div.wpsight-listing-info.clearfix")
                        ->filter("div.row.gutter-40")
                        ->filter("div.col-xs-6")
                        ->filter("div.wpsight-listing-id")
                        ->text();

                    $listing_type = $listing
                        ->filter("body")
                        ->filter("div.site-wrapper")
                        ->filter("div.site-container")
                        ->filter("div.site-main")
                        ->filter("div.container")
                        ->filter("div.row.gutter-60")
                        ->filter("main.content.col-md-8")
                        ->filter("div")
                        ->filter("div")
                        ->filter("section.widget-section.section-widget_listing_price")
                        ->filter("div.widget.widget_listing_price")
                        ->filter("div.wpsight-listing-section.wpsight-listing-section-info")
                        ->filter("div.wpsight-listing-info.clearfix")
                        ->filter("div.row.gutter-40")
                        ->filter("div.col-xs-6")
                        ->filter("div.wpsight-listing-offer")
                        ->filter("span")
                        ->text();

                    switch (strtolower($listing_type)) {
                        case 'en alquiler':
                            $listing_type = "rent";
                        break;

                        case 'en venta':
                            $listing_type = "sale";
                        break;
                    }

                    $listing
                        ->filter("body")
                        ->filter("div.site-wrapper")
                        ->filter("div.site-container")
                        ->filter("div.site-main")
                        ->filter("div.container")
                        ->filter("div.row.gutter-60")
                        ->filter("main.content.col-md-8")
                        ->filter("div")
                        ->filter("div")
                        ->filter("section.widget-section.section-widget_listing_details")
                        ->filter("div.widget.widget_listing_details")
                        ->filter("div.wpsight-listing-section.wpsight-listing-section-details")
                        ->filter("div.wpsight-listing-details.clearfix")
                        ->filter("span.listing-details-detail")
                        ->each(function ($node) {
                            global $bedrooms_count;
                            global $bathrooms_count;
                            global $area;
                            global $built_area;
                            global $parking_slots_count;
                            global $year_of_construction;

                            $old_string = array("Dormitorios: ",    "Baños: ",  "AT: ",     "AC: ", "Garage: ", "Construido en: ",  "m²");
                            $new_string = array("",                 "",         "",         "",     "",         "",                 "");

                            if (strpos($node->text(), "Dormitorios:") !== false) {
                                $bedrooms_count = str_replace($old_string, $new_string, $node->text());
                            }

                            if (strpos($node->text(), "Baños:") !== false) {
                                $bathrooms_count = str_replace($old_string, $new_string, $node->text());
                            }

                            if (strpos($node->text(), "AT:") !== false) {
                                $area = str_replace($old_string, $new_string, $node->text());
                            }

                            if (strpos($node->text(), "AC:") !== false) {
                                $built_area = str_replace($old_string, $new_string, $node->text());
                            }

                            if (strpos($node->text(), "Garage:") !== false) {
                                $parking_slots_count = str_replace($old_string, $new_string, $node->text());
                            }

                            if (strpos($node->text(), "Construido en:") !== false) {
                                $year_of_construction_prev = explode(",", $node->text());
                                $year_of_construction = str_replace($old_string, $new_string, $year_of_construction_prev[0]);
                            }
                        });

                    if (!isset($area)) {
                        $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_description")
                            ->filter("div.widget.widget_listing_description")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-description")
                            ->filter("div.wpsight-listing-description")
                            ->filter("p")
                            ->each(function ($node) {
                                global $area_prev;

                                $area_prev .= $node->html();
                            });

                        $area = get_string_between($area_prev, 'A.T. : ', ' m2');
                    }

                    if (!isset($built_area)) {
                        $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_description")
                            ->filter("div.widget.widget_listing_description")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-description")
                            ->filter("div.wpsight-listing-description")
                            ->filter("p")
                            ->each(function ($node) {
                                global $built_area_prev;

                                $built_area_prev .= $node->html();
                            });

                        $built_area = get_string_between($built_area_prev, 'A.C. : ', ' m2');
                    }

                    if (
                        $listing
                        ->filter("body")
                        ->filter("div.site-wrapper")
                        ->filter("div.site-container")
                        ->filter("div.site-main")
                        ->filter("div.container")
                        ->filter("div.row.gutter-60")
                        ->filter("main.content.col-md-8")
                        ->filter("div")
                        ->filter("div")
                        ->filter("section.widget-section.section-widget_listing_location")
                        ->filter("div.widget.widget_listing_location")
                        ->filter("div.wpsight-listing-section.wpsight-listing-section-location")
                        ->filter("div")
                        ->filter("div.wpsight-listing-location")
                        ->filter("div.wpsight-listing-location-note.bs-callout.bs-callout-primary.bs-callout-small")
                        ->count() > 0
                    ) {
                        $address_alternative_prev = $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_location")
                            ->filter("div.widget.widget_listing_location")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-location")
                            ->filter("div")
                            ->filter("div.wpsight-listing-location")
                            ->filter("div.wpsight-listing-location-note.bs-callout.bs-callout-primary.bs-callout-small")
                            ->text();

                        $address_alternative_prev = explode(",", $address_alternative_prev);
                        $address_alternative = $address_alternative_prev[0];

                        $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_location")
                            ->filter("div.widget.widget_listing_location")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-location")
                            ->filter("div")
                            ->filter("div.wpsight-listing-location")
                            ->filter("meta")
                            ->each(function ($node) {
                                global $latitude;
                                global $longitude;

                                if ($node->attr("itemprop") == "latitude") {
                                    $latitude = $node->attr("content");
                                }

                                if ($node->attr("itemprop") == "longitude") {
                                    $longitude = $node->attr("content");
                                }
                            });
                    }
                    else {
                        unset($address);
                        unset($address_alternative);
                        unset($longitude);
                        unset($latitude);
                    }

                    $contact_data = $listing
                        ->filter("body")
                        ->filter("div.site-wrapper")
                        ->filter("div.site-container")
                        ->filter("div.site-main")
                        ->filter("div.container")
                        ->filter("div.row.gutter-60")
                        ->filter("aside.sidebar.col-md-4")
                        ->filter("section.widget-section.section-widget_listing_agent")
                        ->filter("div.widget.widget_listing_agent")
                        ->filter("div.wpsight-listing-section.wpsight-listing-section-agent")
                        ->filter("div.wpsight-listing-agent.clearfix")
                        ->filter("div.wpsight-listing-agent-info")
                        ->filter("div.wpsight-listing-agent-name")
                        ->text();

                    $contact_data = str_replace(array(" (Gama Inmobiliaria) ", " (Giuliana Lucia Giannoni Martin,  RUC 10078718833) ",), array("|", "|",), $contact_data);
                    $contact_data = explode("|", $contact_data);
                    $contact_name = $contact_data[0];
                    $contact_phone = str_replace(" ", "", $contact_data[1]);

                    $array_contacts = array(
                        array(
                            "name" => strval($contact_name),
                            "email" => null,
                            "prefix" => strval("51"),
                            "phone" => strval($contact_phone),
                        ),
                    );

                    if (!isset($area) OR is_null($area)) {
                        if (isset($built_area)) {
                            $area = intval($built_area);
                        }
                        else {
                            unset($area);
                            unset($built_area);
                        }
                    }

                    if (!isset($year_of_construction) OR is_null($year_of_construction)) {
                        unset($year_of_construction);
                    }
                    else {
                        if (str_contains($year_of_construction, "(")) {
                            $year_of_construction_prev = explode("(", $year_of_construction);
                            $year_of_construction = strval($year_of_construction_prev[0]);
                        }
                    }

                    if (!isset($listing_type) OR is_null($listing_type)) {
                        $status = strval("hidden");
                    }
                    else if (!isset($property_type) OR is_null($property_type)) {
                        $status = strval("hidden");
                    }
                    else if (!isset($description) OR is_null($description)) {
                        $status = strval("hidden");
                    }
                    else if (!isset($price) OR is_null($price)) {
                        $status = strval("hidden");
                    }
                    else if (!isset($area) OR is_null($area)) {
                        $status = strval("hidden");
                    }
                    else if (count($array_images) <= 0) {
                        $status = strval("hidden");
                    }
                    else if (!isset($longitude) AND !isset($latitude)) {
                        if (!isset($district) OR !isset($province) OR !isset($department)) {
                            $status = strval("hidden");
                        }
                        else {
                            $status = strval("visible");
                        }
                    }
                    else {
                        $status = strval("visible");
                    }

                    ##########################################################################################
                    #CREATING LISTING
                    ##########################################################################################
                    $message_create = new stdClass();
                    $message_create->action = strval("create");
                    $message_create->source = strval("integration");
                    $message_create->type = strval("listing");
                    $message_create->user_id = intval($user_id);

                    $message_create->property_type = strval($property_type);
                    $message_create->listing_type = strval($listing_type);

                    if ($listing_type == "sale") {
                        $message_create->price = intval($price);
                    }
                    else {
                        $message_create->price_per_month = intval($price);
                    }

                    $message_create->maintenance_price = (isset($maintenance_price) ? intval($maintenance_price) : null);
                    $message_create->description = (isset($description) ? strval($description) : null);
                    $message_create->bathrooms_count = (isset($bathrooms_count) ? intval($bathrooms_count) : null);
                    $message_create->half_bathrooms_count = (isset($half_bathrooms_count) ? intval($half_bathrooms_count) : null);
                    $message_create->bedrooms_count = (isset($bedrooms_count) ? intval($bedrooms_count) : null);
                    $message_create->area = (isset($area) ? intval($area) : null);
                    $message_create->year_of_construction = (isset($year_of_construction) ? strval($year_of_construction) : null);
                    $message_create->parking_slots_count = (isset($parking_slots_count) ? intval($parking_slots_count) : null);
                    $message_create->floor_number = (isset($floor_number) ? intval($floor_number) : null);
                    $message_create->total_floors_count = (isset($total_floors_count) ? intval($total_floors_count) : null);
                    $message_create->built_area = (isset($built_area) ? intval($built_area) : null);
                    $message_create->terrain_area = (isset($built_area) ? intval($built_area) : null);
                    $message_create->qty_env = (isset($qty_env) ? intval($qty_env) : null);
                    $message_create->pet_friendly = (isset($pet_friendly) ? strval($pet_friendly) : null);
                    $message_create->parking_for_visits = (isset($parking_for_visits) ? strval($parking_for_visits) : null);

                    $message_create->status = strval($status);
                    $message_create->publisher_role = strval("realtor");

                    $message_create->package_id = intval($user_package_id);

                    $message_create->contacts = (isset($array_contacts) ? $array_contacts : null);

                    $message_create->location_attributes = new stdClass();
                    $message_create->location_attributes->country = (isset($country) ? strval($country) : null);
                    $message_create->location_attributes->department = (isset($department) ? strval(ucwords(strtolower($department))) : null);
                    $message_create->location_attributes->province = (isset($province) ? strval(ucwords(strtolower($province))) : null);
                    $message_create->location_attributes->district = (isset($district) ? strval(ucwords(strtolower($district))) : null);
                    $message_create->location_attributes->address = (isset($address) ? strval(ucwords(strtolower($address))) : null);
                    $message_create->location_attributes->address_alternative = (isset($address_alternative) ? strval(ucwords(strtolower($address_alternative))) : null);
                    $message_create->location_attributes->latitude = (isset($latitude) ? floatval($latitude) : null);
                    $message_create->location_attributes->longitude = (isset($longitude) ? floatval($longitude) : null);

                    $message_create->image_ids = array_values(array_unique($array_images));

                    $message_create->videos = (isset($array_videos) ? $array_videos : null);

                    $message_create->objects_360 = (isset($array_360) ? $array_360 : null);

                    $message_create->facility_ids = (isset($array_facilities) ? $array_facilities : null);

                    $message_create->id_listing_external = strval($id_listing_external);
                    $message_create->id_realtor = strval($id_listing_external);
                    $message_create->url = strval($link);

                    $message_create = json_encode($message_create);

                    print_r($message_create . "\n");

                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $URL_SERVICE . "/me/validation_data",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_POSTFIELDS => $message_create,
                        CURLOPT_HTTPHEADER => array(
                            "Authorization: " . $authorization,
                            "Content-Type: application/json",
                            "Accept-Language: " . $LANGUAGE_DEFAULT
                        ),
                    ));
                    $answer_create_ok = curl_exec($curl);
                    $answer_create_error = curl_error($curl);
                    curl_close($curl);

                    if ($answer_create_error) {
                        header("HTTP/1.1 400 Bad Request");

                        $error = array (
                            "data" => array (
                                "errors" => array (array (
                                    "key" => "unknow",
                                    "message" => $answer_create_error,
                                    "payload" => array (
                                        "code" => "unknow",
                                    ),
                                    "type" => strval("contempo"),
                                ))
                            )
                        );

                        echo json_encode($error);
                    }
                    else {
                        $answer_create_ok = json_decode($answer_create_ok);

                        if ($answer_create_ok->data->status) {
                            if ($answer_create_ok->data->status == "ok") {
                                echo json_encode($answer_create_ok) . "\n";
                                echo date("Y-m-d H:i:s") . "\n";
                                echo "Remains " . $c_create-- . " of " . $counter_create . " creations\n\n";
                            }
                            else {
                                $answer_error_create_counter++;

                                echo json_encode($answer_create_ok) . "\n";
                                echo date("Y-m-d H:i:s") . "\n";
                                echo "Remains " . $c_create-- . " of " . $counter_create . " creations\n\n";
                            }
                        }
                        else {
                            $answer_error_create_counter++;

                            if ($answer_create_ok->data->errors[0]->message == "[External listing ID] already exist") {
                                array_push($array_external_listing_id_already_exist, $link);

                                $answer_error_create_external_listing_id_counter++;

                                echo json_encode($answer_create_ok) . "\n";
                                echo date("Y-m-d H:i:s") . "\n";
                                echo "Remains " . $c_create-- . " of " . $counter_create . " creations\n\n";
                            }
                            else {
                                echo json_encode($answer_create_ok) . "\n";
                                echo date("Y-m-d H:i:s") . "\n";
                                echo "Remains " . $c_create-- . " of " . $counter_create . " creations\n\n";
                            }
                        }
                    }

                    unset($array_incomming_images);
                    unset($array_images);
                    unset($array_videos);
                    unset($array_facilities);
                    unset($gps);
                    unset($property_type);
                    unset($listing_type);
                    unset($price);
                    unset($price_per_month);
                    unset($price_sale);
                    unset($price_rent);
                    unset($maintenance_price);
                    unset($description);
                    unset($bathrooms_count);
                    unset($half_bathrooms_count);
                    unset($bedrooms_count);
                    unset($area);
                    unset($qty_env);
                    unset($year_of_construction);
                    unset($parking_slots_count);
                    unset($built_area);
                    unset($terrain_area);
                    unset($total_floors_count);
                    unset($floor_number);
                    unset($pet_friendly);
                    unset($parking_for_visits);
                    unset($status);
                    unset($array_contacts);
                    unset($contact_name);
                    unset($contact_email);
                    unset($contact_prefix);
                    unset($contact_phone);
                    unset($country);
                    unset($department);
                    unset($province);
                    unset($district);
                    unset($address);
                    unset($address_alternative);
                    unset($latitude);
                    unset($longitude);
                    unset($array_images);
                    unset($array_360);
                    unset($array_videos);
                    unset($array_facilities);
                    unset($id_listing_external);
                    unset($id_listing_external_sale);
                    unset($id_listing_external_rent);
                    unset($id_realtor);
                    unset($link);
                }
            }

            ##########################################################################################
            #UPDATING ADS
            ##########################################################################################
            $result_update = array_intersect($array_provider, $array_babilonia);

            foreach ($array_external_listing_id_already_exist as $external_listing_id_already_exist) {
                array_push($result_update, $external_listing_id_already_exist);
            }

            #$result_update = array("https://gamainmobiliaria.pe/listing/alquilo-casa-en-cerro-colorado-cerro-azul-canete-en-1ra-fila-4-dorm/");
            $counter_update = count($result_update);
            $c_update = $counter_update;

            sort($result_update);

            if (count($result_update) >= 1) {
                foreach ($result_update as $link) {
                    echo $link . "\n";

                    $array_images = array();
                    $array_videos = array();
                    $array_360 = array();
                    $array_facilities = array();

                    $listing = $client->request('GET', $link);

                    $entire_website = $listing->html();

                    file_put_contents($path_data_website, $link . " - " . date("Y-m-d\TH:i:s\Z") . "\n" . $entire_website . "\n\n\n", FILE_APPEND | LOCK_EX);

                    $listing
                        ->filter("body")
                        ->filter("div.site-wrapper")
                        ->filter("div.site-container")
                        ->filter("div.site-main-top.listing-top")
                        ->filter("div.container")
                        ->filter("section.widget-section.section-widget_listing_image_slider")
                        ->filter("div.widget.widget_listing_image_slider")
                        ->filter("div.wpsight-listing-section.wpsight-listing-section-slider")
                        ->filter("div.wpsight-listing-slider")
                        ->filter("div")
                        ->filter("div")
                        ->filter("div")
                        ->filter("ul")
                        ->filter("li")
                        ->each(function ($node) use(&$array_images) {
                            $node
                                ->filter("img")
                                ->each(function ($node_img) {
                                    global $array_images;

                                    array_push($array_images, $node_img->attr("src"));
                                });
                        });

                    $property_type = strtolower($listing
                        ->filter("body")
                        ->filter("div.site-wrapper")
                        ->filter("div.site-container")
                        ->filter("div.site-main-top.listing-top")
                        ->filter("div.container")
                        ->filter("section.widget-section.section-widget_listing_title")
                        ->filter("div.widget.widget_listing_title")
                        ->filter("div.wpsight-listing-section.wpsight-listing-section-title")
                        ->filter("header.page-header")
                        ->filter("div.wpsight-listing-title.clearfix")
                        ->filter("h1.entry-title")
                        ->text());

                    if (
                        strtolower($property_type) == "vendo, terreno cercado con oficinas y servicios en av.b, el callao (km. 9.5 nestor gambetta)"
                    ) {
                        $property_type = "land";
                    }
                    else if (
                        strtolower($property_type) == "vendo, excelente casona de 2 pisos para oficina o comercio, en jr. miroquesada, lima"
                    ) {
                        $property_type = "commercial";
                    }
                    else {
                        if (
                            str_contains(strtolower($property_type), "casa")
                            OR strpos(strtolower($property_type), "casas") !== false
                        ) {
                            $property_type = "house";
                        }
                        else if (
                            str_contains(strtolower($property_type), "local comercial")
                            OR str_contains(strtolower($property_type), "local industrial")
                        ) {
                            $property_type = "commercial";
                        }
                        else if (
                            str_contains(strtolower($property_type), "dpto")
                            OR str_contains(strtolower($property_type), "dpto.")
                            OR str_contains(strtolower($property_type), "dpto,")
                            OR str_contains(strtolower($property_type), "dptos")
                            OR str_contains(strtolower($property_type), "dptos.")
                            OR str_contains(strtolower($property_type), "dptos,")
                            OR str_contains(strtolower($property_type), "loft")
                            OR str_contains(strtolower($property_type), "dúplex")
                            OR str_contains(strtolower($property_type), "duplex")
                            OR str_contains(strtolower($property_type), "duplex,")
                            OR str_contains(strtolower($property_type), "d\u00faplex")
                            OR str_contains(strtolower($property_type), "d\u00faplex,")
                            OR str_contains(strtolower($property_type), "departamento")
                            OR str_contains(strtolower($property_type), "flat")
                        ) {
                            $property_type = "apartment";
                        }
                        else if (
                            str_contains(strtolower($property_type), "oficina")
                            OR str_contains(strtolower($property_type), "of.")
                            OR str_contains(strtolower($property_type), "oficina,")
                            OR str_contains(strtolower($property_type), "oficinas")
                        ) {
                            $property_type = "office";
                        }
                        else if (
                            str_contains(strtolower($property_type), "terreno")
                            OR str_contains(strtolower($property_type), "terreno,")
                            OR str_contains(strtolower($property_type), "parcelas")
                        ) {
                            $property_type = "land";
                        }
                        else if (
                            str_contains(strtolower($property_type), "cochera")
                        ) {
                            $property_type = "parking";
                        }
                        else if (
                            str_contains(strtolower($property_type), "edificio")
                        ) {
                            $property_type = "building";
                        }
                        else {
                            unset($property_type);
                        }
                    }

                    if (
                        $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_description")
                            ->filter("div.widget.widget_listing_description")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-description")
                            ->filter("div.wpsight-listing-description")
                            ->count() > 0
                    ) {
                        $description = $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_description")
                            ->filter("div.widget.widget_listing_description")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-description")
                            ->filter("div.wpsight-listing-description")
                            ->html();

                        $description = str_replace("<br>", "", $description);
                    }

                    if (str_contains($description, " https://youtu")) {
                        $video_url = get_string_between($description, 'https://youtu', '<');
                        $video_url = strval("https://youtu" . $video_url);

                        array_push($array_videos, $video_url);
                    }

                    if (
                        $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_price")
                            ->filter("div.widget.widget_listing_price")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-info")
                            ->filter("div.wpsight-listing-info.clearfix")
                            ->filter("div.row.gutter-40")
                            ->filter("div.col-xs-6")
                            ->filter("div.wpsight-listing-price")
                            ->filter("span.listing-price-value")
                            ->count() > 0
                    ) {
                        $price = $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_price")
                            ->filter("div.widget.widget_listing_price")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-info")
                            ->filter("div.wpsight-listing-info.clearfix")
                            ->filter("div.row.gutter-40")
                            ->filter("div.col-xs-6")
                            ->filter("div.wpsight-listing-price")
                            ->filter("span.listing-price-value")
                            ->text();

                        $price = str_replace(array(",", ".",), array("", "",), $price);
                    }

                    $id_listing_external = $listing
                        ->filter("body")
                        ->filter("div.site-wrapper")
                        ->filter("div.site-container")
                        ->filter("div.site-main")
                        ->filter("div.container")
                        ->filter("div.row.gutter-60")
                        ->filter("main.content.col-md-8")
                        ->filter("div")
                        ->filter("div")
                        ->filter("section.widget-section.section-widget_listing_price")
                        ->filter("div.widget.widget_listing_price")
                        ->filter("div.wpsight-listing-section.wpsight-listing-section-info")
                        ->filter("div.wpsight-listing-info.clearfix")
                        ->filter("div.row.gutter-40")
                        ->filter("div.col-xs-6")
                        ->filter("div.wpsight-listing-id")
                        ->text();

                    $listing_type = $listing
                        ->filter("body")
                        ->filter("div.site-wrapper")
                        ->filter("div.site-container")
                        ->filter("div.site-main")
                        ->filter("div.container")
                        ->filter("div.row.gutter-60")
                        ->filter("main.content.col-md-8")
                        ->filter("div")
                        ->filter("div")
                        ->filter("section.widget-section.section-widget_listing_price")
                        ->filter("div.widget.widget_listing_price")
                        ->filter("div.wpsight-listing-section.wpsight-listing-section-info")
                        ->filter("div.wpsight-listing-info.clearfix")
                        ->filter("div.row.gutter-40")
                        ->filter("div.col-xs-6")
                        ->filter("div.wpsight-listing-offer")
                        ->filter("span")
                        ->text();

                    switch (strtolower($listing_type)) {
                        case 'en alquiler':
                            $listing_type = "rent";
                        break;

                        case 'en venta':
                            $listing_type = "sale";
                        break;
                    }

                    $listing
                        ->filter("body")
                        ->filter("div.site-wrapper")
                        ->filter("div.site-container")
                        ->filter("div.site-main")
                        ->filter("div.container")
                        ->filter("div.row.gutter-60")
                        ->filter("main.content.col-md-8")
                        ->filter("div")
                        ->filter("div")
                        ->filter("section.widget-section.section-widget_listing_details")
                        ->filter("div.widget.widget_listing_details")
                        ->filter("div.wpsight-listing-section.wpsight-listing-section-details")
                        ->filter("div.wpsight-listing-details.clearfix")
                        ->filter("span.listing-details-detail")
                        ->each(function ($node) {
                            global $bedrooms_count;
                            global $bathrooms_count;
                            global $area;
                            global $built_area;
                            global $parking_slots_count;
                            global $year_of_construction;

                            $old_string = array("Dormitorios: ",    "Baños: ",  "AT: ",     "AC: ", "Garage: ", "Construido en: ",  "m²");
                            $new_string = array("",                 "",         "",         "",     "",         "",                 "");

                            if (strpos($node->text(), "Dormitorios:") !== false) {
                                $bedrooms_count = str_replace($old_string, $new_string, $node->text());
                            }

                            if (strpos($node->text(), "Baños:") !== false) {
                                $bathrooms_count = str_replace($old_string, $new_string, $node->text());
                            }

                            if (strpos($node->text(), "AT:") !== false) {
                                $area = str_replace($old_string, $new_string, $node->text());
                            }

                            if (strpos($node->text(), "AC:") !== false) {
                                $built_area = str_replace($old_string, $new_string, $node->text());
                            }

                            if (strpos($node->text(), "Garage:") !== false) {
                                $parking_slots_count = str_replace($old_string, $new_string, $node->text());
                            }

                            if (strpos($node->text(), "Construido en:") !== false) {
                                $year_of_construction_prev = explode(",", $node->text());
                                $year_of_construction = str_replace($old_string, $new_string, $year_of_construction_prev[0]);
                            }
                        });

                    if (!isset($area)) {
                        $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_description")
                            ->filter("div.widget.widget_listing_description")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-description")
                            ->filter("div.wpsight-listing-description")
                            ->filter("p")
                            ->each(function ($node) {
                                global $area_prev;

                                $area_prev .= $node->html();
                            });

                        $area = get_string_between($area_prev, 'A.T. : ', ' m2');
                    }

                    if (!isset($built_area)) {
                        $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_description")
                            ->filter("div.widget.widget_listing_description")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-description")
                            ->filter("div.wpsight-listing-description")
                            ->filter("p")
                            ->each(function ($node) {
                                global $built_area_prev;

                                $built_area_prev .= $node->html();
                            });

                        $built_area = get_string_between($built_area_prev, 'A.C. : ', ' m2');
                    }

                    if (
                        $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_location")
                            ->filter("div.widget.widget_listing_location")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-location")
                            ->filter("div")
                            ->filter("div.wpsight-listing-location")
                            ->filter("div.wpsight-listing-location-note.bs-callout.bs-callout-primary.bs-callout-small")
                            ->count() > 0
                    ) {
                        $address_alternative_prev = $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_location")
                            ->filter("div.widget.widget_listing_location")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-location")
                            ->filter("div")
                            ->filter("div.wpsight-listing-location")
                            ->filter("div.wpsight-listing-location-note.bs-callout.bs-callout-primary.bs-callout-small")
                            ->text();

                        $address_alternative_prev = explode(",", $address_alternative_prev);
                        $address_alternative = $address_alternative_prev[0];

                        $listing
                            ->filter("body")
                            ->filter("div.site-wrapper")
                            ->filter("div.site-container")
                            ->filter("div.site-main")
                            ->filter("div.container")
                            ->filter("div.row.gutter-60")
                            ->filter("main.content.col-md-8")
                            ->filter("div")
                            ->filter("div")
                            ->filter("section.widget-section.section-widget_listing_location")
                            ->filter("div.widget.widget_listing_location")
                            ->filter("div.wpsight-listing-section.wpsight-listing-section-location")
                            ->filter("div")
                            ->filter("div.wpsight-listing-location")
                            ->filter("meta")
                            ->each(function ($node) {
                                global $latitude;
                                global $longitude;

                                if ($node->attr("itemprop") == "latitude") {
                                    $latitude = $node->attr("content");
                                }

                                if ($node->attr("itemprop") == "longitude") {
                                    $longitude = $node->attr("content");
                                }
                            });
                    }
                    else {
                        unset($address);
                        unset($address_alternative);
                        unset($longitude);
                        unset($latitude);
                    }

                    $contact_data = $listing
                        ->filter("body")
                        ->filter("div.site-wrapper")
                        ->filter("div.site-container")
                        ->filter("div.site-main")
                        ->filter("div.container")
                        ->filter("div.row.gutter-60")
                        ->filter("aside.sidebar.col-md-4")
                        ->filter("section.widget-section.section-widget_listing_agent")
                        ->filter("div.widget.widget_listing_agent")
                        ->filter("div.wpsight-listing-section.wpsight-listing-section-agent")
                        ->filter("div.wpsight-listing-agent.clearfix")
                        ->filter("div.wpsight-listing-agent-info")
                        ->filter("div.wpsight-listing-agent-name")
                        ->text();

                    $contact_data = str_replace(array(" (Gama Inmobiliaria) ", " (Giuliana Lucia Giannoni Martin,  RUC 10078718833) ",), array("|", "|",), $contact_data);
                    $contact_data = explode("|", $contact_data);
                    $contact_name = $contact_data[0];
                    $contact_phone = str_replace(" ", "", $contact_data[1]);

                    $array_contacts = array(
                        array(
                            "name" => strval($contact_name),
                            "email" => null,
                            "prefix" => strval("51"),
                            "phone" => strval($contact_phone),
                        ),
                    );

                    if (!isset($area) OR is_null($area)) {
                        if (isset($built_area)) {
                            $area = intval($built_area);
                        }
                        else {
                            unset($area);
                            unset($built_area);
                        }
                    }

                    if (!isset($year_of_construction) OR is_null($year_of_construction)) {
                        unset($year_of_construction);
                    }
                    else {
                        if (str_contains($year_of_construction, "(")) {
                            $year_of_construction_prev = explode("(", $year_of_construction);
                            $year_of_construction = strval($year_of_construction_prev[0]);
                        }
                    }

                    if (!isset($listing_type) OR is_null($listing_type)) {
                        $status = strval("hidden");
                    }
                    else if (!isset($property_type) OR is_null($property_type)) {
                        $status = strval("hidden");
                    }
                    else if (!isset($description) OR is_null($description)) {
                        $status = strval("hidden");
                    }
                    else if (!isset($price) OR is_null($price)) {
                        $status = strval("hidden");
                    }
                    else if (!isset($area) OR is_null($area)) {
                        $status = strval("hidden");
                    }
                    else if (count($array_images) <= 0) {
                        $status = strval("hidden");
                    }
                    else if (!isset($longitude) AND !isset($latitude)) {
                        if (!isset($district) OR !isset($province) OR !isset($department)) {
                            $status = strval("hidden");
                        }
                        else {
                            $status = strval("visible");
                        }
                    }
                    else {
                        $status = strval("visible");
                    }

                    ##########################################################################################
                    #UPDATING LISTING
                    ##########################################################################################
                    $message_update = new stdClass();
                    $message_update->action = strval("update");
                    $message_update->source = strval("integration");
                    $message_update->type = strval("listing");
                    $message_update->user_id = intval($user_id);

                    $message_update->property_type = strval($property_type);
                    $message_update->listing_type = strval($listing_type);

                    if ($listing_type == "sale") {
                        $message_update->price = intval($price);
                    }
                    else {
                        $message_update->price_per_month = intval($price);
                    }

                    $message_update->maintenance_price = (isset($maintenance_price) ? intval($maintenance_price) : null);
                    $message_update->description = (isset($description) ? strval($description) : null);
                    $message_update->bathrooms_count = (isset($bathrooms_count) ? intval($bathrooms_count) : null);
                    $message_update->half_bathrooms_count = (isset($half_bathrooms_count) ? intval($half_bathrooms_count) : null);
                    $message_update->bedrooms_count = (isset($bedrooms_count) ? intval($bedrooms_count) : null);
                    $message_update->area = (isset($area) ? intval($area) : null);
                    $message_update->year_of_construction = (isset($year_of_construction) ? intval($year_of_construction) : null);
                    $message_update->parking_slots_count = (isset($parking_slots_count) ? intval($parking_slots_count) : null);
                    $message_update->floor_number = (isset($floor_number) ? intval($floor_number) : null);
                    $message_update->total_floors_count = (isset($total_floors_count) ? intval($total_floors_count) : null);
                    $message_update->built_area = (isset($built_area) ? intval($built_area) : null);
                    $message_update->terrain_area = (isset($built_area) ? intval($built_area) : null);
                    $message_update->qty_env = (isset($qty_env) ? intval($qty_env) : null);
                    $message_update->pet_friendly = (isset($pet_friendly) ? strval($pet_friendly) : null);
                    $message_update->parking_for_visits = (isset($parking_for_visits) ? strval($parking_for_visits) : null);

                    $message_update->status = strval($status);
                    $message_update->publisher_role = strval("realtor");

                    $message_update->package_id = intval($user_package_id);

                    $message_update->contacts = (isset($array_contacts) ? $array_contacts : null);

                    $message_update->location_attributes = new stdClass();
                    $message_update->location_attributes->country = (isset($country) ? strval($country) : null);
                    $message_update->location_attributes->department = (isset($department) ? strval(ucwords(strtolower($department))) : null);
                    $message_update->location_attributes->province = (isset($province) ? strval(ucwords(strtolower($province))) : null);
                    $message_update->location_attributes->district = (isset($district) ? strval(ucwords(strtolower($district))) : null);
                    $message_update->location_attributes->address = (isset($address) ? strval(ucwords(strtolower($address))) : null);
                    $message_update->location_attributes->address_alternative = (isset($address_alternative) ? strval(ucwords(strtolower($address_alternative))) : null);
                    $message_update->location_attributes->latitude = (isset($latitude) ? floatval($latitude) : null);
                    $message_update->location_attributes->longitude = (isset($longitude) ? floatval($longitude) : null);

                    $message_update->image_ids = array_values(array_unique($array_images));

                    $message_update->videos = (isset($array_videos) ? $array_videos : null);

                    $message_update->objects_360 = (isset($array_360) ? $array_360 : null);

                    $message_update->facility_ids = (isset($array_facilities) ? $array_facilities : null);

                    $message_update->id_listing_external = array(
                        $id_listing_external,
                    );
                    $message_update->id_realtor = strval($id_listing_external);
                    $message_update->url = strval($link);

                    $message_update = json_encode($message_update);

                    print_r($message_update . "\n");

                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $URL_SERVICE . "/me/validation_data",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CUSTOMREQUEST => "PUT",
                        CURLOPT_POSTFIELDS => $message_update,
                        CURLOPT_HTTPHEADER => array(
                            "Authorization: " . $authorization,
                            "Content-Type: application/json",
                            "Accept-Language: " . $LANGUAGE_DEFAULT
                        ),
                    ));
                    $answer_update_ok = curl_exec($curl);
                    $answer_update_error = curl_error($curl);
                    curl_close($curl);

                    if ($answer_update_error) {
                        header("HTTP/1.1 400 Bad Request");

                        $error = array (
                            "data" => array (
                                "errors" => array (array (
                                    "key" => "unknow",
                                    "message" => $answer_update_error,
                                    "payload" => array (
                                        "code" => "unknow",
                                    ),
                                    "type" => strval("contempo"),
                                ))
                            )
                        );

                        echo json_encode($error);
                    }
                    else {
                        $answer_update_ok = json_decode($answer_update_ok);

                        if ($answer_update_ok->data->status) {
                            if ($answer_update_ok->data->status == "ok") {
                                echo json_encode($answer_update_ok) . "\n";
                                echo date("Y-m-d H:i:s") . "\n";
                                echo "Remains " . $c_update-- . " of " . $counter_update . " updates\n\n";
                            }
                            else {
                                $answer_error_update_counter++;

                                echo json_encode($answer_update_ok) . "\n";
                                echo date("Y-m-d H:i:s") . "\n";
                                echo "Remains " . $c_update-- . " of " . $counter_update . " updates\n\n";
                            }
                        }
                        else {
                            $answer_error_update_counter++;

                            echo json_encode($answer_update_ok) . "\n";
                            echo date("Y-m-d H:i:s") . "\n";
                            echo "Remains " . $c_update-- . " of " . $counter_update . " updates\n\n";
                        }
                    }

                    unset($address_prev);
                    unset($array_incomming_images);
                    unset($array_images);
                    unset($array_videos);
                    unset($array_facilities);
                    unset($gps);
                    unset($property_type);
                    unset($listing_type);
                    unset($price);
                    unset($price_per_month);
                    unset($price_sale);
                    unset($price_rent);
                    unset($maintenance_price);
                    unset($description);
                    unset($bathrooms_count);
                    unset($half_bathrooms_count);
                    unset($bedrooms_count);
                    unset($area);
                    unset($qty_env);
                    unset($year_of_construction);
                    unset($parking_slots_count);
                    unset($built_area);
                    unset($terrain_area);
                    unset($total_floors_count);
                    unset($floor_number);
                    unset($pet_friendly);
                    unset($parking_for_visits);
                    unset($status);
                    unset($array_contacts);
                    unset($contact_name);
                    unset($contact_email);
                    unset($contact_prefix);
                    unset($contact_phone);
                    unset($country);
                    unset($department);
                    unset($province);
                    unset($district);
                    unset($address);
                    unset($address_alternative);
                    unset($latitude);
                    unset($longitude);
                    unset($array_images);
                    unset($array_360);
                    unset($array_videos);
                    unset($array_facilities);
                    unset($id_listing_external);
                    unset($id_listing_external_sale);
                    unset($id_listing_external_rent);
                    unset($id_realtor);
                    unset($link);
                }
            }
        }
    }

    $global_variable = "variable";
    require '/var/www/resources/php/babilonia-io/globalVariable.php';

    $global_mail = "internal";
    require '/var/www/resources/php/babilonia-io/globalMail.php';

    $text = $global_internal_simple_head . $global_internal_simple_logo . "
        <table align='center' cellpadding='0' cellspacing='0' class='tableMain'>
            <tr>
                <td class='tdBodyJustify'>
                    <div class='divCalibri14Body'>
                        Estimado(a):
                        <br>
                        Se ha finalizado un scraping automatico. Estos son los datos:
                        <br><br>
                        - Cliente: " . $path . "<br>
                        - Hora inicio: " . $date_start . "<br>
                        - Hora fin: " . date("Y-m-d H:i:s") . "<br>
                        - Avisos eliminados: " . count($result_delete) . "<br>
                           * Errores del proceso: " . $answer_error_delete_counter . "<br>
                        - Avisos creados: " . count($result_create) . "<br>
                           * Errores del proceso: " . $answer_error_create_counter . "<br>
                           * Errores de external ID: " . $answer_error_create_external_listing_id_counter . "<br>
                        - Avisos actualizados: " . count($result_update) . "<br>
                           * Errores del proceso: " . $answer_error_update_counter . "<br>
                        <br>
                        Gracias.
                    </div>
                </td>
            </tr>
        </table>
    " . $global_internal_simple_footer_helpdesk;

    $mail->setFrom($email_service_desk, $name_service_desk);

    try {
        $mail->AddAddress($email_technology);
        $mail->Subject	=   '[NOTIFICATION] Scraping ended';
        $mail->Body		=   $text;
        $mail->Send();
        $mail->ClearAllRecipients();
    }
    catch (phpmailerException $e) {
        require_once '/var/www/resources/php/babilonia-io/errorMail.php';
        exit();
    }

    $mail->smtpClose();

    $duration = microtime(true) - $time_start;
    $hours = intval($duration / 60 / 60);
    $minutes = intval($duration / 60) - $hours * 60;
    $seconds = intval($duration - $hours * 60 * 60 - $minutes * 60);

    echo "\nTotal execution time: " . $hours . "hrs " . $minutes . "mins " . $seconds . "secs\n";
    echo date("Y-m-d H:i:s") . "\n\n";

    exit();
?>
