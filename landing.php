<?php


function write_log($log) {
    $debug_active = get_option('tulo_debug_log_active');
    if (true === WP_DEBUG && $debug_active == "on") {
            if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        } else {
            error_log("[SSO2][LANDING] ".$log);
        }
    }
}

$token = $_GET["t"];
$redirect_url = $_GET["r"];

$baseurl = explode( "wp-content" , $_SERVER['SCRIPT_FILENAME'] );
$baseurl = $baseurl[0];
require_once( $baseurl . "wp-load.php" );

use \Firebase\JWT\JWT;

$client_secret = get_option('tulo_server_secret');
$session = new Tulo_Payway_Session();

write_log("Return url: ".$redirect_url);
write_log("Token: ".$token);

try
{
    $payload = $session->decode_jwt($token, $client_secret);
    if (isset($payload)) {             
        if (isset($payload->err)) {
            write_log("Error in JWT from Payway! Message: ".$payload->err);
            $session->register_session_error($payload->err);            
        } else {       
            write_log("Decode OK, payload: ".json_encode($payload));     
            $session->register($payload);
        }
    }
    write_log("Redirecting to: ".$redirect_url);
    header("Location: ".$redirect_url);    
} catch(Firebase\JWT\ExpiredException $e) {
    // we land here if the JWT token can not be decoded properly, in this case some claims have expired.
    write_log("Could not decode JWT from Payway! Message: ".$e->getMessage());
}

die();


?>
