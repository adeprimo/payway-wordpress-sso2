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
                    $this->set_session_loggedin();
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

    public function is_logged_in() {
        if ($this->session_established()) {
            if ($this->get_user_name() !="" || $this->get_user_email() != "") {
                return true;
            }
        }
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
        if (isset($_SESSION[$this->sso_session_user_active_products_key]))
            return $_SESSION[$this->sso_session_user_active_products_key]; 
        return null;
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
            "iss" => $organisation_id,
            "sid" => $this->sso_session_id(),
            "ipa" => $ip_address,
            "uas" => $user_agent,
            "lks" => $this->get_session_status(),
            "aud" => "pw-sso",
            "nbf" => $time,
            "exp" => $time + 10,
            "iat" => $time
        );
                
        $token = JWT::encode($payload, $client_secret);
        $payload = json_encode(array("t" => $token));

        $response = $this->common->post_json_jwt($url, $payload);
        if ($response["status"] == 200) {
            $data = json_decode($response["data"]);
            $decoded = JWT::decode($data->t, $client_secret, array("HS256"));
            if ($decoded->sts == "terminated") {
                $this->common->write_log("session terminated in other window, logging out user");
                $this->logout_user(false);
            } else if ($decoded->sts == "loggedin") {
                $this->set_session_loggedin();
            }
        }
    }

    public function authenticate_user($email, $password) {
        $this->common->write_log("!! Authenticating user: ".$email);
        $url = $this->get_sso2_url("authenticate");
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
             "usr" => $email,
             "pwd" => $password,
             "iss" => $organisation_id,
             "aud" => "pw-sso",
             "nbf" => $time,
             "exp" => $time + 10,
             "iat" => $time
        );

        $token = JWT::encode($payload, $client_secret);
        $payload = json_encode(array("t" => $token));

        $response = $this->common->post_json_jwt($url, $payload);
        $status = array();
        if ($response["status"] == 200) {
            $data = json_decode($response["data"]);
            $decoded = JWT::decode($data->t, $client_secret, array("HS256"));
            $status["success"] = $decoded->sts=="loggedin";
            if ($decoded->sts == "loggedin") {
                $this->common->write_log("User successfully authenticated!");
                $this->register_basic_session($decoded);
                $status["name"] = $this->get_user_name();
                $status["email"] = $this->get_user_email();
                $status["products"] = $this->get_user_active_products();
            } else {
                $status["error_code"] = $decoded->err;
                if ($decoded->err == "account_frozen") {
                    $status["error"] = __('Account locked until', 'tulo');
                    $frozenTo = new DateTime();
                    $this->common->write_log("Frozen for: ".$decoded->frf);
                    $frozenTo->setTimestamp(time() + $decoded->frf);
                    $status["frozen_until"] = $frozenTo->format('c');
                }
                if ($decoded->err == "invalid_credentials") {
                    $msg = __('Wrong username or password. (%s attempts left)', 'tulo');
                    $msg = sprintf($msg, $decoded->raa);
                    $status["error"] = $msg;
                    $status["remaining_attempts"] = $decoded->raa;
                } 
                if ($decoded->err == "account_not_active") {
                    $msg = __('Wrong username or password. (%s attempts left)', 'tulo');
                    $msg = sprintf($msg, 5);
                    $status["error"] = $msg;
                    $status["remaining_attempts"] = 5;
                }
            }
        }
        else {
            $this->common->write_log("!! Error authenticating: ".$response["status"]." => ".$response["data"]);
            $status["success"] = false;
            $status["error_code"] = "unknown_error";
        }
        $this->common->write_log("Response authentication:");
        $this->common->write_log($status);
        return $status;

    }

    
    public function logout_user($locallyInitiated=true) {

        if ($locallyInitiated) {
            // Terminate session in Payway
            $this->sso_logout();
        }
        // Terminate locally
        $this->set_user_active_products(array());
        $this->set_user_email(null);
        $this->set_user_name(null);
        $_SESSION[$this->sso_session_established_key] = null;
        $_SESSION[$this->sso_session_id_key] = null;
        $_SESSION[$this->sso_session_status_key] = null;
        setcookie('tpw_id', null, -1, '/');
        return true;
    }

    private function sso_logout() {
        $this->common->write_log("!! Logging out user");
        $url = $this->get_sso2_url("logout");
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
             "iss" => $organisation_id,
             "aud" => "pw-sso",
             "nbf" => $time,
             "exp" => $time + 10,
             "iat" => $time
        );

        $token = JWT::encode($payload, $client_secret);
        $payload = json_encode(array("t" => $token));
        $response = $this->common->post_json_jwt($url, $payload);
        if ($response["status"] == 200) {
            $this->common->write_log("Logout successful");
        } else {
            $this->common->write_log("Logout failed. (".$response["status"].") '".$response["error"]."'");
        }
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

    private function set_session_loggedin() {
        $_SESSION[$this->sso_session_established_key] = time();
        $unique_id = base64_encode($this->sso_session_id() . (string)microtime());
        setcookie("tpw_id", $unique_id, strtotime('+30 days'), '/');
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