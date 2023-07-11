<?php


use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

/**
 * Plugin common methods
 */
class Tulo_Paywall_Common {

    private $session;

    public function __construct() {
        $this->session = new Tulo_Payway_Session();
    }

    public function get_signature() {

        $title = get_option("tulo_paywall_title");
        $client_id = get_option('tulo_server_client_id');
        $client_secret = get_option('tulo_server_secret');
        $aid = $this->session->get_user_id();

        $time = time();
        $payload = array(
             "t"   => $title,
             "aid" => $aid,
             "iss" => $client_id,
             "aud" => "pw-paywall",
             "nbf" => $time,
             "exp" => $time + 60,
             "iat" => $time
        );

        $token = JWT::encode($payload, $client_secret, 'HS256');
        return $token;
    }

    public function get_account_origin() {
        return get_option("tulo_paywall_account_origin");
    }

    public function get_traffic_source() {
        return get_option("tulo_paywall_traffic_source");
    }

    public function get_merchant_reference() {
        return "merchant_reference";
    }

    public function get_article_id() {
        return "article id";
    }

    public function get_paywall_css() {
        $url = get_option("tulo_paywall_css_url");
        if ( $url != "") {
            return $url;
        }

        if (get_option('tulo_environment') == 'prod') {
            $url = "https://payway-cdn.worldoftulo.com/css/paywall.css";
        }
        else {
            $url = "https://payway-cdn-stage.adeprimo.se/css/paywall.css";
        }
        return $url;
    }
    public function get_paywall_js() {
        $url = "";
        if (get_option('tulo_environment') == 'prod') {
            $url = "https://payway-cdn.worldoftulo.com/js/paywall.js";
        }
        else {
            $url = "https://payway-cdn-stage.adeprimo.se/js/paywall.js";
        }
        return $url;
    }
    public function get_paywall_url() {
        $url = "";
        if (get_option('tulo_environment') == 'prod') {
            $url = "https://paywall.worldoftulo.com/api/paywall";
        }
        else {
            $url = "https://payway-paywall-stage.adeprimo.se/api/paywall";
        }
        return $url;
    }
}
