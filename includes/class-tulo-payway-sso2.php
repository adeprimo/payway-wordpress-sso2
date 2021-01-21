<?php

use \Firebase\JWT\JWT;

class Tulo_Payway_API_SSO2 {

    private $common;
    private $api;

    private $sso_check_session_timeout = 30;
    private $sso_session_id_key = "sso2_session_id";
    private $sso_session_status_key = "sso2_session_status";
    private $sso_session_established_key = "sso2_session_established";
    private $sso_session_user_name_key = "sso2_session_user_name";
    private $sso_session_user_email_key = "sso2_session_user_email";
    private $sso_session_user_active_products_key = "sso2_session_user_active_products";

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        $this->common = new Tulo_Payway_Server_Common();
        $this->api = new Tulo_Payway_API();
    }  

    public function write_log($msg) {
        $this->common->write_log($msg);
    }

    /**
     * Called from the landing page, checks session status and sets user in session if logged in
     */
    public function register_basic_session($sso_payload) {
        $this->common->write_log("Registering SSO session");
        if (isset($sso_payload)) {
            $_SESSION[$this->sso_session_id_key] = $sso_payload->sid;
            $_SESSION[$this->sso_session_status_key] = $sso_payload->sts;

            if ($sso_payload->sts == "loggedin" && $sso_payload->at != "") {
                $data = $this->api->get_user_and_products_by_ticket($sso_payload->at);
                if ($data != null) {
                    $this->set_user_name($data["user"]->first_name." ".$data["user"]->last_name);
                    $this->set_user_email($data["user"]->email);
                    $this->set_user_active_products($data["active_products"]);
                    $this->set_session_established();
                } else {
                    $this->common->write_log("!! Could not get user and product info from Payway!");
                }
            }
        }
    }

    public function session_needs_refresh() {
        $established = $_SESSION[$this->sso_session_established_key];
        $diff = (time()-$established);
        if ($diff > $this->sso_check_session_timeout)
            return true;
        return false;
    }

    public function get_session_status() {
        return $_SESSION[$this->sso_session_status_key];
    }
    public function get_user_name() {
        return $_SESSION[$this->sso_session_user_name_key];
    }

    public function get_user_email() {
        return $_SESSION[$this->sso_session_user_email_key];
    }

    public function user_has_subscription() {
        $products = $this->get_user_active_products();
        return !empty($products);
    }

    public function get_user_active_products() {
        return $_SESSION[$this->sso_session_user_active_products_key]; 
    }

    public function session_established() {
        if (isset($_SESSION[$this->sso_session_id_key]) && $_SESSION[$this->sso_session_id_key] != "")
            return true;
        return false;
    }
    
    public function sso_session_id() {
        return $_SESSION[$this->sso_session_id_key];
    }
    

    public function identify_session() {
        $url = $this->get_sso2_url("identify");
        $client_id = get_option('tulo_server_client_id');
        $client_secret = get_option('tulo_server_secret');
        $organisation_id = get_option('tulo_organisation_id');

        $time = time();
        $payload = array(
             "cid" => $client_id,
             "iss" => $organisation_id,
             "aud" => "pw-sso",
             "nbf" => $time,
             "exp" => $time + 10,
             "iat" => $time
        );

        $this->write_log("Identify payload:");
        $this->write_log($payload);

        $token = JWT::encode($payload, $client_secret);
        $protocol = isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        $continueUrl = sprintf("%s://%s%s", $protocol, $_SERVER["HTTP_HOST"], $_SERVER["REQUEST_URI"]);
        $this->write_log(("Redirect url: ".$continueUrl));

        $url = sprintf("%s?t=%s&r=%s", $url, $token, $continueUrl);
        header("Location: ".$url);
        die();
   }

    public function refresh_session() {
        $this->common->write_log("!! Refreshing session");
        $url = $this->get_sso2_url("sessionstatus");
        $client_id = get_option('tulo_server_client_id');
        $client_secret = get_option('tulo_server_secret');
        $organisation_id = get_option('tulo_organisation_id');
        $ip_address = $_SERVER ['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        $time = time();
        $payload = array(
             "cid" => $client_id,
             "sid" => $this->sso_session_id(),
             "ipa" => $ip_address,
             "uas" => $user_agent,
             "lks" => $this->get_session_status(),
             "iss" => $organisation_id,
             "aud" => "pw-sso",
             "nbf" => $time,
             "exp" => $time + 10,
             "iat" => $time
        );

        $this->write_log("Session refresh payload:");
        $this->write_log($payload);
        $token = JWT::encode($payload, $client_secret);
        $payload = json_encode(array("t" => $token));
        
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
        $this->common->write_log("Session status url: ".$url);
        $this->common->write_log("Payload: ".$payload);
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpcode == 200) {
            $data = json_decode($output);
            $decoded = JWT::decode($data->t, $client_secret, array("HS256"));
            if ($decoded->sts == "terminated") {
                $this->common->write_log("session terminated in other window, logging out user");
                $this->logout_user();
            } else {
                $this->set_session_established();
            }
        }
    }

    private function logout_user() {
        
        $this->set_user_active_products(array());
        $this->set_user_email(null);
        $this->set_user_name(null);
        $_SESSION[$this->sso_session_established_key] = null;
        $_SESSION[$this->sso_session_id_key] = null;
        $_SESSION[$this->sso_session_status_key] = null;
    }

    private function set_user_name($name) {
        $_SESSION[$this->sso_session_user_name_key] = $name;
    }

    private function set_user_email($email) {
        $_SESSION[$this->sso_session_user_email_key] = $email;
    }

    private function set_user_active_products($products) {
        $_SESSION[$this->sso_session_user_active_products_key] = $products;
    }

    private function set_session_established() {
        $_SESSION[$this->sso_session_established_key] = time();
    }

    private static function get_sso2_url($path) {       
        $url = "";
        if (get_option('tulo_environment') == 'prod') {
            $url = "";
        }
        else {
            $url = "https://payway-sso-stage.azurewebsites.net";
        }
        return sprintf("%s/%s", $url, $path);    
    }

}

?>