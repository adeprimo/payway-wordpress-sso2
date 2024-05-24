<?php

use \Firebase\JWT\JWT;

class Tulo_Paywall_Server_Common extends Tulo_Payway_Server_Common {

    const LOG_PREFIX = "PAYWALL";

    public function __construct() {
    }

}

/**
 * Plugin common methods
 */
class Tulo_Payway_Server_Common {

    const LOG_PREFIX = "SSO";

    public function __construct() {
    }

    public static function get_tulo_myaccount_url() {
        $url = "";
        $currentOrg = get_option('tulo_organisation_id');
        if (get_option('tulo_environment') == 'prod') {
            $url = "https://".$currentOrg.".portal.worldoftulo.com";
        }
        else {
            $url = "https://".$currentOrg.".payway-portal.stage.adeprimo.se"; 
        }
        return $url;    
    }

    public function get_authentication_url() {
        global $wp;
        $currentUrl = home_url( $wp->request );
        $permalinkStructure = get_option( 'permalink_structure' );
        if ($permalinkStructure == "plain") {
            $queryVars = $wp->query_vars;
            $queryVars['tpw_session_refresh'] = '1';
            $currentUrl = add_query_arg( $queryVars, home_url( $wp->request ) );    
        } else {
            $currentUrl .= "?tpw_session_refresh=1";
        } 
        $currentOrg = get_option('tulo_organisation_id');
        $authUrl = get_option('tulo_authentication_url');
        return str_replace("{currentOrganisation}", $currentOrg, str_replace("{currentUrl}", urlencode($currentUrl), $authUrl));
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
                error_log("[".static::LOG_PREFIX."] ".$log);
            }
        }
    }

}
