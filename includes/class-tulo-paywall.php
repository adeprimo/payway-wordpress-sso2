<?php


use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

/**
 * Plugin common methods
 */
class Tulo_Paywall_Common {

    private $session;
    const PAYWALL_VERSION = "1.2";

    public function __construct() {
        $this->session = new Tulo_Payway_Session();
        $this->common = new Tulo_Paywall_Server_Common();
    }

    public function get_signature($post_restrictions) {

        $title = get_option("tulo_paywall_title");
        $client_id = get_option('tulo_paywall_client_id');
        $client_secret = get_option('tulo_paywall_secret');
        $aid = $this->session->get_user_id();
        
        $this->common->write_log("Fetching Paywall with aid: ".$aid);

        $key = get_option('tulo_paywall_static_selector_key');
        $dynamic_key = get_option('tulo_paywall_dynamic_selector_key');
        
        if ($dynamic_key != "" && isset($_SESSION[$dynamic_key])) {
            $key = $_SESSION[$dynamic_key];
        }

        if (get_option('tulo_paywall_product_selector_key') == "on") {
            $key = $this->get_product_codes($post_restrictions);
        }

        $this->common->write_log("Fetching Paywall with key: ".$key);
        $time = time();
        $payload = array(
             "t"   => $title,
             "aid" => $aid,
             "iss" => $client_id,
             "aud" => "pw-paywall",
             "nbf" => $time,
             "exp" => $time + 60,
             "iat" => $time,
             "pc" => $key
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

    public function get_login_url() {
        $login_url = get_option("tulo_paywall_template_login_url");
        if ($login_url == "") {
            return $this->common->get_authentication_url();
        }
        return $login_url;
    }

    public function get_shop_url() {
        return get_option("tulo_paywall_template_shop_url");
    }

    public function get_paywall_css() {
        $version = Tulo_Paywall_Common::PAYWALL_VERSION;
        if ($version == '1.0') {
            return get_option('tulo_environment') == 'prod' ? "https://payway-cdn.worldoftulo.com/css/paywall.css" : "https://payway-cdn-stage.adeprimo.se/css/paywall.css";
        }
        return get_option('tulo_environment') == 'prod' ? "https://payway-cdn.worldoftulo.com/css/".$version."/paywall.css" : "https://payway-cdn-stage.adeprimo.se/css/".$version."/paywall.css";
    }
    public function get_paywall_js() {
        $version = Tulo_Paywall_Common::PAYWALL_VERSION;
        if ($version == '1.0') {
            return get_option('tulo_environment') == 'prod' ? "https://payway-cdn.worldoftulo.com/js/paywall.js" : "https://payway-cdn-stage.adeprimo.se/js/paywall.js";
        }
        return get_option('tulo_environment') == 'prod' ? "https://payway-cdn.worldoftulo.com/js/".$version."/paywall.js" : "https://payway-cdn-stage.adeprimo.se/js/".$version."/paywall.js";
    }
    public function get_paywall_url() {
        //return "https://localhost:7172/api/paywall";
        return get_option('tulo_environment') == 'prod' ? "https://paywall.worldoftulo.com/api/paywall" : "https://payway-paywall-stage.adeprimo.se/api/paywall";
    }

    public function get_custom_variables() {
        $variables = get_option("tulo_paywall_variables");
        $custom_variables = array();
        foreach($variables as $variable) {
            $value = $variable->value;
            if ($value == "\$user.name") {
                $user_name = $this->session->get_user_name();
                $value = $user_name != null ? $user_name : "";
            }
            $custom_variables[$variable->key] = $value;
        }   
        return json_encode($custom_variables);
    }

    private function get_product_codes($post_restrictions) {
        $restrictions = array();
        //$this->common->write_log("Getting product code for restrictions: ".print_r($post_restrictions, true));
        if (isset($post_restrictions) && is_array($post_restrictions)) {
            foreach($post_restrictions as $restriction) {                
                array_push($restrictions, $restriction->productid);
            }
            return join(".", $restrictions);
        }
        return "";
    }
}
