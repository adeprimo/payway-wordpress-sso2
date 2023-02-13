<?php

/**
 * The public-facing API for the Tulo Payway SSO2 Session functionality.

 */
class Tulo_Payway_Session extends Tulo_Payway_API_SSO2 {

    public function __construct() {
        parent::__construct();
    }

    public function is_logged_in() {
        return $this->is_session_logged_in();
    }

    public function get_user_name() {
        return $this->get_session_user_name();
    }

    public function get_user_email() {
        return $this->get_session_user_email();
    }

    public function get_user_customer_number() {
       return $this->get_session_user_customer_number();
    }

    public function get_user_active_products() {
        return $this->get_session_user_active_products();
    }

    public function user_has_subscription() {
        $products = $this->get_user_active_products();
        return !empty($products);
    }

    public function register($payload) {
        $this->register_basic_session($payload);
    }

    public function established() {
        return $this->session_established();
    }

    public function needs_refresh() {
        return $this->session_needs_refresh();
    }

    public function refresh() {
        $this->refresh_session();
    }

    public function get_status() {
        return $this->get_session_status();
    }

    public function identify() {
        $this->identify_session();
    }

    public function decode_jwt($token, $client_secret) {
        return $this->decode_token($token, $client_secret);
    }
    
    public function authenticate($email, $password) {
        return $this->authenticate_user($email, $password);
    }
    
    public function logout($locallyInitiated=true) {
        $this->logout_user($locallyInitiated);
    }

}

?>
