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
    const SESSION_COOKIE_NAME = "tpw_sso";

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
            $this->set_sso_session_cookie($sso_payload->sid, $sso_payload->sts);
            $this->set_sso_session_established_cookie();

            if ($sso_payload->sts == "loggedin" && $sso_payload->at != "") {
                $this->fetch_user_and_login($sso_payload->at);
            }
        } else {
            $this->common->write_log("no sso payload to register");
        }
    }
    
    protected function session_needs_refresh() {
        $data = $this->get_sso_session_time();
        if ($data != null) {
            $established = $data->established;
            $now = time();
            $diff = (time()-$established);
            $session_timeout = get_option('tulo_session_refresh_timeout');
            $this->common->write_log("established: ".$established." now: ".$now." diff: ".$diff." timeout: ".$session_timeout);
            if ($diff > $session_timeout)
                return true;
            
            return false;
        }
        return true;
        /*
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
        */
    }

    protected function is_session_logged_in() {
        //$this->common->write_log("[is_session_logged_in]");
        if ($this->session_established()) {
            //$this->common->write_log("[is_session_logged_in] session established");
            //$this->common->write_log("[is_session_logged_in] user name: ".$this->get_user_name());
            //$this->common->write_log("[is_session_logged_in] email: ". $this->get_user_email());
            if ($this->get_user_name() !="" || $this->get_user_email() != "") {
                return true;
            }
        }
        return false;
    }

    protected function get_session_status() {
        $session_data = $this->get_session_data();
        if ($session_data != null) {
            return $session_data->sts;
        }
        //if (isset($_SESSION[$this->sso_session_status_key]))
        //    return $_SESSION[$this->sso_session_status_key];
        return "anon";            
    }

    protected function get_session_user_id() {
        $session_data = $this->get_session_data();
        if ($session_data != null && isset($session_data->account_id)) {
            return $session_data->account_id;
        }
        //if (isset($_SESSION[$this->sso_session_user_id_key]))
        //    return $_SESSION[$this->sso_session_user_id_key];
    }

    protected function get_session_user_name() {
        $session_data = $this->get_session_data();
        if ($session_data != null && isset($session_data->username)) {
            return $session_data->username;
        }
        //if (isset($_SESSION[$this->sso_session_user_name_key]))
        //    return $_SESSION[$this->sso_session_user_name_key];
    }

    protected function get_session_user_email() {
        $session_data = $this->get_session_data();
        if ($session_data != null && isset($session_data->email)) {
            return $session_data->email;
        }
        //if (isset($_SESSION[$this->sso_session_user_email_key]))
        //    return $_SESSION[$this->sso_session_user_email_key];
    }

    protected function get_session_user_customer_number() {
        $session_data = $this->get_session_data();
        if ($session_data != null) {
            return $session_data->customer_number;
        }
        //if (isset($_SESSION[$this->sso_session_user_custno_key]))
        //    return $_SESSION[$this->sso_session_user_custno_key];
    }

    protected function get_session_user_active_products() {
        $session_data = $this->get_session_data();
        if ($session_data != null) {
            return $session_data->active_products;
        }
        //if (isset($_SESSION[$this->sso_session_user_active_products_key]))
        //    return $_SESSION[$this->sso_session_user_active_products_key]; 
        return null;
    }

    public function should_request_be_excepted() {        

        if (isset($_SERVER["HTTP_PURPOSE"]) && $_SERVER["HTTP_PURPOSE"] == "prefetch") {
            return true;
        }

        if ($this->isBot()) {
            $this->common->write_log("bot detected, request excepted!");
            return true;
        }

        //$this->common->write_log("SERVER: ".print_r($_SERVER, true));

        $except_ip = false;
        $whitelisted_ips = Tulo_Payway_Server_Public::get_whitelisted_ips();
        if (in_array($_SERVER['REMOTE_ADDR'], $whitelisted_ips, false)) {
            $except_ip = true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($iplist as $ip) {
                if (in_array($ip, $whitelisted_ips, false)) {
                    $except_ip = true;
                }
                if ($except_ip) {
                    break;
                }
            }
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED'])) {
           if (in_array($_SERVER['HTTP_X_FORWARDED'], $whitelisted_ips, false)) {
                $except_ip = true;
           }
        }
   
        if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            if (in_array($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'], $whitelisted_ips, false)) {
                $except_ip = true;
            }          
        }

        if (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            if (in_array($_SERVER['HTTP_FORWARDED_FOR'], $whitelisted_ips, false)) {
                $except_ip = true;
            }
        } 
  
        if (!empty($_SERVER['HTTP_FORWARDED'])) {
            if (in_array($_SERVER['HTTP_FORWARDED'], $whitelisted_ips, false)) {
                $except_ip = true;

            }        
        }

        if ($except_ip) {
            $this->common->write_log("IP match, excepting this request from SSO.");
            return true;
        }

        // check header?
        if (get_option('tulo_except_header_name') != "") {
            $header = get_option('tulo_except_header_name');
            $value = get_option('tulo_except_header_value');
            if (isset($_SERVER[$header])) {
                if ($_SERVER[$header] == $value) {
                    $this->common->write_log("Header value match, excepting this request from SSO.");
                    return true;
                }
            } 
        }
    
        return false;
    }

    protected function session_established() {

        $session_data = $this->get_session_data();
        if ($session_data != null) {            
            return Tulo_Payway_API_SSO2::SESSION_ESTABLISHED_STATUS_WARM;
        }     
        if (isset($_SESSION[$this->sso_session_id_key]) && $_SESSION[$this->sso_session_id_key] != "")
            return Tulo_Payway_API_SSO2::SESSION_ESTABLISHED_STATUS_WARM;

        $cookieSessionId = $this->get_session_id_from_cookie();   
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
        $this->common->write_log("identify token: ".$token);
        $protocol = isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        $continueUrl = sprintf("%s://%s%s", $protocol, $_SERVER["HTTP_HOST"], $_SERVER["REQUEST_URI"]);
        
        $filteredUrl = $this->rewriteContinueUrl($continueUrl);
        $this->common->write_log(("Redirect url: ".$filteredUrl));

        $url = sprintf("%s?t=%s&r=%s", $url, $token, $filteredUrl);
        header("Location: ".$url);
        die();
    }

    private function rewriteContinueUrl($url) {
        if (strpos($url, "tpw_session_refresh") !== false) {
            $this->common->write_log("Continue url contains tpw_session_refresh, removing it");
            $url = $this->removeParamFromUrl($url, "tpw_session_refresh");
            if (strpos($url, "?") === false) {
                $url .= "?tpw=".time();
            } else {
                $url .= "&tpw=".time();
            }

        }
        return $url;
    }

    private function removeParamFromUrl($url, $param) {
        $url = preg_replace('/(&|\?)'.preg_quote($param).'=[^&]*$/', '', $url);
        $url = preg_replace('/(&|\?)'.preg_quote($param).'=[^&]*&/', '$1', $url);
        return $url;
    }

    private function isBot() {
        $crawlers = "alexa|bot|crawl(er|ing)|facebookexternalhit|feedburner|google web preview|nagios|postrank|pingdom|slurp|spider|yahoo!|yandex|ias-or|integralads|verity";
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

            // Update session time
            $this->set_sso_session_time();
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
        $this->delete_cookie('tpw_id', $httponly=false);
        $this->delete_cookie(Tulo_Payway_API_SSO2::SESSION_COOKIE_NAME);
        $this->delete_cookie('tpw_session_established');
        $this->delete_cookie('tpw_session_error');
        $this->delete_cookie('tpw_sso_session_time');
        setcookie('tpw_id', null, -1, '/');
        setcookie(Tulo_Payway_API_SSO2::SESSION_COOKIE_NAME, null, -1, '/');
        setcookie('tpw_session_established', null, -1, '/');
        setcookie('tpw_session_error', null, -1, '/');
        setcookie('tpw_sso_session_time', null, -1, '/');
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

        $session_data = $this->get_session_data();
        if ($session_data != null) {
            $this->common->write_log("[SESSIONID] sso session id found in cookie session data: ".$session_data->sid);
            return $session_data->sid;
        }

        if (isset($_SESSION[$this->sso_session_id_key])) {
            $this->common->write_log("[SESSIONID] sso session id found in SESSION session data: ".$_SESSION[$this->sso_session_id_key]);
            return $_SESSION[$this->sso_session_id_key];
        }
        
        $sessionId = $this->get_session_id_from_cookie();         
        if ($sessionId != "") {
            $this->common->write_log("[SESSIONID] sso session id not found in session but found in cookie: ".$sessionId);
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
            $this->write_session_data();
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
            $this->write_session_data();
            
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

    private function set_sso_session_established_cookie() {
        $cookie_data = $this->get_cookie("tpw_session_established");
        if ($cookie_data != null) {
            $this->common->write_log("Session established cookie already set, not setting again");
            return;
        }
        $this->set_cookie('tpw_session_established', 1, time() + 60*60*24*30, $encode=false);
        setcookie('tpw_session_error', null, -1, '/');
    }

    private function set_sso_session_cookie($sid, $sts) {
        $cookie_data = $this->get_session_data();
        if ($cookie_data != null && isset($cookie_data->sid) && $cookie_data->sid == $sid) {
            return;
        }
        $data = json_encode(["sid" => $sid, "sts" => $sts]);
        $this->common->write_log("Setting initial sso cookie: ".$data);
        $this->set_cookie(Tulo_Payway_API_SSO2::SESSION_COOKIE_NAME, $data);
        $_SESSION["initial_sso_data"] = $data;
        $this->set_sso_session_time();
    }

    private function set_session_loggedin() {
        $_SESSION[$this->sso_session_established_key] = time();
        $unique_id = $this->sso_session_id() .'^'. (string)microtime();
        $this->set_cookie("tpw_id", $unique_id, strtotime('+30 days'), $encode=true, $httponly=false);
        //setcookie("tpw_id", $unique_id, strtotime('+30 days'), '/');
    }


    private function get_session_id_from_cookie() {
        $cookie_data = $this->get_cookie("tpw_id");
        if ($cookie_data != null) {
            if (str_contains($cookie_data, "^")) {
                $vals = explode("^", $cookie_data);
                if (count($vals) == 2) {
                    return $vals[0];
                }    
            }
        }
        /*
        $session_data = $this->get_session_data();
        if ( $session_data != null) {            
            $this->common->write_log("Session data found in session cookie, id: ".$session_data->sid);
            return $session_data->sid;
        }
        */

        return "";
    }

    private function write_session_data() {
        $this->common->write_log("[write_session_data]");
        $session_data = $this->get_session_data();
        if ($session_data != null) {
            $session_data->account_id = isset($_SESSION[$this->sso_session_user_id_key]) ? $_SESSION[$this->sso_session_user_id_key] : "";
            $session_data->username = isset($_SESSION[$this->sso_session_user_name_key]) ? $_SESSION[$this->sso_session_user_name_key] : "";
            $session_data->email = isset($_SESSION[$this->sso_session_user_email_key]) ? $_SESSION[$this->sso_session_user_email_key] : "";
            $session_data->customer_number = isset($_SESSION[$this->sso_session_user_custno_key]) ? $_SESSION[$this->sso_session_user_custno_key] : "";
            $session_data->active_products = isset($_SESSION[$this->sso_session_user_active_products_key]) ? $_SESSION[$this->sso_session_user_active_products_key] : "";
            if ($session_data->account_id != "") {
                $session_data->sts = "loggedin";
            } else {
                $session_data->sts = "anon";
            }

            $current_data = $this->get_session_data();
            $this->common->write_log("Current session data: ".json_encode($current_data));
            $this->common->write_log("New session data: ".json_encode($session_data));
            if ($current_data == $session_data) {
                $this->common->write_log("Session data unchanged, not writing to cookie");
                return;
            }

            $this->common->write_log("!! Writing session data: ".json_encode($session_data));
            $this->set_cookie(Tulo_Payway_API_SSO2::SESSION_COOKIE_NAME, json_encode($session_data));
        }
    }

    private function get_session_data() {
        $data = $this->get_cookie(Tulo_Payway_API_SSO2::SESSION_COOKIE_NAME);
        if ($data != null)  {
            $data = json_decode($data);
            if (isset($data->sts))
                return $data;
            else {
                $this->common->write_log("Session data corrupted, resetting session data");
                $this->delete_cookie(Tulo_Payway_API_SSO2::SESSION_COOKIE_NAME);
                return null;
            }
        }
        if (isset($_SESSION["initial_sso_data"])) {
            $data = json_decode($_SESSION["initial_sso_data"]);
            return $data;
        }
        return null;
    }

    private function set_sso_session_time() {
        $data = ["established" => time()];
        $this->set_cookie("tpw_sso_session_time", json_encode($data));
    }

    protected function set_session_error($error) {
        $data = ["error" => $error];
        $this->common->write_log("!! Got token error establishing session: ".$error);
        $this->set_cookie("tpw_session_error", json_encode($data), time() + 60);
        // also remove other cookies that might have been previously set.
        $this->delete_cookie("tpw_session_established");
        $this->delete_cookie("tpw_sso");
        $this->delete_cookie("tpw_id");
        $this->delete_cookie("tpw_sso_session_time");
    }

    public function has_session_error() {
        $data = $this->get_cookie("tpw_session_error");
        if ($data != null) {
            return true;
        }
        return false;
    }

    private function get_sso_session_time() {
        return json_decode($this->get_cookie("tpw_sso_session_time"));
    }


    public function get_cookie($cookie) {
        if (isset($_COOKIE[$cookie]))  {
            $data = base64_decode($_COOKIE[$cookie]);
            return $data;
        }
        return null;
    }

    public function set_cookie($cookie_name, $data, $expire = "", $encode = true, $httponly = true) {
        if ($expire == "") {
            $expire = time() + 60*60*24*30;
        }

        if ($encode) {
            $cookie_data = base64_encode($data);
        } else {
            $cookie_data = $data;
        }

        $domain = get_option('tulo_cookie_domain');
        if (!isset($domain)) {
            $domain = "";
        }

        $secure = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $secure = true;
        }                

        $_COOKIE[$cookie_name] = $cookie_data;
        setcookie($cookie_name, $cookie_data, $expire, '/', $domain, $secure, $httponly);        
    }

    public function delete_cookie($cookie_name, $httponly=true) {
        $domain = get_option('tulo_cookie_domain');
        if (!isset($domain)) {
            $domain = "";
        }

        $secure = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $secure = true;
        }                
        unset($_COOKIE[$cookie_name]);
        setcookie($cookie_name, null, -1, '/', $domain, $secure, $httponly);
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