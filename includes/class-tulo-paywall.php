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

    public function get_back_url() {
        return $this->get_return_url();
    }
    
    public function get_return_url() {
        return $this->get_current_url();
    }

    public function get_current_url() {
        global $wp;
        return add_query_arg( $wp->query_vars, home_url( $wp->request ) );
    }

    public function get_account_origin() {
        return get_option("tulo_paywall_account_origin");
    }

    public function get_traffic_source() {
        return get_option("tulo_paywall_traffic_source");
    }

    public function get_merchant_reference() {
        if (get_option("tulo_paywall_merchant_reference_static") == "" && get_option("tulo_paywall_merchant_reference_link") == "on") {
            return $this->get_current_url();
        }
        return get_option("tulo_paywall_merchant_reference_static");
    }

    public function get_slug() {
        global $post;
        return $post->post_name;
    }

    public function get_article_id() {
        global $wp_query;
        return $wp_query->post->ID;
    }

    public function get_paywall_css() {
        $url = get_option("tulo_paywall_css_url");
        if ( $url != "") {
            return $url;
        }
        return get_option('tulo_environment') == 'prod' ? "https://payway-cdn.worldoftulo.com/css/paywall.css" : "https://payway-cdn-stage.adeprimo.se/css/paywall.css";
    }
    public function get_paywall_js() {
        return get_option('tulo_environment') == 'prod' ? "https://payway-cdn.worldoftulo.com/js/paywall.js" : "https://payway-cdn-stage.adeprimo.se/js/paywall.js";
    }
    public function get_paywall_url() {
        //return "https://localhost:7172/api/paywall";
        return get_option('tulo_environment') == 'prod' ? "https://paywall.worldoftulo.com/api/paywall" : "https://payway-paywall-stage.adeprimo.se/api/paywall";
    }
}
