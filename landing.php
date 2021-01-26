<?php

function write_log($log) {
    if (true === WP_DEBUG) {
        if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        } else {
            error_log("[SSO2] ".$log);
        }
    }
}
$baseurl = explode( "wp-content" , __FILE__ );
$baseurl = $baseurl[0];
require_once( $baseurl . "wp-load.php" );

use \Firebase\JWT\JWT;

$token = $_GET["t"];
$redirectUrl = $_GET["r"];
$clientSecret = get_option('tulo_server_secret');
$session = new Tulo_Payway_Session();

try
{
    $payload = JWT::decode($token, $clientSecret, array('HS256'));
    if (isset($payload)) {        
        $session->register($payload);
    }
    header("Location: ".$redirectUrl);    
} catch(Firebase\JWT\ExpiredException $e) {
    // we land here if the JWT token can not be decoded properly, in this case some claims have expired.
    write_log("Got error decoding JWT from Payway!");
    write_log($e);

    // restart the identification flow by going back to where we came from    
    header("Location: ".$redirectUrl);    
}

die();


?>