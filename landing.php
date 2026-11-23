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

// Only redirect back to this site. identify_session() builds the return url from the
// current host, which a site can change with the "tulo_sso_redirect_host" filter, so that
// host is allowed as well. Anything else (other hosts, "//host", non-http schemes) falls
// back to the front page, so landing.php cannot be used as an open redirect.
$allowed_redirect_hosts = function ($hosts) {
    $candidates = array(
        home_url(),
        site_url(),
        "http://" . apply_filters("tulo_sso_redirect_host", isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : ""),
    );
    foreach ($candidates as $candidate) {
        $host = parse_url($candidate, PHP_URL_HOST);
        if (is_string($host) && $host !== "") {
            $hosts[] = strtolower($host);
        }
    }
    return array_unique($hosts);
};
add_filter('allowed_redirect_hosts', $allowed_redirect_hosts);
$requested_url = is_string($redirect_url) ? $redirect_url : "";
$fallback_url = home_url('/');
$redirect_url = $requested_url === "" ? $fallback_url : wp_validate_redirect($requested_url, $fallback_url);
remove_filter('allowed_redirect_hosts', $allowed_redirect_hosts);
if ($redirect_url === $fallback_url && $requested_url !== $fallback_url) {
    write_log("Return url rejected, not on this site: ".$requested_url);
}

try
{
    $payload = $session->decode_jwt($token, $client_secret);
    if (isset($payload)) {             
        if (isset($payload->err)) {
            write_log("Error in JWT from Payway! Message: ".$payload->err);
            $session->register_session_error($payload->err);
            if (strpos($redirect_url, "tpw") === false) {
                if (strpos($redirect_url, "?") === false) {
                    $redirect_url .= "?tpw=".time();
                } else {
                    $redirect_url .= "&tpw=".time();
                }
            }        
        } else {       
            write_log("Decode OK, payload: ".json_encode($payload));     
            $session->register($payload);
        }
    }
    write_log("Redirecting to: ".$redirect_url);
    header("Location: ".$redirect_url);    
} catch(Exception $e) {
    // we land here if the JWT token can not be decoded properly, in this case some claims have expired.    
    write_log("Could not decode JWT from Payway! Message: ".$e->getMessage());
    write_log("Token: ".$token);
}

die();


?>
