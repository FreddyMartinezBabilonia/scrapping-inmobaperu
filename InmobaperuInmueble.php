<?php
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

Class InmobaperuInmueble {
    
    private $url;

    private $entire_website = '';
    private $maintenance_price = null;

    private $array_images = array();
    private $array_videos = array();
    private $array_360 = array();
    private $array_facilities = array();

    private $property_type = null;
    private $description = null;
    private $price = null;
    private $listing_type = null;

    private $parking_for_visits = null;
    private $pet_friendly = null;
    private $qty_env = null;
    private $terrain_area = null;
    private $total_floors_count = null;
    private $floor_number = null;
    private $half_bathrooms_count = null;
    private $bedrooms_count = null;
    private $bathrooms_count = null;
    private $area = null;
    private $built_area = null;
    private $parking_slots_count = null;
    private $year_of_construction = null;
    
    private $country = null;
    private $department = null;
    private $province = null;
    private $district = null;
    private $address = null;
    private $address_alternative = null;
    private $longitude = null;
    private $latitude = null;

    private $status = null;
    private $role = "publisher_role";
    private $id_listing_external = null;
    private $id_realtor = null;
    private $link = null;

    private $array_contacts = array();


    public function __construct($url){
        $this->url = $url;

        $this->getContentFromTempalte();
    }

    public function getContentFromTempalte (){
        
        $link = $this->url;
        $array_images = $this->array_images;
        $bedrooms_count = null;
        $bathrooms_count = null;
        $area = null;
        $built_area = null;
        $parking_slots_count = null;
        $year_of_construction = null;
        $array_imagenes = array();

        $client = new HttpBrowser(HttpClient::create(['timeout' => 180]));

        $listing = $client->request('GET', $link);

        $this->entire_website = $listing->html();

        $body = $listing->filter("body");

        $title = $body
                ->filter(".attribute-title-box")
                ->text();    
        $body
            ->filter(".listing-gallery-thumbnail-box")
            ->filter("a")
            ->each(function($node) use(&$array_images) {                        
                array_push($array_images, $node->attr("href"));                
            });
        
        $this->array_images = $array_imagenes;

        $listing_type = $body
                ->filter(".listing-single-info")
                ->filter(".listing-category-list")
                ->filter("span")
                ->text();

        switch (strtolower($listing_type)) {
            case 'alquiler':
                $listing_type = "rent";
            break;

            case 'venta':
                $listing_type = "sale";
            break;

            default:
                $listing_type = null;
            break;
        }
        $this->listing_type = $listing_type;

        $this->property_type = $this->cleanPropertyType($body
                        ->filter(".listing-single-info")
                        ->filter(".listing-type-list")
                        ->filter("span")
                        ->text());

        if(!empty($body
                    ->filter(".site-content")
                    ->filter("div:nth-child(4)")
                    ->filter(".container")
                    ->filter(".stm-row")
                    ->filter(".stm-col:nth-child(1)")
                    ->filter("div:nth-child(1)")
                    ->text())){

        $description = $body
                    ->filter(".site-content")
                    ->filter("div:nth-child(4)")
                    ->filter(".container")
                    ->filter(".stm-row")
                    ->filter(".stm-col:nth-child(1)")
                    ->filter("div:nth-child(1)")
                    ->html();
        $this->description = strval(str_replace("<br>", "", $description));

        }

        if (str_contains($description, " https://youtu")) {
            $video_url = get_string_between($description, 'https://youtu', '<');
            $video_url = strval("https://youtu" . $video_url);

            array_push($this->array_videos, $video_url);
        }

        if (
            $body
                ->filter(".site-content")
                ->filter(".genuine_sale")
                ->count() > 0
        ) {
            $price = $body
                ->filter(".site-content")
                ->filter(".genuine_sale")
                ->text();

            $this->price = str_replace(array(",", ".","$"), array("", "", ""), $price);
        }


        $this->id_listing_external = $body
                                ->filter("body")
                                ->filter("span.ulisting-listing-wishlist")
                                ->attr("data-wishlist_id");

        $array_attributes = array();

        $attr1 = $body
                    ->filter(".attribute-box")
                    ->filter(".attribute-box-columns:nth-child(1)");    
        
        $attr2 = $body
                    ->filter(".attribute-box")
                    ->filter(".attribute-box-columns:nth-child(2)");

        $attr3 = $body
                    ->filter(".attribute-box")
                    ->filter(".attribute-box-columns:nth-child(3)");

        $attr4 = $body
                    ->filter(".attribute-box")
                    ->filter(".attribute-box-columns:nth-child(4)");

        foreach([$attr1, $attr2, $attr3, $attr4] as $node){
            $name = strtolower($node->filter(".attribute-name")->text());
            $value = strtolower($node->filter(".attribute-value")->text());
            
            switch ($name) {
                case 'dormitorios':
                    $bedrooms_count = empty($value) || $value == "n/a" ? null : intval($value);
                break;

                case 'baños':
                    $bathrooms_count = empty($value) || $value == "n/a" ? null : intval($value);
                break;

                case 'mt2':
                    $area = empty($value) || $value == "n/a" ? null : intval($value);
                break;

                case 'cochera':
                    $parking_slots_count = empty($value) || $value == "n/a" ? null : intval($value);
                break;
            }
        }

        $this->bedrooms_count = $bedrooms_count;
        $this->bathrooms_count = $bathrooms_count;
        $this->area = $area;
        $this->parking_slots_count = $parking_slots_count;

        $year_of_construction = $body
                    ->filter(".site-content")
                    ->filter("div:nth-child(4)")
                    ->filter(".container")
                    ->filter(".stm-row")
                    ->filter(".stm-col:nth-child(1)")
                    ->filter(".attribute-box")
                    ->filter(".attribute-value")
                    ->text();
        $year_of_construction = !empty($year_of_construction) && strtolower($year_of_construction) != "n/a" ? $year_of_construction : null;

        if (!isset($this->listing_type) OR is_null($this->listing_type)) {
            $this->status = strval("hidden");
        }
        else if (!isset($this->property_type) OR is_null($this->property_type)) {
            $this->status = strval("hidden");
        }
        else if (!isset($this->description) OR is_null($this->description)) {
            $this->status = strval("hidden");
        }
        else if (!isset($this->price) OR is_null($this->price)) {
            $this->status = strval("hidden");
        }
        else if (!isset($this->area) OR is_null($this->area)) {
            $this->status = strval("hidden");
        }
        else if (count($this->array_images) <= 0) {
            $this->status = strval("hidden");
        }
        else if (!isset($this->longitude) AND !isset($this->latitude)) {
            if (!isset($this->district) OR !isset($this->province) OR !isset($this->department)) {
                $this->status = strval("hidden");
            }
            else {
                $this->status = strval("visible");
            }
        }
        else {
            $this->status = strval("visible");
        }        
    }
    
    public function getEntireWebsite(){
        return $this->entire_website;
    }
    
    public function getPropertyType() {
        return $this->property_type;
    }

    public function getListingType() {
        return $this->listing_type;
    }

    public function getPrice() {
        return $this->price;
    }

    public function getMaintenancePrice() {
        return $this->maintenance_price;
    }

    public function getDescription() {
        return $this->description;
    }

    public function getBathroomsCount() {
        return $this->bathrooms_count;
    }

    public function getHalfBathroomsCount() {
        return $this->half_bathrooms_count;
    }

    public function getBedroomsCount() {
        return $this->bedrooms_count;
    }

    public function getArea() {
        return $this->area;
    }

    public function getYearOfConstruction() {
        return $this->year_of_construction;
    }

    public function getParkingSlotsCount() {
        return $this->parking_slots_count;
    }

    public function getFloorNumber() {
        return $this->floor_number;
    }

    public function getTotalFloorsCount() {
        return $this->total_floors_count;
    }

    public function getBuiltArea() {
        return $this->built_area;
    }

    public function getTerrainArea() {
        return $this->terrain_area;
    }

    public function getQtyEnv() {
        return $this->qty_env;
    }

    public function getPetFriendly() {
        return $this->pet_friendly;
    }

    public function getParkingForVisits() {
        return $this->parking_for_visits;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getPublisherRole() {
        return $this->role;
    }

    public function getContacts() {
        return !empty($this->array_contacts) ? $this->array_contacts : null;
    }

    public function getCountry() {
        return $this->country;
    }

    public function getDepartment() {
        return $this->department;
    }

    public function getProvince() {
        return $this->province;
    }

    public function getDistrict() {
        return $this->district;
    }

    public function getAddress() {
        return $this->address;
    }

    public function getAddressAlternative() {
        return $this->address_alternative;
    }

    public function getLatitude() {
        return $this->latitude;
    }

    public function getLongitude() {
        return $this->longitude;
    }

    public function getImageIds() {
        return array_values(array_unique($this->array_images));
    }

    public function getVideos() {
        return !empty($this->array_videos) ? $this->array_videos : null;
    }

    public function getArray360() {
        return !empty($this->array_360) ? $this->array_360 : null;
    }

    public function getFacilityIds() {
        return !empty($this->array_facilities) ? $this->array_facilities : null;
    }

    public function getIdListingExternal() {
        return $this->id_listing_external;
    }

    public function getIdRealtor() {
        return $this->id_realtor;
    }

    public function getUrl() {
        return $this->url;
    }
    

    public function cleanPropertyType ($property_type = null) {

        $property_type =  strval($property_type);
        $property_type = strtolower($property_type);

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
            OR str_contains(strtolower($property_type), "departamentos")
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
            OR str_contains(strtolower($property_type), "terrenos")
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
}