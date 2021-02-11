<?php

use \Firebase\JWT\JWT;

/**
 * Plugin common methods
 */
class Tulo_Payway_Server_Common {

    public function __construct() {

    }

    public function get_json_with_bearer($url, $token) {
        $this->write_log("[get_json_with_bearer]");
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

        return array(
            "status" => $httpcode,
            "data" => $response
        );
        if ($httpcode == 200) {
            $data = json_decode($response);
            return $data->item->active_products;
        }        
        return array();


    }

    public function post_json_jwt($url, $payload) {
        $this->write_log("[post_json_jwt]");
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_POST => 1,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            )
        ));

        $this->write_log("posting payload:");
        $this->write_log($payload);
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        $response = array(
            'status' => $httpcode,
            'error' => $error,
            'data' => $output
       );

       return $response;

    }
   
    public static function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log("[SSO2] ".$log);
            }
        }
    }

}
