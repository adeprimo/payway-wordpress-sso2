<?php

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

/**
 * The Tulo Payway SSO2 API
 * 
 * TODO:
 *  - Add production url
 * 
 */
class Tulo_Payway_API_SSO2 {

    private $common;
    private $api;

    private $sso_session_id_key = "sso2_session_id";
    private $sso_session_status_key = "sso2_session_status";
    private $sso_session_established_key = "sso2_session_established";
    private $sso_session_user_id_key = "sso2_session_user_id";
    private $sso_session_user_name_key = "sso2_session_user_name";
    private $sso_session_user_email_key = "sso2_session_user_email";
    private $sso_session_user_custno_key = "sso2_session_user_customer_number";
    private $sso_session_user_active_products_key = "sso2_session_user_active_products";

    const SESSION_ESTABLISHED_STATUS_COLD = "cold";
    const SESSION_ESTABLISHED_STATUS_WARM = "warm";
    const SESSION_ESTABLISHED_STATUS_NOEXIST = "nosession";

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        $this->common = new Tulo_Payway_Server_Common();
        $this->api = new Tulo_Payway_API();
    }  

    protected function process_paywall_checkout_login($payload) {
        $this->common->write_log("---> process paywall checkout login");
        if (isset($payload)) {
            $delegated_ticket = $payload->dtid;
            $account_id = $payload->aid;
            $this->common->write_log("Authentication ticket: ".$delegated_ticket);
            $this->common->write_log("Account id: ".$account_id);

            $url = $this->get_sso2_url("authenticatewithticket");
            $client_id = get_option('tulo_server_client_id');
            $client_secret = get_option('tulo_server_secret');
    
            $token = $this->get_delegated_ticket_token($client_id, $client_secret, $delegated_ticket);
            $payload = json_encode(array("t" => $token));
    
            $this->common->write_log("posting payload to: ".$url);
            $response = $this->common->post_json_jwt($url, $payload);
            if ($response["status"] == 200) {
                $data = json_decode($response["data"]);
                $decoded = $this->decode_token($data->t, $client_secret);
                if ($decoded == null) {
                    $this->common->write_log("[ERROR] error processing response from sso request, token could not be decoded");
                } else {
                    $sts = $decoded->sts; 
                    $err = $decoded->err;
                    $at = $decoded->at;
                    $this->common->write_log(" sts.......: ".$sts);
                    $this->common->write_log(" at........: ".$at);
                    if ($sts == "loggedin" && $at != "") {
                        $this->fetch_user_and_login($at);
                        $this->common->write_log("<--- process paywall completed successfully, user is logged in, ready for reload.");
                    } else {
                        $this->common->write_log(" !! Error fetching access token => ".$err);
                    }
                }                
    
            } else {
                $this->common->write_log("[ERROR] error posting authenticate with ticket request");
                $this->common->write_log($response);
            }

    
        }
    }

    private function get_delegated_ticket_token($client_id, $client_secret, $ticket) {
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
            "at" =>  $ticket, 
            "aud" => "pw-sso",
            "nbf" => $time,
            "exp" => $time + 10,
            "iat" => $time
        );
        $this->common->write_log("ticket token payload:");
        $this->common->write_log($payload);
        
        $token = JWT::encode($payload, $client_secret, 'HS256');
        return $token;
    }
    /**
     * Called from the landing page, checks session status and sets user in session if logged in
     */
    protected function register_basic_session($sso_payload) {
        $this->common->write_log("[register basic session]");            
        if (isset($sso_payload)) {
            $_SESSION[$this->sso_session_id_key] = $sso_payload->sid;
            $_SESSION[$this->sso_session_status_key] = $sso_payload->sts;
            $_SESSION[$this->sso_session_established_key] = time();
            
            if ($sso_payload->sts == "loggedin" && $sso_payload->at != "") {
                $this->fetch_user_and_login($sso_payload->at);
            }
        } else {
            $this->common->write_log("no sso payload to register");
        }
    }
    
    protected function session_needs_refresh() {
        if (!isset($_SESSION[$this->sso_session_established_key])) {
            return true;
        }

        $established = $_SESSION[$this->sso_session_established_key];
        $diff = (time()-$established);
        $session_timeout = get_option('tulo_session_refresh_timeout');
        $this->common->write_log("established: ".$established." diff: ".$diff." timeout: ".$session_timeout);
        if ($diff > $session_timeout)
            return true;

        $_SESSION[$this->sso_session_established_key] = time();            
        return false;
    }

    protected function is_session_logged_in() {
        if ($this->session_established()) {
            if ($this->get_user_name() !="" || $this->get_user_email() != "") {
                return true;
            }
        }
        return false;
    }

    protected function get_session_status() {
        if (isset($_SESSION[$this->sso_session_status_key]))
            return $_SESSION[$this->sso_session_status_key];
        return "anon";            
    }

    protected function get_session_user_id() {
        if (isset($_SESSION[$this->sso_session_user_id_key]))
            return $_SESSION[$this->sso_session_user_id_key];
    }

    protected function get_session_user_name() {
        if (isset($_SESSION[$this->sso_session_user_name_key]))
            return $_SESSION[$this->sso_session_user_name_key];
    }

    protected function get_session_user_email() {
        if (isset($_SESSION[$this->sso_session_user_email_key]))
            return $_SESSION[$this->sso_session_user_email_key];
    }

    protected function get_session_user_customer_number() {
        if (isset($_SESSION[$this->sso_session_user_custno_key]))
            return $_SESSION[$this->sso_session_user_custno_key];
    }

    protected function get_session_user_active_products() {
        if (isset($_SESSION[$this->sso_session_user_active_products_key]))
            return $_SESSION[$this->sso_session_user_active_products_key]; 
        return null;
    }

    protected function session_established() {

        $cookieSessionId = $this->get_session_id_from_cookie();        
        if (isset($_SESSION[$this->sso_session_id_key]) && $_SESSION[$this->sso_session_id_key] != "")
            return Tulo_Payway_API_SSO2::SESSION_ESTABLISHED_STATUS_WARM;
        if ($cookieSessionId != "") {
            return Tulo_Payway_API_SSO2::SESSION_ESTABLISHED_STATUS_COLD;
        }
        return Tulo_Payway_API_SSO2::SESSION_ESTABLISHED_STATUS_NOEXIST;
    }        

    protected function identify_session() {
        $this->common->write_log("[identify_session]");

        if ($this->isBot()) {
            $this->common->write_log("bot detected, skipping identify!");
            return;
        }

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
             "exp" => $time + 60,
             "iat" => $time
        );

        $this->common->write_log("identify payload:");
        $this->common->write_log($payload);

        $token = JWT::encode($payload, $client_secret, 'HS256');
        $protocol = isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        $continueUrl = sprintf("%s://%s%s", $protocol, $_SERVER["HTTP_HOST"], $_SERVER["REQUEST_URI"]);
        $this->common->write_log(("Redirect url: ".$continueUrl));

        $url = sprintf("%s?t=%s&r=%s", $url, $token, $continueUrl);
        header("Location: ".$url);
        die();
    }

    private function isBot() {
        $crawlers = "alexa|bot|crawl(er|ing)|facebookexternalhit|feedburner|google web preview|nagios|postrank|pingdom|slurp|spider|yahoo!|yandex";
        $pattern = "/".$crawlers."/i";
        $agent = $_SERVER['HTTP_USER_AGENT'];
        if ( preg_match($pattern, $agent) ) {
            return true;
        }    
        return false;    
    }

    protected function refresh_session() {
        $this->common->write_log("[refresh_session]");            

        $url = $this->get_sso2_url("sessionstatus");
        $client_id = get_option('tulo_server_client_id');
        $client_secret = get_option('tulo_server_secret');
        $organisation_id = get_option('tulo_organisation_id');
        $ip_address = $_SERVER ['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $lks = $this->get_session_status();

        $time = time();
        $payload = array(
            "cid" => $client_id,
            "iss" => $organisation_id,
            "sid" => $this->sso_session_id(),
            "ipa" => $ip_address,
            "uas" => $user_agent,
            "lks" => $lks,
            "aud" => "pw-sso",
            "nbf" => $time,
            "exp" => $time + 10,
            "iat" => $time
        );
                
        $this->common->write_log("sessionstatus payload:");
        $this->common->write_log($payload);

        $token = JWT::encode($payload, $client_secret, 'HS256');
        $payload = json_encode(array("t" => $token));

        $response = $this->common->post_json_jwt($url, $payload);
        if ($response["status"] == 200) {
            $this->common->write_log("successful sessionstatus request, token:");
            $data = json_decode($response["data"]);
            $this->common->write_log($data->t);
            
            $decoded = $this->decode_token($data->t, $client_secret);
            if ($decoded == null) {
                return;
            }

            $this->common->write_log("session status: <".$decoded->sts. "> at: ".$decoded->at);
            if ($decoded->sts == "terminated" || $decoded->err == "session_not_found") {
                $this->common->write_log("session terminated in other window or expired, logging out user locally also and establishing new session");
                $this->logout_user(false);
                $this->identify_session();  // Re-establish session after logout

            } else if ($decoded->sts == "loggedin") {
                $this->register_basic_session($decoded);    
                if ($lks == "anon" || $lks == "terminated") {
                    if ($decoded->at != "") {
                        //$this->fetch_user_and_login($decoded->at);
                    } else {
                        // No "at" available at this time, let's do another "identify" session call
                        $this->identify_session();
                    }                    
                } else if ($lks == "loggedin" && !$this->is_logged_in()) {
                    $this->identify_session();
                }
            }
        } else {
            $this->common->write_log("[ERROR] error posting session status!");
            $this->common->write_log($response);
        }
    }

    protected function authenticate_user($email, $password) {
        $this->common->write_log("[authenticate_user]");
        $this->common->write_log("email: ".$email);
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

        $token = JWT::encode($payload, $client_secret, 'HS256');
        $payload = json_encode(array("t" => $token));

        $response = $this->common->post_json_jwt($url, $payload);
        $status = array();
        if ($response["status"] == 200) {
            $data = json_decode($response["data"]);
            $decoded = $this->decode_token($data->t, $client_secret);
            if ($decoded == null) {
                $status["success"] = false;
                $status["error_code"] = "jwt_decode_failed";                
            } else {
                $status["success"] = $decoded->sts=="loggedin";
                if ($decoded->sts == "loggedin") {
                    $this->common->write_log("User successfully authenticated!");
                    $this->register_basic_session($decoded);
                    $status["name"] = $this->get_user_name();
                    $status["email"] = $this->get_user_email();
                    $status["customer_number"] = $this->get_user_customer_number();
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
        }
        else {
            $data = json_decode($response["data"]);
            $decoded = $this->decode_token($data->t, $client_secret);
            $this->common->write_log("!! Error authenticating: ".$response["status"]);
            $this->common->write_log($decoded);
            if ($decoded == null) {
                $status["success"] = false;
                $status["error_code"] = "jwt_decode_error";    
            } else {
                $status["success"] = false;
                $status["error_code"] = "unknown_error";    
            }
        }
        $this->common->write_log("Response authentication:");
        $this->common->write_log($status);
        return $status;

    }
    
    protected function logout_user($locallyInitiated=true) {
        $this->common->write_log("[logout_user]");
        $this->common->write_log("locallyInitiated: ".$locallyInitiated);

        if ($locallyInitiated) {
            // Terminate session in Payway
            $this->sso_logout();
        }
        // Terminate locally
        $this->set_user_active_products(array());
        $this->set_user_email(null);
        $this->set_user_name(null);
        $this->set_user_customer_number(null);
        $this->set_user_id(null);
        $_SESSION[$this->sso_session_established_key] = null;
        $_SESSION[$this->sso_session_id_key] = null;
        $_SESSION[$this->sso_session_status_key] = null;
        setcookie('tpw_id', null, -1, '/');
        return true;
    }

    protected function decode_token($token, $client_secret) {
        try {
            JWT::$leeway = 120;
            $decoded = JWT::decode($token, new Key($client_secret, "HS256"));
            return $decoded;
        } catch(Firebase\JWT\BeforeValidException $e) {
            $this->common->write_log("[ERROR] could not decode JWT from Payway! Message: ".$e->getMessage());
            return null;
        }                
    }

    /** Private functions */


    private function sso_session_id() {
        if (isset($_SESSION[$this->sso_session_id_key])) {
            return $_SESSION[$this->sso_session_id_key];
        }
        
        $sessionId = $this->get_session_id_from_cookie();         
        if ($sessionId != "") {
            $this->common->write_log("sso session id not found in session but found in cookie");
        }
        return $sessionId;
    }

    private function sso_logout() {
        $this->common->write_log("[sso_logout");

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

        $token = JWT::encode($payload, $client_secret, 'HS256');
        $payload = json_encode(array("t" => $token));
        $response = $this->common->post_json_jwt($url, $payload);
        if ($response["status"] == 200) {
            $this->common->write_log("Logout successful");
        } else {
            $this->common->write_log("Logout failed. (".$response["status"].") '".$response["error"]."'");
        }
    }

    /**
     * New function for refreshing user info.
     * Place holder code, not yet completed and usable.
     */
    private function refresh_user() {
        $this->common->write_log("Refreshing user and product info");
        $data = $this->api->get_user_and_products();
        if ($data != null) {
            $this->set_user_id($data["user"]->id);
            $this->set_user_name($data["user"]->first_name." ".$data["user"]->last_name);
            $this->set_user_email($data["user"]->email);
            $this->set_user_customer_number($data["user"]->customer_number);
            $this->set_user_active_products($data["active_products"]);
            $this->set_session_loggedin();
        } else {
            $this->common->write_log("!! Could not get user and product info from Payway!");
        }
    }

    private function fetch_user_and_login($auth_ticket) {
        $data = $this->api->get_user_and_products_by_ticket($auth_ticket);
        if ($data != null) {
            $this->set_user_id($data["user"]->id);
            $this->set_user_name($data["user"]->first_name." ".$data["user"]->last_name);
            $this->set_user_email($data["user"]->email);
            $this->set_user_customer_number($data["user"]->customer_number);
            $this->set_user_active_products($data["active_products"]);
            $this->set_session_loggedin();
        } else {
            $this->common->write_log("!! Could not get user and product info from Payway!");
        }
    }

    private function set_user_id($accountId) {
        $_SESSION[$this->sso_session_user_id_key] = $accountId;
    }

    private function set_user_name($name) {
        $_SESSION[$this->sso_session_user_name_key] = $name;
    }

    private function set_user_email($email) {
        $_SESSION[$this->sso_session_user_email_key] = $email;
    }
    
    private function set_user_customer_number($customer_number) {
        $_SESSION[$this->sso_session_user_custno_key] = $customer_number;
    }

    private function set_user_active_products($products) {
        $_SESSION[$this->sso_session_user_active_products_key] = $products;
    }

    private function set_session_loggedin() {
        $_SESSION[$this->sso_session_established_key] = time();
        $unique_id = base64_encode($this->sso_session_id() .'^'. (string)microtime());
        setcookie("tpw_id", $unique_id, strtotime('+30 days'), '/');
    }

    private function get_session_id_from_cookie() {
        if (isset($_COOKIE["tpw_id"]) && $_COOKIE["tpw_id"] != "")  {
            $data = base64_decode($_COOKIE["tpw_id"]);
            if (str_contains($data, "^")) {
                $vals = explode("^", $data);
                if (count($vals) == 2) {
                    return $vals[0];
                }    
            }
            else {
                if (isset($_SESSION[$this->sso_session_id_key])) {
                    $sessionId = $_SESSION[$this->sso_session_id_key];
                    if ($sessionId != "") {
                        $unique_id = base64_encode($sessionId .'^'. (string)microtime());
                        setcookie("tpw_id", $unique_id, strtotime('+30 days'), '/');            
                        return $sessionId;
                    }
                }                
            }
        }
        return "";
    }

    private static function get_sso2_url($path) {       
        $url = "";
        if (get_option('tulo_environment') == 'prod') {
            $url = "https://sso.worldoftulo.com";
        }
        else {
            $url = "https://payway-sso-stage.azurewebsites.net";
        }
        return sprintf("%s/%s", $url, $path);    
    }

}

?>