<?php

function write_log($log) {
    if (true === WP_DEBUG) {
        if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        } else {
            error_log($log);
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
$pw = new Tulo_Payway_API_SSO2();

try
{
    $payload = JWT::decode($token, $clientSecret, array('HS256'));
    $pw->write_log("Identify session payload response");
    $pw->write_log($payload);
    if (isset($payload)) {        
        $pw->register_basic_session($payload);
    }
    $pw->write_log("RedirectURL: ".$redirectUrl);    
    header("Location: ".$redirectUrl);    
} catch(Firebase\JWT\ExpiredException $e) {
    $pw->write_log("Got error!");
    $pw->write_log($e);
    echo "<h2>!! ERROR establishing session with Payway!</h2>";
}

die();


?>