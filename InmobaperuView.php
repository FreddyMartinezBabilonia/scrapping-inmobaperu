<?php
    ##########################################################################################
    #EXTRAER PAGINA MAX POR VISTA DE PROPIEDAD VISITADA
    ##########################################################################################
    $browser = $client->request('GET', $path . "/$viewPage/");
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
            $listing = $client->request('GET', $path . "/$viewPage/");
        }
        else {
            $listing = $client->request('GET', $path . "/$viewPage/?current_page=" . $page );
        }
        
        if (
            $listing
                ->filter(".ulisting-item-grid")
                ->count() == 0
        ) {
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

                            if(!isset($data["data"]["status"]) || $data["data"]["status"]!=404){
                                $_status = strval($data["status"] ?? 'unpublish');
                                
                                if($_status == 'publish'){
                                    array_push($array_provider, strval($link));
                                }
                            }

                            unset($_status);
                        } catch (Exception $e) {
                            print_r($e->getMessage());
                        }
                    }
                });
        }
    }

    $array_provider = array_unique($array_provider);

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

                    $message_create->location_attributes = new stdClass();
                    $message_create->location_attributes->country = $inmueble->getCountry();
                    $message_create->location_attributes->department = $inmueble->getDepartment();
                    $message_create->location_attributes->province = $inmueble->getProvince();
                    $message_create->location_attributes->district = $inmueble->getDistrict();
                    $message_create->location_attributes->address = $inmueble->getAddress();
                    $message_create->location_attributes->address_alternative = $inmueble->getAddressAlternative();
                    $message_create->location_attributes->latitude = $inmueble->getLatitude();
                    $message_create->location_attributes->longitude = $inmueble->getLongitude();

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

                    $message_update->location_attributes = new stdClass();
                    $message_update->location_attributes->country = $inmueble->getCountry();
                    $message_update->location_attributes->department = $inmueble->getDepartment();
                    $message_update->location_attributes->province = $inmueble->getProvince();
                    $message_update->location_attributes->district = $inmueble->getDistrict();
                    $message_update->location_attributes->address = $inmueble->getAddress();
                    $message_update->location_attributes->address_alternative = $inmueble->getAddressAlternative();
                    $message_update->location_attributes->latitude = $inmueble->getLatitude();
                    $message_update->location_attributes->longitude = $inmueble->getLongitude();

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

                    unset($inmueble);
                    unset($id_listing_external);
                    unset($link);
                }
            }
        }
    }

    unset(
        $browser,
        $script,
        $response,
        $total_pages,
        $page,
        $listing,
        $link,
        $id,
        $url,
        $curl,
        $data,
        $_status,
        $result_delete,
        $counter_delete,
        $c_delete,
        $delete_babilonia,
        $del_babilonia,
        $array_listing_id,
        $message_delete_listing,
        $answer_delete_ok,
        $answer_delete_error,
        $error,
        $select_users_packages,
        $user_package,
        $user_package_id,
        $array_external_listing_id_already_exist,
        $result_create,
        $counter_create,
        $c_create,
        $inmueble,
        $entire_website,
        $message_create,
        $answer_create_ok,
        $answer_create_error,
        $answer_error_create_counter,
        $answer_error_create_external_listing_id_counter,
        $result_update,
        $counter_update,
        $c_update,
        $message_update,
        $answer_update_ok,
        $answer_update_error,
        $answer_error_update_counter,
        $id_listing_external,
        $external_listing_id_already_exist,
        $current_date
    );
?>
