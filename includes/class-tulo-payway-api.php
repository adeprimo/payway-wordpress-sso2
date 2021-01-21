<?php

class Tulo_Payway_API {
    
    private $common;
    /**
     * Initialize the class and set its properties.
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( ) {
        $this->common = new Tulo_Payway_Server_Common();
    }  


    public function get_user_and_products_by_ticket($ticket) {
        $this->common->write_log("Fetching user from ticket: ".$ticket);
        $token = $this->get_access_token_from_ticket($ticket);
        if (isset($token) && $token != "" ) {
            return $this->get_user_and_products_by_token($token);
        }
        return null;
    }

    public function get_user_and_products_by_token($token) {
        $user = $this->get_user_details($token);
        if ($user != null) {
            $active_products = $this->get_user_active_products($token);
            return array(
                "user" => $user,
                "active_products" => $active_products
            );
        } else {
            $this->common->write_log("!! Could not get user from Payway");
        }
        return null;
    }

    public function get_user_details($token) {

        $url = $this->get_api_url("/external/api/v1/me");

        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            )
        ));

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode == 200) {
            $data = json_decode($response);
            return $data->item;
        }        
        return null;
    }

    public function get_user_active_products($token) {
        $url = $this->get_api_url("/external/api/v1/me/active_products");

        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            )
        ));

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode == 200) {
            $data = json_decode($response);
            return $data->item->active_products;
        }        
        return array();
    }

    public function get_access_token_from_ticket($ticket) {
        $url = $this->get_api_url("/api/authorization/access_token");
        $client_id = get_option('tulo_server_client_id');
        $client_secret = get_option('tulo_server_secret');
        $organisation_id = get_option('tulo_organisation_id');
        $scopes = "/external/me/w /external/account/r /external/account/w";

        $fields = "client_id=".$client_id."&client_secret=".$client_secret."&grant_type=ticket&scope=".$scopes."&ticket=".$ticket;
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_POST => 1,
            CURLOPT_HTTPHEADER => array(
                "Accept: application/json",
                "Content-Type: application/x-www-form-urlencoded"
            )
        ));

        $this->common->write_log("Access token url: ".$url);
        $this->common->write_log("Fields: ".$fields);
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $data = json_decode($output);

        $token = null;
        if ($httpcode == 200) {
            $this->common->write_log("Got access token: ".$data->access_token);
            $token = $data->access_token;
        } else {
            $error = curl_error($ch);
            $this->common->write_log("!! Error fetching access token: ".$httpcode." => ".$error);
        }
        curl_close($ch);
        return $token;
    }

    private function get_api_url($path) {
        $url = "";
        if (get_option('tulo_environment') == 'prod') {
            $url = "https://backend.worldoftulo.com";
        }
        else {
            $url = "https://payway-api.stage.adeprimo.se";
        }
        return sprintf("%s%s", $url, $path);    

    }

}

?>