<?php
/*
 * This file serves to logout an already loggedin user and redirect to the login page with a specific continue url that 
 * the user should land on after login.
 * 
 * If no redirect_url is specified, the user will be redirected to the start page of the site after login.
 * 
 * Params:  
 * r - the url to redirect to after login
 */

function write_log($log) {
    $debug_active = get_option('tulo_debug_log_active');
    if (true === WP_DEBUG && $debug_active == "on") {
            if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        } else {
            error_log("[SSO2][LOGOUTCONTINUE] ".$log);
        }
    }
}

$baseurl = explode( "wp-content" , $_SERVER['SCRIPT_FILENAME'] );
$baseurl = $baseurl[0];
require_once( $baseurl . "wp-load.php" );

if (isset($_SERVER['HTTPS']) &&
    ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
    isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
    $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
  $protocol = 'https://';
}
else {
  $protocol = 'http://';
}

if ($_SERVER['SERVER_PORT'] == 80 || $_SERVER['SERVER_PORT'] == 443) {
    $port = "";
} else {
    $port = ":".$_SERVER['SERVER_PORT'];
}
$redirect_url = isset($_GET["r"]) ? $_GET["r"] : $protocol . $_SERVER['SERVER_NAME'].$port."/";

$tulo = new Tulo_Payway_Server_Common();
$login_url = $tulo->get_authentication_url($redirect_url);

$session = new Tulo_Payway_Session();
$session->logout(true);

write_log("Redirecting to: ".$login_url);
header("Location: ".$login_url);    
die();
?>