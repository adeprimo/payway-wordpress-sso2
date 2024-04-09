<?php

function write_log($log) {
    if (true === WP_DEBUG) {
        if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        } else {
            error_log("[SSO2][Landing] ".$log);
        }
    }
}

$token = $_POST["jwtToken"];
if (!isset($token)) {
    write_log("No token available!");
    die("No token available!");
}

$baseurl = explode( "wp-content" , $_SERVER['SCRIPT_FILENAME'] );
$baseurl = $baseurl[0];
require_once( $baseurl . "wp-load.php" );

use \Firebase\JWT\JWT;


try
{
    $client_secret = get_option('tulo_paywall_secret');
    $session = new Tulo_Payway_Session();
    $payload = $session->decode_jwt($token, $client_secret);
    if (isset($payload)) {        
        $session->process_checkout_landing($payload);
    }

} catch(Firebase\JWT\ExpiredException $e) {
    // we land here if the JWT token can not be decoded properly, in this case some claims have expired.
    write_log("Could not decode JWT from Payway! Message: ".$e->getMessage());
}

die();


?>
