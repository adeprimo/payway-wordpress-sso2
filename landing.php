<?php
$baseurl = explode( "wp-content" , __FILE__ );
$baseurl = $baseurl[0];
require_once( $baseurl . "wp-load.php" );

use \Firebase\JWT\JWT;

$token = $_GET["t"];
$redirectUrl = $_GET["r"];
$clientSecret = get_option('tulo_server_secret');

$payload = JWT::decode($token, $clientSecret, array('HS256'));

/* 
Check payload
    If logged in:
        Fetch user from Payway and store user-info and products in session
    If anonymous:
        Login user
*/

echo(var_dump($payload));
echo($redirectUrl);
?>