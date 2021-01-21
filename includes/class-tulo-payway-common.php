<?php

/**
 * Plugin common methods
 */
class Tulo_Payway_Server_Common {


     public function __construct() {

     }

     public static function write_log($log) {
          if (true === WP_DEBUG) {
              if (is_array($log) || is_object($log)) {
                  error_log(print_r($log, true));
              } else {
                  error_log("[SSO2] ".$log);
              }
          }
      }
   
}
