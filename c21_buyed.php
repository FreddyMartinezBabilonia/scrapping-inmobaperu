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

    $global_db_type = "redis";
    require '/var/www/resources/php/babilonia-io/appConnection.php';

    $key_environment = $MASTER_ENVIRONMENT;
    $key_data = "internal";
    require '/var/www/resources/php/babilonia-io/globalCredential.php';

    $current_date = new DateTime("now", new DateTimeZone('+00:00'));

    $currency = $mongo->babilonia->currency;
    $users = $mongo->babilonia->users;
    $listings = $mongo->babilonia->listings;
    $listings_scraping_temp = $mongo->babilonia->listings_scraping_temp;
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

    $client = new HttpBrowser(HttpClient::create(['timeout' => 180]));

    $path = "https://century21.pe";

    /*
        EN LA URL QUE SE CAPTURA POSTERIORMENTE, YA SE INCLUYE EL /
    */
    print_r("
    _
    |_  _ |_ .| _  _ . _     |_|_  _  |_    |_   (_ _  _  |_  _  _  _ _
    |_)(_||_)||(_)| )|(_|,   |_| )(-  | )|_||_)  | (_)|   | )(_)|||(-_)
    \n\n\n\n");

    $array_offices_c21 = array(
        #idAfiliados 1
        #PAQUETE EXPIRADO
        #651,
        #idAfiliados 5
        #PAQUETE EXPIRADO
        #655,
        #idAfiliados 6
        #PAGADO - CENTURY AMBASSADOR
        641,
        #idAfiliados 10
        #PAQUETE EXPIRADO
        #557,
        #idAfiliados 11
        #PAQUETE EXPIRADO
        #662,
        #idAfiliados 14
        #PAQUETE EXPIRADO
        #657,
        #idAfiliados 15
        #PAQUETE EXPIRADO
        #644,
        #idAfiliados 17
        #PAQUETE EXPIRADO
        #652,
        #idAfiliados 19
        #PAQUETE EXPIRADO
        #642,
        #idAfiliados 25
        #PAQUETE EXPIRADO
        #660,
        #idAfiliados 26
        #PAQUETE EXPIRADO
        #656,
        #idAfiliados 29
        #PAQUETE EXPIRADO
        #654,
        #idAfiliados 31
        #PAQUETE EXPIRADO
        #658,
        #idAfiliados 33
        #PAQUETE EXPIRADO
        #648,
        #idAfiliados 34
        #PAQUETE EXPIRADO
        #649,
        #idAfiliados 37
        #PAQUETE EXPIRADO
        #653,
        #idAfiliados 38
        #PAQEUTE EXPIRADO
        #646,
        #idAfiliados 39
        #PAQEUTE EXPIRADO
        #645,
        #idAfiliados 40
        #PAQEUTE EXPIRADO
        #659,
        #idAfiliados 41
        #PAQEUTE EXPIRADO
        #650,
        #idAfiliados 42
        #PAQEUTE EXPIRADO
        #2024-04-04 - PAGADO - C21 Valor Inmobiliario
        #2024-08-27
        #RODRIGO MORALES CORREA - SE RETIRA CLIENTE, YA QUE NO PERTENECE AL GRUPO C21
        #664,
        #idAfiliados 43
        #PAQEUTE EXPIRADO
        #4299,
        #idAfiliados 44
        #PAQEUTE EXPIRADO
        #647,
        #idAfiliados 45
        #PAQEUTE EXPIRADO
        #663,
        #idAfiliados 46
        #PAQEUTE EXPIRADO
        #893,
        #idAfiliados 47
        #PAQEUTE EXPIRADO
        #892,
    );

    if (count($array_offices_c21) > 0) {
        ##########################################################################################
        #DELETEING PREVIOUSLY DATA
        ##########################################################################################
        $delete_listings_scraping_temp = $listings_scraping_temp->deleteMany(
            array(
                'id' => strval("c21"),
            )
        );

        ##########################################################################################
        #PROVISIONAL GETTING LISTINGS
        ##########################################################################################

        ##########################################################################################
        #VENTA
        ##########################################################################################
        for ($i = 1; $i <= 100; $i++) {
            $counter_listings = 0;

            $url_prev = strval("https://century21.pe/busqueda/tipo_departamento/operacion_venta/recamaras_1-2-3/pagina_" . $i . "?json=true");

            echo "- analyzing url: " . $url_prev . " - ";

            $curl= curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => $url_prev,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.70 Safari/537.36",
                    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng;q=0.8,application/signed-exchange;v=b3",
                    "Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7",
                ),
            ));

            $answer_ok = curl_exec($curl);
            $answer_error = curl_error($curl);
            curl_close($curl);

            $obj = json_decode($answer_ok);

            foreach ($obj->propiedades as $data) {
                $link = strval($path . $data->urlCorrecta);
                $url_listings = strval($url_prev);

                switch ($data->idAfiliados) {
                    case '1':
                        $babilonia_user_id = intval(651);
                    break;

                    case '5':
                        $babilonia_user_id = intval(655);
                    break;

                    case '6':
                        $babilonia_user_id = intval(641);
                    break;

                    case '10':
                        $babilonia_user_id = intval(557);
                    break;

                    case '11':
                        $babilonia_user_id = intval(662);
                    break;

                    case '14':
                        $babilonia_user_id = intval(657);
                    break;

                    case '15':
                        $babilonia_user_id = intval(644);
                    break;

                    case '17':
                        $babilonia_user_id = intval(652);
                    break;

                    case '19':
                        $babilonia_user_id = intval(642);
                    break;

                    case '25':
                        $babilonia_user_id = intval(660);
                    break;

                    case '26':
                        $babilonia_user_id = intval(656);
                    break;

                    case '29':
                        $babilonia_user_id = intval(654);
                    break;

                    case '31':
                        $babilonia_user_id = intval(658);
                    break;

                    case '33':
                        $babilonia_user_id = intval(648);
                    break;

                    case '34':
                        $babilonia_user_id = intval(649);
                    break;

                    case '37':
                        $babilonia_user_id = intval(653);
                    break;

                    case '38':
                        $babilonia_user_id = intval(646);
                    break;

                    case '39':
                        $babilonia_user_id = intval(645);
                    break;

                    case '40':
                        $babilonia_user_id = intval(659);
                    break;

                    case '41':
                        $babilonia_user_id = intval(650);
                    break;

                    case '42':
                        $babilonia_user_id = intval(664);
                    break;

                    case '43':
                        $babilonia_user_id = intval(4299);
                    break;

                    case '44':
                        $babilonia_user_id = intval(647);
                    break;

                    case '45':
                        $babilonia_user_id = intval(663);
                    break;

                    case '46':
                        $babilonia_user_id = intval(893);
                    break;

                    case '47':
                        $babilonia_user_id = intval(892);
                    break;

                    default:
                        unset($babilonia_user_id);
                    break;
                }

                if (isset($babilonia_user_id)) {
                    $redis_user = json_decode($redis->get("data:user:" . $babilonia_user_id), true);

                    $insert_temp = $listings_scraping_temp->insertOne(
                        array(
                            "id" => strval("c21"),
                            "user_id" => intval($babilonia_user_id),
                            "email" => strval($redis_user["email"]),
                            "company_name" => null,
                            "url_origin" => strval(strtolower($url_prev)),
                            "url" => strval(strtolower($link . "?json=true")),
                            "listing_type" => null,
                            "district" => null,
                            "state" => intval(1),
                        )
                    );

                    $counter_listings++;
                }
            }

            echo "cantidad de avisos: " . $counter_listings . "\n";
        }

        for ($i = 1; $i <= 27; $i++) {
            $counter_listings = 0;

            $url_prev = strval("https://century21.pe/busqueda/tipo_casa-o-casa-de-playa-o-casa-de-campo/operacion_venta/pagina_" . $i . "?json=true");

            echo "- analyzing url: " . $url_prev . " - ";

            $curl= curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => $url_prev,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.70 Safari/537.36",
                    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng;q=0.8,application/signed-exchange;v=b3",
                    "Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7",
                ),
            ));

            $answer_ok = curl_exec($curl);
            $answer_error = curl_error($curl);
            curl_close($curl);

            $obj = json_decode($answer_ok);

            foreach ($obj->propiedades as $data) {
                $link = strval($path . $data->urlCorrecta);
                $url_listings = strval($url_prev);

                switch ($data->idAfiliados) {
                    case '1':
                        $babilonia_user_id = intval(651);
                    break;

                    case '5':
                        $babilonia_user_id = intval(655);
                    break;

                    case '6':
                        $babilonia_user_id = intval(641);
                    break;

                    case '10':
                        $babilonia_user_id = intval(557);
                    break;

                    case '11':
                        $babilonia_user_id = intval(662);
                    break;

                    case '14':
                        $babilonia_user_id = intval(657);
                    break;

                    case '15':
                        $babilonia_user_id = intval(644);
                    break;

                    case '17':
                        $babilonia_user_id = intval(652);
                    break;

                    case '19':
                        $babilonia_user_id = intval(642);
                    break;

                    case '25':
                        $babilonia_user_id = intval(660);
                    break;

                    case '26':
                        $babilonia_user_id = intval(656);
                    break;

                    case '29':
                        $babilonia_user_id = intval(654);
                    break;

                    case '31':
                        $babilonia_user_id = intval(658);
                    break;

                    case '33':
                        $babilonia_user_id = intval(648);
                    break;

                    case '34':
                        $babilonia_user_id = intval(649);
                    break;

                    case '37':
                        $babilonia_user_id = intval(653);
                    break;

                    case '38':
                        $babilonia_user_id = intval(646);
                    break;

                    case '39':
                        $babilonia_user_id = intval(645);
                    break;

                    case '40':
                        $babilonia_user_id = intval(659);
                    break;

                    case '41':
                        $babilonia_user_id = intval(650);
                    break;

                    case '42':
                        $babilonia_user_id = intval(664);
                    break;

                    case '43':
                        $babilonia_user_id = intval(4299);
                    break;

                    case '44':
                        $babilonia_user_id = intval(647);
                    break;

                    case '45':
                        $babilonia_user_id = intval(663);
                    break;

                    case '46':
                        $babilonia_user_id = intval(893);
                    break;

                    case '47':
                        $babilonia_user_id = intval(892);
                    break;

                    default:
                        unset($babilonia_user_id);
                    break;
                }

                if (isset($babilonia_user_id)) {
                    $redis_user = json_decode($redis->get("data:user:" . $babilonia_user_id), true);

                    $insert_temp = $listings_scraping_temp->insertOne(
                        array(
                            "id" => strval("c21"),
                            "user_id" => intval($babilonia_user_id),
                            "email" => strval($redis_user["email"]),
                            "company_name" => null,
                            "url_origin" => strval(strtolower($url_prev)),
                            "url" => strval(strtolower($link . "?json=true")),
                            "listing_type" => null,
                            "district" => null,
                            "state" => intval(1),
                        )
                    );

                    $counter_listings++;
                }
            }

            echo "cantidad de avisos: " . $counter_listings . "\n";
        }

        for ($i = 1; $i <= 51; $i++) {
            $counter_listings = 0;

            $url_prev = strval("https://century21.pe/busqueda/tipo_departamento-en-proyecto-o-local-o-local-industrial-o-oficinas-o-terreno-agricola-o-terreno-comercial-o-terreno-industrial-o-terreno-residencial/operacion_venta/pagina_" . $i . "?json=true");

            echo "- analyzing url: " . $url_prev . " - ";

            $curl= curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => $url_prev,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.70 Safari/537.36",
                    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng;q=0.8,application/signed-exchange;v=b3",
                    "Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7",
                ),
            ));

            $answer_ok = curl_exec($curl);
            $answer_error = curl_error($curl);
            curl_close($curl);

            $obj = json_decode($answer_ok);

            foreach ($obj->propiedades as $data) {
                $link = strval($path . $data->urlCorrecta);
                $url_listings = strval($url_prev);

                switch ($data->idAfiliados) {
                    case '1':
                        $babilonia_user_id = intval(651);
                    break;

                    case '5':
                        $babilonia_user_id = intval(655);
                    break;

                    case '6':
                        $babilonia_user_id = intval(641);
                    break;

                    case '10':
                        $babilonia_user_id = intval(557);
                    break;

                    case '11':
                        $babilonia_user_id = intval(662);
                    break;

                    case '14':
                        $babilonia_user_id = intval(657);
                    break;

                    case '15':
                        $babilonia_user_id = intval(644);
                    break;

                    case '17':
                        $babilonia_user_id = intval(652);
                    break;

                    case '19':
                        $babilonia_user_id = intval(642);
                    break;

                    case '25':
                        $babilonia_user_id = intval(660);
                    break;

                    case '26':
                        $babilonia_user_id = intval(656);
                    break;

                    case '29':
                        $babilonia_user_id = intval(654);
                    break;

                    case '31':
                        $babilonia_user_id = intval(658);
                    break;

                    case '33':
                        $babilonia_user_id = intval(648);
                    break;

                    case '34':
                        $babilonia_user_id = intval(649);
                    break;

                    case '37':
                        $babilonia_user_id = intval(653);
                    break;

                    case '38':
                        $babilonia_user_id = intval(646);
                    break;

                    case '39':
                        $babilonia_user_id = intval(645);
                    break;

                    case '40':
                        $babilonia_user_id = intval(659);
                    break;

                    case '41':
                        $babilonia_user_id = intval(650);
                    break;

                    case '42':
                        $babilonia_user_id = intval(664);
                    break;

                    case '43':
                        $babilonia_user_id = intval(4299);
                    break;

                    case '44':
                        $babilonia_user_id = intval(647);
                    break;

                    case '45':
                        $babilonia_user_id = intval(663);
                    break;

                    case '46':
                        $babilonia_user_id = intval(893);
                    break;

                    case '47':
                        $babilonia_user_id = intval(892);
                    break;

                    default:
                        unset($babilonia_user_id);
                    break;
                }

                if (isset($babilonia_user_id)) {
                    $redis_user = json_decode($redis->get("data:user:" . $babilonia_user_id), true);

                    $insert_temp = $listings_scraping_temp->insertOne(
                        array(
                            "id" => strval("c21"),
                            "user_id" => intval($babilonia_user_id),
                            "email" => strval($redis_user["email"]),
                            "company_name" => null,
                            "url_origin" => strval(strtolower($url_prev)),
                            "url" => strval(strtolower($link . "?json=true")),
                            "listing_type" => null,
                            "district" => null,
                            "state" => intval(1),
                        )
                    );

                    $counter_listings++;
                }
            }

            echo "cantidad de avisos: " . $counter_listings . "\n";
        }

        for ($i = 1; $i <= 9; $i++) {
            $counter_listings = 0;

            $url_prev = strval("https://century21.pe/busqueda/tipo_departamento/operacion_venta/recamaras_4-5/pagina_" . $i . "?json=true");

            echo "- analyzing url: " . $url_prev . " - ";

            $curl= curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => $url_prev,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.70 Safari/537.36",
                    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng;q=0.8,application/signed-exchange;v=b3",
                    "Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7",
                ),
            ));

            $answer_ok = curl_exec($curl);
            $answer_error = curl_error($curl);
            curl_close($curl);

            $obj = json_decode($answer_ok);

            foreach ($obj->propiedades as $data) {
                $link = strval($path . $data->urlCorrecta);
                $url_listings = strval($url_prev);

                switch ($data->idAfiliados) {
                    case '1':
                        $babilonia_user_id = intval(651);
                    break;

                    case '5':
                        $babilonia_user_id = intval(655);
                    break;

                    case '6':
                        $babilonia_user_id = intval(641);
                    break;

                    case '10':
                        $babilonia_user_id = intval(557);
                    break;

                    case '11':
                        $babilonia_user_id = intval(662);
                    break;

                    case '14':
                        $babilonia_user_id = intval(657);
                    break;

                    case '15':
                        $babilonia_user_id = intval(644);
                    break;

                    case '17':
                        $babilonia_user_id = intval(652);
                    break;

                    case '19':
                        $babilonia_user_id = intval(642);
                    break;

                    case '25':
                        $babilonia_user_id = intval(660);
                    break;

                    case '26':
                        $babilonia_user_id = intval(656);
                    break;

                    case '29':
                        $babilonia_user_id = intval(654);
                    break;

                    case '31':
                        $babilonia_user_id = intval(658);
                    break;

                    case '33':
                        $babilonia_user_id = intval(648);
                    break;

                    case '34':
                        $babilonia_user_id = intval(649);
                    break;

                    case '37':
                        $babilonia_user_id = intval(653);
                    break;

                    case '38':
                        $babilonia_user_id = intval(646);
                    break;

                    case '39':
                        $babilonia_user_id = intval(645);
                    break;

                    case '40':
                        $babilonia_user_id = intval(659);
                    break;

                    case '41':
                        $babilonia_user_id = intval(650);
                    break;

                    case '42':
                        $babilonia_user_id = intval(664);
                    break;

                    case '43':
                        $babilonia_user_id = intval(4299);
                    break;

                    case '44':
                        $babilonia_user_id = intval(647);
                    break;

                    case '45':
                        $babilonia_user_id = intval(663);
                    break;

                    case '46':
                        $babilonia_user_id = intval(893);
                    break;

                    case '47':
                        $babilonia_user_id = intval(892);
                    break;

                    default:
                        unset($babilonia_user_id);
                    break;
                }

                if (isset($babilonia_user_id)) {
                    $redis_user = json_decode($redis->get("data:user:" . $babilonia_user_id), true);

                    $insert_temp = $listings_scraping_temp->insertOne(
                        array(
                            "id" => strval("c21"),
                            "user_id" => intval($babilonia_user_id),
                            "email" => strval($redis_user["email"]),
                            "company_name" => null,
                            "url_origin" => strval(strtolower($url_prev)),
                            "url" => strval(strtolower($link . "?json=true")),
                            "listing_type" => null,
                            "district" => null,
                            "state" => intval(1),
                        )
                    );

                    $counter_listings++;
                }
            }

            echo "cantidad de avisos: " . $counter_listings . "\n";
        }

        ##########################################################################################
        #ALQUILER
        ##########################################################################################
        for ($i = 1; $i <= 100; $i++) {
            $counter_listings = 0;

            $url_prev = strval("https://century21.pe/busqueda/tipo_departamento/operacion_renta/recamaras_1-2-3/pagina_" . $i . "?json=true");

            echo "- analyzing url: " . $url_prev . " - ";

            $curl= curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => $url_prev,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.70 Safari/537.36",
                    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng;q=0.8,application/signed-exchange;v=b3",
                    "Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7",
                ),
            ));

            $answer_ok = curl_exec($curl);
            $answer_error = curl_error($curl);
            curl_close($curl);

            $obj = json_decode($answer_ok);

            foreach ($obj->propiedades as $data) {
                $link = strval($path . $data->urlCorrecta);
                $url_listings = strval($url_prev);

                switch ($data->idAfiliados) {
                    case '1':
                        $babilonia_user_id = intval(651);
                    break;

                    case '5':
                        $babilonia_user_id = intval(655);
                    break;

                    case '6':
                        $babilonia_user_id = intval(641);
                    break;

                    case '10':
                        $babilonia_user_id = intval(557);
                    break;

                    case '11':
                        $babilonia_user_id = intval(662);
                    break;

                    case '14':
                        $babilonia_user_id = intval(657);
                    break;

                    case '15':
                        $babilonia_user_id = intval(644);
                    break;

                    case '17':
                        $babilonia_user_id = intval(652);
                    break;

                    case '19':
                        $babilonia_user_id = intval(642);
                    break;

                    case '25':
                        $babilonia_user_id = intval(660);
                    break;

                    case '26':
                        $babilonia_user_id = intval(656);
                    break;

                    case '29':
                        $babilonia_user_id = intval(654);
                    break;

                    case '31':
                        $babilonia_user_id = intval(658);
                    break;

                    case '33':
                        $babilonia_user_id = intval(648);
                    break;

                    case '34':
                        $babilonia_user_id = intval(649);
                    break;

                    case '37':
                        $babilonia_user_id = intval(653);
                    break;

                    case '38':
                        $babilonia_user_id = intval(646);
                    break;

                    case '39':
                        $babilonia_user_id = intval(645);
                    break;

                    case '40':
                        $babilonia_user_id = intval(659);
                    break;

                    case '41':
                        $babilonia_user_id = intval(650);
                    break;

                    case '42':
                        $babilonia_user_id = intval(664);
                    break;

                    case '43':
                        $babilonia_user_id = intval(4299);
                    break;

                    case '44':
                        $babilonia_user_id = intval(647);
                    break;

                    case '45':
                        $babilonia_user_id = intval(663);
                    break;

                    case '46':
                        $babilonia_user_id = intval(893);
                    break;

                    case '47':
                        $babilonia_user_id = intval(892);
                    break;

                    default:
                        unset($babilonia_user_id);
                    break;
                }

                if (isset($babilonia_user_id)) {
                    $redis_user = json_decode($redis->get("data:user:" . $babilonia_user_id), true);

                    $insert_temp = $listings_scraping_temp->insertOne(
                        array(
                            "id" => strval("c21"),
                            "user_id" => intval($babilonia_user_id),
                            "email" => strval($redis_user["email"]),
                            "company_name" => null,
                            "url_origin" => strval(strtolower($url_prev)),
                            "url" => strval(strtolower($link . "?json=true")),
                            "listing_type" => null,
                            "district" => null,
                            "state" => intval(1),
                        )
                    );

                    $counter_listings++;
                }
            }

            echo "cantidad de avisos: " . $counter_listings . "\n";
        }

        for ($i = 1; $i <= 27; $i++) {
            $counter_listings = 0;

            $url_prev = strval("https://century21.pe/busqueda/tipo_casa-o-casa-de-playa-o-casa-de-campo/operacion_renta/pagina_" . $i . "?json=true");

            echo "- analyzing url: " . $url_prev . " - ";

            $curl= curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => $url_prev,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.70 Safari/537.36",
                    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng;q=0.8,application/signed-exchange;v=b3",
                    "Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7",
                ),
            ));

            $answer_ok = curl_exec($curl);
            $answer_error = curl_error($curl);
            curl_close($curl);

            $obj = json_decode($answer_ok);

            foreach ($obj->propiedades as $data) {
                $link = strval($path . $data->urlCorrecta);
                $url_listings = strval($url_prev);

                switch ($data->idAfiliados) {
                    case '1':
                        $babilonia_user_id = intval(651);
                    break;

                    case '5':
                        $babilonia_user_id = intval(655);
                    break;

                    case '6':
                        $babilonia_user_id = intval(641);
                    break;

                    case '10':
                        $babilonia_user_id = intval(557);
                    break;

                    case '11':
                        $babilonia_user_id = intval(662);
                    break;

                    case '14':
                        $babilonia_user_id = intval(657);
                    break;

                    case '15':
                        $babilonia_user_id = intval(644);
                    break;

                    case '17':
                        $babilonia_user_id = intval(652);
                    break;

                    case '19':
                        $babilonia_user_id = intval(642);
                    break;

                    case '25':
                        $babilonia_user_id = intval(660);
                    break;

                    case '26':
                        $babilonia_user_id = intval(656);
                    break;

                    case '29':
                        $babilonia_user_id = intval(654);
                    break;

                    case '31':
                        $babilonia_user_id = intval(658);
                    break;

                    case '33':
                        $babilonia_user_id = intval(648);
                    break;

                    case '34':
                        $babilonia_user_id = intval(649);
                    break;

                    case '37':
                        $babilonia_user_id = intval(653);
                    break;

                    case '38':
                        $babilonia_user_id = intval(646);
                    break;

                    case '39':
                        $babilonia_user_id = intval(645);
                    break;

                    case '40':
                        $babilonia_user_id = intval(659);
                    break;

                    case '41':
                        $babilonia_user_id = intval(650);
                    break;

                    case '42':
                        $babilonia_user_id = intval(664);
                    break;

                    case '43':
                        $babilonia_user_id = intval(4299);
                    break;

                    case '44':
                        $babilonia_user_id = intval(647);
                    break;

                    case '45':
                        $babilonia_user_id = intval(663);
                    break;

                    case '46':
                        $babilonia_user_id = intval(893);
                    break;

                    case '47':
                        $babilonia_user_id = intval(892);
                    break;

                    default:
                        unset($babilonia_user_id);
                    break;
                }

                if (isset($babilonia_user_id)) {
                    $redis_user = json_decode($redis->get("data:user:" . $babilonia_user_id), true);

                    $insert_temp = $listings_scraping_temp->insertOne(
                        array(
                            "id" => strval("c21"),
                            "user_id" => intval($babilonia_user_id),
                            "email" => strval($redis_user["email"]),
                            "company_name" => null,
                            "url_origin" => strval(strtolower($url_prev)),
                            "url" => strval(strtolower($link . "?json=true")),
                            "listing_type" => null,
                            "district" => null,
                            "state" => intval(1),
                        )
                    );

                    $counter_listings++;
                }
            }

            echo "cantidad de avisos: " . $counter_listings . "\n";
        }

        for ($i = 1; $i <= 51; $i++) {
            $counter_listings = 0;

            $url_prev = strval("https://century21.pe/busqueda/tipo_departamento-en-proyecto-o-local-o-local-industrial-o-oficinas-o-terreno-agricola-o-terreno-comercial-o-terreno-industrial-o-terreno-residencial/operacion_renta/pagina_" . $i . "?json=true");

            echo "- analyzing url: " . $url_prev . " - ";

            $curl= curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => $url_prev,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.70 Safari/537.36",
                    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng;q=0.8,application/signed-exchange;v=b3",
                    "Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7",
                ),
            ));

            $answer_ok = curl_exec($curl);
            $answer_error = curl_error($curl);
            curl_close($curl);

            $obj = json_decode($answer_ok);

            foreach ($obj->propiedades as $data) {
                $link = strval($path . $data->urlCorrecta);
                $url_listings = strval($url_prev);

                switch ($data->idAfiliados) {
                    case '1':
                        $babilonia_user_id = intval(651);
                    break;

                    case '5':
                        $babilonia_user_id = intval(655);
                    break;

                    case '6':
                        $babilonia_user_id = intval(641);
                    break;

                    case '10':
                        $babilonia_user_id = intval(557);
                    break;

                    case '11':
                        $babilonia_user_id = intval(662);
                    break;

                    case '14':
                        $babilonia_user_id = intval(657);
                    break;

                    case '15':
                        $babilonia_user_id = intval(644);
                    break;

                    case '17':
                        $babilonia_user_id = intval(652);
                    break;

                    case '19':
                        $babilonia_user_id = intval(642);
                    break;

                    case '25':
                        $babilonia_user_id = intval(660);
                    break;

                    case '26':
                        $babilonia_user_id = intval(656);
                    break;

                    case '29':
                        $babilonia_user_id = intval(654);
                    break;

                    case '31':
                        $babilonia_user_id = intval(658);
                    break;

                    case '33':
                        $babilonia_user_id = intval(648);
                    break;

                    case '34':
                        $babilonia_user_id = intval(649);
                    break;

                    case '37':
                        $babilonia_user_id = intval(653);
                    break;

                    case '38':
                        $babilonia_user_id = intval(646);
                    break;

                    case '39':
                        $babilonia_user_id = intval(645);
                    break;

                    case '40':
                        $babilonia_user_id = intval(659);
                    break;

                    case '41':
                        $babilonia_user_id = intval(650);
                    break;

                    case '42':
                        $babilonia_user_id = intval(664);
                    break;

                    case '43':
                        $babilonia_user_id = intval(4299);
                    break;

                    case '44':
                        $babilonia_user_id = intval(647);
                    break;

                    case '45':
                        $babilonia_user_id = intval(663);
                    break;

                    case '46':
                        $babilonia_user_id = intval(893);
                    break;

                    case '47':
                        $babilonia_user_id = intval(892);
                    break;

                    default:
                        unset($babilonia_user_id);
                    break;
                }

                if (isset($babilonia_user_id)) {
                    $redis_user = json_decode($redis->get("data:user:" . $babilonia_user_id), true);

                    $insert_temp = $listings_scraping_temp->insertOne(
                        array(
                            "id" => strval("c21"),
                            "user_id" => intval($babilonia_user_id),
                            "email" => strval($redis_user["email"]),
                            "company_name" => null,
                            "url_origin" => strval(strtolower($url_prev)),
                            "url" => strval(strtolower($link . "?json=true")),
                            "listing_type" => null,
                            "district" => null,
                            "state" => intval(1),
                        )
                    );

                    $counter_listings++;
                }
            }

            echo "cantidad de avisos: " . $counter_listings . "\n";
        }

        for ($i = 1; $i <= 9; $i++) {
            $counter_listings = 0;

            $url_prev = strval("https://century21.pe/busqueda/tipo_departamento/operacion_renta/recamaras_4-5/pagina_" . $i . "?json=true");

            echo "- analyzing url: " . $url_prev . " - ";

            $curl= curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => $url_prev,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.70 Safari/537.36",
                    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng;q=0.8,application/signed-exchange;v=b3",
                    "Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7",
                ),
            ));

            $answer_ok = curl_exec($curl);
            $answer_error = curl_error($curl);
            curl_close($curl);

            $obj = json_decode($answer_ok);

            foreach ($obj->propiedades as $data) {
                $link = strval($path . $data->urlCorrecta);
                $url_listings = strval($url_prev);

                switch ($data->idAfiliados) {
                    case '1':
                        $babilonia_user_id = intval(651);
                    break;

                    case '5':
                        $babilonia_user_id = intval(655);
                    break;

                    case '6':
                        $babilonia_user_id = intval(641);
                    break;

                    case '10':
                        $babilonia_user_id = intval(557);
                    break;

                    case '11':
                        $babilonia_user_id = intval(662);
                    break;

                    case '14':
                        $babilonia_user_id = intval(657);
                    break;

                    case '15':
                        $babilonia_user_id = intval(644);
                    break;

                    case '17':
                        $babilonia_user_id = intval(652);
                    break;

                    case '19':
                        $babilonia_user_id = intval(642);
                    break;

                    case '25':
                        $babilonia_user_id = intval(660);
                    break;

                    case '26':
                        $babilonia_user_id = intval(656);
                    break;

                    case '29':
                        $babilonia_user_id = intval(654);
                    break;

                    case '31':
                        $babilonia_user_id = intval(658);
                    break;

                    case '33':
                        $babilonia_user_id = intval(648);
                    break;

                    case '34':
                        $babilonia_user_id = intval(649);
                    break;

                    case '37':
                        $babilonia_user_id = intval(653);
                    break;

                    case '38':
                        $babilonia_user_id = intval(646);
                    break;

                    case '39':
                        $babilonia_user_id = intval(645);
                    break;

                    case '40':
                        $babilonia_user_id = intval(659);
                    break;

                    case '41':
                        $babilonia_user_id = intval(650);
                    break;

                    case '42':
                        $babilonia_user_id = intval(664);
                    break;

                    case '43':
                        $babilonia_user_id = intval(4299);
                    break;

                    case '44':
                        $babilonia_user_id = intval(647);
                    break;

                    case '45':
                        $babilonia_user_id = intval(663);
                    break;

                    case '46':
                        $babilonia_user_id = intval(893);
                    break;

                    case '47':
                        $babilonia_user_id = intval(892);
                    break;

                    default:
                        unset($babilonia_user_id);
                    break;
                }

                if (isset($babilonia_user_id)) {
                    $redis_user = json_decode($redis->get("data:user:" . $babilonia_user_id), true);

                    $insert_temp = $listings_scraping_temp->insertOne(
                        array(
                            "id" => strval("c21"),
                            "user_id" => intval($babilonia_user_id),
                            "email" => strval($redis_user["email"]),
                            "company_name" => null,
                            "url_origin" => strval(strtolower($url_prev)),
                            "url" => strval(strtolower($link . "?json=true")),
                            "listing_type" => null,
                            "district" => null,
                            "state" => intval(1),
                        )
                    );

                    $counter_listings++;
                }
            }

            echo "cantidad de avisos: " . $counter_listings . "\n";
        }

        foreach ($array_offices_c21 as $office_c21) {
            $counter_users = $users->count(
                array(
                    'id' => intval($office_c21),
                ),
            );

            if ($counter_users > 0) {
                $select_users = $users->find(
                    array(
                        'id' => intval($office_c21),
                    ),
                    array(),
                    array(
                        'email' => intval(1),
                    ),
                );

                foreach ($select_users as $user) {
                    $babilonia_user_email = strval($user["email"]);

                    print_r("
                        ##########################################################################################\n
                        INICIA " . $office_c21 . "\n
                        ##########################################################################################\n"
                    );

                    ##########################################################################################
                    #GETTING PROVIDER DATA
                    ##########################################################################################
                    $user_email = ($MASTER_ENVIRONMENT == "prod" ? strval($babilonia_user_email) : strval("julio.lopez@babilonia.io"));

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
                                    "type" => strval("c21"),
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
                                        "type" => strval("c21"),
                                    ))
                                )
                            );

                            echo json_encode($error);
                            exit();
                        }

                        echo $user_id . " - " . $authorization . "\n\n";

                        $array_provider = array();
                        $array_babilonia = array();
                        $answer_error_delete_counter = 0;
                        $answer_error_create_counter = 0;
                        $answer_error_create_external_listing_id_counter = 0;
                        $answer_error_update_counter = 0;

                        ##########################################################################################
                        #GETTING PROVIDER DATA
                        ##########################################################################################
                        $select_listings_provider = $listings_scraping_temp->find(
                            array(
                                '$and' => array(
                                    array('id' => strval("c21")),
                                    array('user_id' => intval($user_id))
                                ),
                            ),
                            array(
                                'sort' => array(
                                    '_id' => floatval(-1),
                                ),
                            ),
                        );

                        foreach ($select_listings_provider as $listing_temp) {
                            array_push($array_provider, strtolower($listing_temp["url"]));
                        }

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

                        $counter_users_packages = $users_packages->count(
                            array('$and' => array(
                                array('user_id' => intval($user_id)),
                                array('expires_at' => array(
                                    '$gte' => new MongoDB\BSON\UTCDateTime($current_date->format('U') * 1000),
                                )),
                            ))
                        );

                        if ($counter_users_packages >= 1) {
                            $select_users_packages = $users_packages->find(
                                array('$and' => array(
                                    array('user_id' => intval($user_id)),
                                    array('expires_at' => array(
                                        '$gte' => new MongoDB\BSON\UTCDateTime($current_date->format('U') * 1000),
                                    )),
                                ))
                            );

                            foreach ($select_users_packages as $user_package) {
                                $package_id = intval($user_package["id"]);
                            }
                        }
                        else {
                            unset($package_id);
                        }

                        ##########################################################################################
                        #DELETING ADS
                        ##########################################################################################
                        $result_delete = array_diff($array_babilonia, $array_provider);
                        $result_delete = array_unique($result_delete);
                        $counter_delete = count($result_delete);
                        $c_delete = $counter_delete;

                        sort($result_delete);

                        if(count($result_delete) >= 1) {
                            foreach ($result_delete as $link) {
                                echo $link . "\n";

                                $select_listings_babilonia_delete = $listings->find(
                                    array('$and' => array(
                                        array('user_id' => intval($user_id)),
                                        array('external_data.url' => strval($link))
                                    ))
                                );

                                foreach ($select_listings_babilonia_delete as $listing_delete) {
                                    $array_listing_id = array(
                                        strval($listing_delete["id"]),
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
                                                    "type" => strval("c21"),
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

                        ##########################################################################################
                        #CREATING ADS
                        ##########################################################################################
                        $array_external_listing_id_already_exist = array();

                        $result_create = array_diff($array_provider, $array_babilonia);
                        #$result_create = array("https://century21.pe/propiedad/31886_departamento-en-venta-en-miraflores-lima-lima-peru");
                        $counter_create = count($result_create);
                        $c_create = $counter_create;

                        sort($result_create);

                        if (count($result_create) >= 1) {
                            if (isset($package_id)) {
                                foreach ($result_create as $link) {
                                    echo $link . "\n";

                                    $array_images = array();
                                    $array_videos = array();
                                    $array_360 = array();
                                    $array_facilities = array();

                                    $curl= curl_init();
                                    curl_setopt_array($curl, array(
                                        CURLOPT_URL => $link,
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_CUSTOMREQUEST => "GET",
                                        CURLOPT_HTTPHEADER => array(
                                            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.70 Safari/537.36",
                                            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng;q=0.8,application/signed-exchange;v=b3",
                                            "Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7",
                                        ),
                                    ));

                                    $answer_ok = curl_exec($curl);
                                    $answer_error = curl_error($curl);
                                    curl_close($curl);

                                    $json = json_decode($answer_ok);

                                    $id_listing_external = intval($json->entity->id);

                                    switch ($json->entity->tipoOperacion) {
                                        case 'venta':
                                            $listing_type = strval("sale");
                                        break;

                                        case 'renta':
                                            $listing_type = strval("rent");
                                        break;

                                        default:
                                            $listing_type = strval($json->entity->tipoOperacion);
                                        break;
                                    }

                                    switch ($json->entity->subtipoPropiedad) {
                                        case 'casa':
                                        case 'casa-de-campo':
                                        case 'casa-de-playa':
                                            $property_type = strval("house");
                                        break;

                                        case 'departamento':
                                        case 'departamento-duplex':
                                        case 'departamento-flat':
                                        case 'departamento-triplex':
                                            $property_type = strval("apartment");
                                        break;

                                        case 'local':
                                            $property_type = strval("commercial");
                                        break;

                                        case 'local-industrial':
                                            $property_type = strval("local_industrial");
                                        break;

                                        case 'oficinas':
                                            $property_type = strval("office");
                                        break;

                                        case 'terreno-residencial':
                                            $property_type = strval("land");
                                        break;

                                        case 'terreno-agricola':
                                            $property_type = strval("land_agricultural");
                                        break;

                                        case 'terreno-industrial':
                                            $property_type = strval("land_industrial");
                                        break;

                                        case 'terreno-comercial':
                                            $property_type = strval("land_commercial");
                                        break;

                                        default:
                                            $property_type = strval($json->entity->subtipoPropiedad);
                                        break;
                                    }

                                    $address = strval($json->entity->calle . " " . $json->entity->numero);
                                    $district = strval($json->entity->colonia);
                                    $province = strval($json->entity->municipio);
                                    $department = strval($json->entity->estado);
                                    $country = strval($json->entity->pais);
                                    $latitude = floatval($json->entity->lat);
                                    $longitude = floatval($json->entity->lon);
                                    $description = strval($json->entity->descripcion);
                                    $area = ($json->entity->m2T <> null ? strval($json->entity->m2T) : strval($json->entity->m2C));
                                    $built_area = ($json->entity->m2C <> null ? strval($json->entity->m2C) : null);
                                    $bathrooms_count = intval($json->entity->banios);
                                    $bedrooms_count = intval($json->entity->recamaras);
                                    $parking_slots_count = intval($json->entity->estacionamientos);
                                    $floor_number = intval($json->entity->pisoEnQueSeEncuentra);
                                    $total_floors_count = intval($json->entity->nivelesConstruidos);
                                    $year_of_construction = intval($json->entity->edad);
                                    $price = intval($json->entity->precioSecundario);

                                    if (str_contains($json->entity->usuatelMovil, "+51")) {
                                        $contact_prefix = strval("51");
                                        $contact_phone = str_replace("+51", "", $json->entity->usuatelMovil);
                                    }
                                    else {
                                        $contact_prefix = strval("51");
                                        $contact_phone = strval($json->entity->usuatelMovil);
                                    }

                                    $array_contacts = array(
                                        array(
                                            "name" => $json->entity->usname . " " . $json->entity->apellidoP . " " . $json->entity->apellidoM,
                                            "email" => $json->entity->usuaemail,
                                            "prefix" => $contact_prefix,
                                            "phone" => $contact_phone,
                                        ),
                                    );

                                    if ($json->entity->noMascotas == true) {
                                        $pet_friendly = strval("true");
                                    }
                                    else {
                                        $pet_friendly = null;
                                    }

                                    if ($json->entity->alberca == true) {
                                        array_push($array_facilities, "piscina");
                                    }

                                    if ($json->entity->numeroElevadores != null) {
                                        array_push($array_facilities, "elevator");
                                    }

                                    foreach ($json->fotos as $image) {
                                        array_push($array_images, $image->large3);
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

                                    $message_create->package_id = intval($package_id);

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
                                                    "type" => strval("c21"),
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
                            else {
                                $response = array (
                                    "data" => array (
                                        "status" => "created_skkiped",
                                        "execution_time" => number_format(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 2, ".", "") . " sec",
                                        "desc" => "no package id"
                                    )
                                );

                                echo json_encode($response) . "\n";
                                echo date("Y-m-d H:i:s") . "\n";
                                echo "Remains " . $c_create-- . " of " . $counter_create . " creations\n\n";
                            }
                        }

                        ##########################################################################################
                        #UPDATING ADS
                        ##########################################################################################
                        $result_update = array_intersect($array_provider, $array_babilonia);

                        foreach ($array_external_listing_id_already_exist as $external_listing_id_already_exist) {
                            array_push($result_update, $external_listing_id_already_exist);
                        }

                        $counter_update = count($result_update);
                        $c_update = $counter_update;

                        sort($result_update);

                        if (count($result_update) >= 1) {
                            if (isset($package_id)) {
                                foreach ($result_update as $link) {
                                    echo $link . "\n";

                                    $array_images = array();
                                    $array_videos = array();
                                    $array_360 = array();
                                    $array_facilities = array();

                                    $curl= curl_init();
                                    curl_setopt_array($curl, array(
                                        CURLOPT_URL => $link,
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_CUSTOMREQUEST => "GET",
                                        CURLOPT_HTTPHEADER => array(
                                            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.70 Safari/537.36",
                                            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng;q=0.8,application/signed-exchange;v=b3",
                                            "Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7",
                                        ),
                                    ));

                                    $answer_ok = curl_exec($curl);
                                    $answer_error = curl_error($curl);
                                    curl_close($curl);

                                    $json = json_decode($answer_ok);

                                    foreach (json_decode($json->entity->caracteristicasJSON) as $facilities) {
                                        foreach ($facilities as $facility) {
                                            if ($facility->valor == true) {
                                                array_push($array_facilities, $facility->label);
                                            }
                                        }
                                    }

                                    $id_listing_external = intval($json->entity->id);

                                    switch ($json->entity->tipoOperacion) {
                                        case 'venta':
                                            $listing_type = strval("sale");
                                        break;

                                        case 'renta':
                                            $listing_type = strval("rent");
                                        break;

                                        default:
                                            $listing_type = strval($json->entity->tipoOperacion);
                                        break;
                                    }

                                    switch ($json->entity->subtipoPropiedad) {
                                        case 'casa':
                                        case 'casa-de-campo':
                                        case 'casa-de-playa':
                                            $property_type = strval("house");
                                        break;

                                        case 'departamento':
                                        case 'departamento-duplex':
                                        case 'departamento-flat':
                                        case 'departamento-triplex':
                                            $property_type = strval("apartment");
                                        break;

                                        case 'local':
                                            $property_type = strval("commercial");
                                        break;

                                        case 'local-industrial':
                                            $property_type = strval("local_industrial");
                                        break;

                                        case 'oficinas':
                                            $property_type = strval("office");
                                        break;

                                        case 'terreno-residencial':
                                            $property_type = strval("land");
                                        break;

                                        case 'terreno-agricola':
                                            $property_type = strval("land_agricultural");
                                        break;

                                        case 'terreno-industrial':
                                            $property_type = strval("land_industrial");
                                        break;

                                        case 'terreno-comercial':
                                            $property_type = strval("land_commercial");
                                        break;

                                        default:
                                            $property_type = strval($json->entity->subtipoPropiedad);
                                        break;
                                    }

                                    $address = strval($json->entity->calle . " " . $json->entity->numero);
                                    $district = strval($json->entity->colonia);
                                    $province = strval($json->entity->municipio);
                                    $department = strval($json->entity->estado);
                                    $country = strval($json->entity->pais);
                                    $latitude = floatval($json->entity->lat);
                                    $longitude = floatval($json->entity->lon);
                                    $description = strval($json->entity->descripcion);
                                    $area = ($json->entity->m2T <> null ? strval($json->entity->m2T) : strval($json->entity->m2C));
                                    $built_area = ($json->entity->m2C <> null ? strval($json->entity->m2C) : null);
                                    $bathrooms_count = intval($json->entity->banios);
                                    $bedrooms_count = intval($json->entity->recamaras);
                                    $parking_slots_count = intval($json->entity->estacionamientos);
                                    $floor_number = intval($json->entity->pisoEnQueSeEncuentra);
                                    $total_floors_count = intval($json->entity->nivelesConstruidos);
                                    $year_of_construction = intval($json->entity->edad);
                                    $price = intval($json->entity->precioSecundario);

                                    if (str_contains($json->entity->usuatelMovil, "+51")) {
                                        $contact_prefix = strval("51");
                                        $contact_phone = str_replace("+51", "", $json->entity->usuatelMovil);
                                    }
                                    else {
                                        $contact_prefix = strval("51");
                                        $contact_phone = strval($json->entity->usuatelMovil);
                                    }

                                    $array_contacts = array(
                                        array(
                                            "name" => $json->entity->usname . " " . $json->entity->apellidoP . " " . $json->entity->apellidoM,
                                            "email" => $json->entity->usuaemail,
                                            "prefix" => $contact_prefix,
                                            "phone" => $contact_phone,
                                        ),
                                    );

                                    if ($json->entity->noMascotas == true) {
                                        $pet_friendly = strval("true");
                                    }
                                    else {
                                        $pet_friendly = null;
                                    }

                                    if ($json->entity->alberca == true) {
                                        array_push($array_facilities, "piscina");
                                    }

                                    if ($json->entity->numeroElevadores != null) {
                                        array_push($array_facilities, "elevator");
                                    }

                                    foreach ($json->fotos as $image) {
                                        array_push($array_images, $image->large3);
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

                                    $message_update->package_id = intval($package_id);

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
                                        strval($id_listing_external),
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
                                                    "type" => strval("c21"),
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
                            else {
                                $response = array (
                                    "data" => array (
                                        "status" => "update_skkiped",
                                        "execution_time" => number_format(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 2, ".", "") . " sec",
                                        "desc" => "no package id"
                                    )
                                );

                                echo json_encode($response) . "\n";
                                echo date("Y-m-d H:i:s") . "\n";
                                echo "Remains " . $c_update-- . " of " . $counter_update . " updates\n\n";
                            }
                        }
                    }
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
                        &nbsp;&nbsp;&nbsp;* Errores del proceso: " . $answer_error_delete_counter . "<br>
                        - Avisos creados: " . count($result_create) . "<br>
                        &nbsp;&nbsp;&nbsp;* Errores del proceso: " . $answer_error_create_counter . "<br>
                        &nbsp;&nbsp;&nbsp;* Errores de external ID: " . $answer_error_create_external_listing_id_counter . "<br>
                        - Avisos actualizados: " . count($result_update) . "<br>
                        &nbsp;&nbsp;&nbsp;* Errores del proceso: " . $answer_error_update_counter . "<br>
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
