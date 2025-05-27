<?php

    use Symfony\Component\BrowserKit\HttpBrowser;
    use Symfony\Component\DomCrawler\Crawler;
    use Symfony\Component\HttpClient\HttpClient;

    include_once __DIR__ . "/InmobaperuInmueble.php";
    
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
    $users_packages = $mongo->babilonia->users_packages;

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
    $user_email = ($MASTER_ENVIRONMENT == "prod" ? strval("GIULIANA.GIANNONI@YAHOO.COM") : strval("julio.lopez@babilonia.pe"));
    $path = "https://inmobaperu.com";
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
    $result_create = array();
    $result_update = array();
    $result_delete = array();



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

    ### script para obtener datos de un aviso

    // $inmueble = new InmobaperuInmueble("https://inmobaperu.com/listing/alquiler-de-edificio-en-magdalena-del-mar/");
    // return;
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
    
    $views = array("departamentos", "casas-2", "oficinas", "local-comercial", "terrenos");
    #$views = array("local-comercial");

    foreach($views as $view){
        
        ##########################################################################################
        #EXTRAER PAGINA MAX POR VISTA DE PROPIEDAD VISITADA
        ##########################################################################################
        $browser = $client->request('GET', $path . "/$view/");
        $script = $browser->filter('script#stm-search-form-advanced-js-before')->text();
        $response = str_replace("/* <![CDATA[ */ var stm_listing_pagination_data = json_parse('", "", $script);
        $response = str_replace("') /* ]]> */", "", $response);
        $response = str_replace("\\\"", "\"", $response); 
        $response = json_decode($response, true);
        $total_pages = $response["total_pages"] ?? 1;
        
        ##########################################################################################
        #GETTING PROVIDER DATA
        ##########################################################################################
        for ($page = 1; $page <= $total_pages; $page++) {
            if ($page == 1) {
                $listing = $client->request('GET', $path . "/$view/");
            }
            else {
                $listing = $client->request('GET', $path . "/$view/?current_page=" . $page );
            }
            
            if (
                $listing
                    ->filter(".ulisting-item-grid")
                    ->count() == 0
            ) {
                unset($listing);
                continue;
            }
            else {
        
            $listing
                ->filter("body")
                ->filter(".ulisting-item-grid")
                ->filter(".inventory-thumbnail-box")
                ->each(function($node) use(&$array_provider, $path){
        
                if ($node->attr('href') <> "javascript:void(0)") {
                    $link = $node->filter("a");
                    $link = $link->attr('href');
                    $id = $node->attr('data-id');
                    
                    try {                                                        
                    $url =  $path . "/wp-json/wp/v2/listing/" . $id;
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_URL, $url);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_HEADER, false);
                    $response = curl_exec($curl);
                    curl_close($curl);
        
                    $data = json_decode($response, true);
                    $slug = str_replace($path . "/listing/", "", $link);
                    if((!isset($data["data"]["status"]) || $data["data"]["status"]!=404) && $slug !="" && preg_match('/^[a-zA-Z0-9\-_]+(\/)?$/', $slug)){
                        $_status = strval($data["status"] ?? 'unpublish');
                        
                        if($_status == 'publish'){
                            array_push($array_provider, strval($link));
                            echo "✅ url agregada: " . $link . "\n";
                        }
                    }else{
                        echo "❌ la url no es valida: " . $slug . "\n";
                    }
        
                        unset($_status);
                        unset($url);
                        unset($curl);
                        unset($response);
                        unset($data);
                    } catch (Exception $e) {
                        print_r($e->getMessage());
                    }
                    unset($link);
                    unset($id);
                }
                });
                unset($listing);
            }

            // if($page > 1) break;
        }
        
        unset($browser);
        unset($script);
        unset($response);
        unset($total_pages);
        
    }

    $array_provider = array_unique($array_provider);

    // print_r($array_provider);
    // echo "\n\n";
    echo "cantidad de avisos encontrados: " . count($array_provider) . "\n\n";

    if (count($array_provider) >= 1) {

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

                        if (isset($answer_delete_ok->data->status) && !empty($answer_delete_ok->data->status)) {
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
            $counter_create = count($result_create);
            $c_create = $counter_create;

            sort($result_create);

            echo "Avisos a crear: " . count($result_create) . "\n\n";        
            echo "\n\n";

            if (count($result_create) >= 1) {
                foreach ($result_create as $link) {
                    echo $link . "\n";

                    $inmueble = new InmobaperuInmueble($link);
                    $entire_website = $inmueble->getEntireWebsite();

                    file_put_contents($path_data_website, $link . " - " . date("Y-m-d\TH:i:s\Z") . "\n" . $entire_website . "\n\n\n", FILE_APPEND | LOCK_EX);

                    ##########################################################################################
                    #CREATING LISTING
                    ##########################################################################################
                    $message_create = new stdClass();
                    $message_create->action = strval("create");
                    $message_create->source = strval("integration");
                    $message_create->type = strval("listing");
                    $message_create->user_id = intval($user_id);
                    
                    $message_create->property_type = $inmueble->getPropertyType();
                    $message_create->listing_type = $inmueble->getListingType();

                    if ($inmueble->getListingType() == "sale") {
                        $message_create->price = $inmueble->getPrice();
                    }
                    else {
                        $message_create->price_per_month = $inmueble->getPrice();
                    }

                    $message_create->maintenance_price = $inmueble->getMaintenancePrice();
                    $message_create->description = $inmueble->getDescription();
                    $message_create->bathrooms_count = $inmueble->getBathroomsCount();
                    $message_create->half_bathrooms_count = $inmueble->getHalfBathroomsCount();
                    $message_create->bedrooms_count = $inmueble->getBedroomsCount();
                    $message_create->area = $inmueble->getArea();
                    $message_create->year_of_construction = $inmueble->getYearOfConstruction();
                    $message_create->parking_slots_count = $inmueble->getParkingSlotsCount();
                    $message_create->floor_number = $inmueble->getFloorNumber();
                    $message_create->total_floors_count = $inmueble->getTotalFloorsCount();
                    $message_create->built_area = $inmueble->getBuiltArea();
                    $message_create->terrain_area = $inmueble->getTerrainArea();
                    $message_create->qty_env = $inmueble->getQtyEnv();
                    $message_create->pet_friendly = $inmueble->getPetFriendly();
                    $message_create->parking_for_visits = $inmueble->getParkingForVisits();

                    $message_create->status = $inmueble->getStatus();
                    $message_create->publisher_role = $inmueble->getPublisherRole();

                    $message_create->package_id = intval($user_package_id);

                    $message_create->contacts = $inmueble->getContacts();

                    // $message_create->location_attributes = new stdClass();
                    // $message_create->location_attributes->country = $inmueble->getCountry();
                    // $message_create->location_attributes->department = $inmueble->getDepartment();
                    // $message_create->location_attributes->province = $inmueble->getProvince();
                    // $message_create->location_attributes->district = $inmueble->getDistrict();
                    // $message_create->location_attributes->address = $inmueble->getAddress();
                    // $message_create->location_attributes->address_alternative = $inmueble->getAddressAlternative();
                    // $message_create->location_attributes->latitude = $inmueble->getLatitude();
                    // $message_create->location_attributes->longitude = $inmueble->getLongitude();

                    $message_create->image_ids = $inmueble->getImageIds();

                    $message_create->videos = $inmueble->getVideos();

                    $message_create->objects_360 = $inmueble->getArray360();

                    $message_create->facility_ids = $inmueble->getFacilityIds();

                    $message_create->id_listing_external = $inmueble->getIdListingExternal();
                    $message_create->id_realtor = $inmueble->getIdRealtor();
                    $message_create->url = $inmueble->getUrl();

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
                        
                        if (isset($answer_create_ok->data->status) && !empty($answer_create_ok->data->status)) {
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

                    unset($inmueble);
                    unset($id_listing_external);
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

            echo "cantidad de avisos a actualizar: " . count($result_update) . "\n\n";
            echo "\n\n";

            if (count($result_update) >= 1) {
                foreach ($result_update as $link) {
                    echo $link . "\n";

                    $inmueble = new InmobaperuInmueble($link);
                    $entire_website = $inmueble->getEntireWebsite();

                    file_put_contents($path_data_website, $link . " - " . date("Y-m-d\TH:i:s\Z") . "\n" . $entire_website . "\n\n\n", FILE_APPEND | LOCK_EX);

                    ##########################################################################################
                    #UPDATING LISTING
                    ##########################################################################################
                    $message_update = new stdClass();
                    $message_update->action = strval("update");
                    $message_update->source = strval("integration");
                    $message_update->type = strval("listing");
                    $message_update->user_id = intval($user_id);

                    $message_update->property_type = $inmueble->getPropertyType();
                    $message_update->listing_type = $inmueble->getListingType();

                    if ($inmueble->getListingType() == "sale") {
                        $message_update->price = $inmueble->getPrice();
                    }
                    else {
                        $message_update->price_per_month = $inmueble->getPrice();
                    }

                    $message_update->maintenance_price = $inmueble->getMaintenancePrice();
                    $message_update->description = $inmueble->getDescription();
                    $message_update->bathrooms_count = $inmueble->getBathroomsCount();
                    $message_update->half_bathrooms_count = $inmueble->getHalfBathroomsCount();
                    $message_update->bedrooms_count = $inmueble->getBedroomsCount();
                    $message_update->area = $inmueble->getArea();
                    $message_update->year_of_construction = $inmueble->getYearOfConstruction();
                    $message_update->parking_slots_count = $inmueble->getParkingSlotsCount();
                    $message_update->floor_number = $inmueble->getFloorNumber();
                    $message_update->total_floors_count = $inmueble->getTotalFloorsCount();
                    $message_update->built_area = $inmueble->getBuiltArea();
                    $message_update->terrain_area = $inmueble->getTerrainArea();
                    $message_update->qty_env = $inmueble->getQtyEnv();
                    $message_update->pet_friendly = $inmueble->getPetFriendly();
                    $message_update->parking_for_visits = $inmueble->getParkingForVisits();

                    $message_update->status = $inmueble->getStatus();
                    $message_update->publisher_role = $inmueble->getPublisherRole();

                    $message_update->package_id = intval($user_package_id);

                    $message_update->contacts = $inmueble->getContacts();

                    // $message_update->location_attributes = new stdClass();
                    // $message_update->location_attributes->country = $inmueble->getCountry();
                    // $message_update->location_attributes->department = $inmueble->getDepartment();
                    // $message_update->location_attributes->province = $inmueble->getProvince();
                    // $message_update->location_attributes->district = $inmueble->getDistrict();
                    // $message_update->location_attributes->address = $inmueble->getAddress();
                    // $message_update->location_attributes->address_alternative = $inmueble->getAddressAlternative();
                    // $message_update->location_attributes->latitude = $inmueble->getLatitude();
                    // $message_update->location_attributes->longitude = $inmueble->getLongitude();

                    $message_update->image_ids = $inmueble->getImageIds();

                    $message_update->videos = $inmueble->getVideos();

                    $message_update->objects_360 = $inmueble->getArray360();

                    $message_update->facility_ids = $inmueble->getFacilityIds();

                    $id_listing_external = $inmueble->getIdListingExternal();
                    $message_update->id_listing_external = array(
                        $id_listing_external,
                    );
                    $message_update->id_realtor = $id_listing_external;
                    $message_update->url = $inmueble->getUrl();

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

                        if (isset($answer_update_ok->data->status) && !empty($answer_update_ok->data->status)) {
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

                    unset($inmueble);
                    unset($id_listing_external);
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
