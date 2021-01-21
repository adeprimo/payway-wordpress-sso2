<?php
use \Firebase\JWT\JWT;

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Tulo_Payway_Server
 * @subpackage Tulo_Payway_Server/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Tulo_Payway_Server
 * @subpackage Tulo_Payway_Server/includes
 * @author     Your Name <email@example.com>
 */
class Tulo_Payway_Server {


     public static $post_meta_key = 'tulo_restrictions';
     /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Tulo_Payway_Server_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
     protected $loader;


     public $plugin_name = 'payway-wordpress-sso2';

     /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
     protected $version;

     protected static $_instance = null;
     /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
     public function __construct() {

          $this->plugin_name = 'payway-wordpress-sso2';
          $this->version = '1.0.0';

          $this->load_dependencies();
          $this->set_locale();
          $this->define_admin_hooks();
          $this->define_public_hooks();
          $this->auto_login();
     }

     public static function instance() {
          if ( is_null( self::$_instance ) ) {
               self::$_instance = new self();
          }
          return self::$_instance;
     }

     /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Tulo_Payway_Server_Loader. Orchestrates the hooks of the plugin.
     * - Tulo_Payway_Server_i18n. Defines internationalization functionality.
     * - Tulo_Payway_Server_Admin. Defines all hooks for the admin area.
     * - Tulo_Payway_Server_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
     private function load_dependencies() {

          /**
           * The class responsible for orchestrating the actions and filters of the
          * core plugin.
          */
          require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tulo-payway-loader.php';

          /**
           * The class responsible for defining internationalization functionality
          * of the plugin.
          */
          require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tulo-payway-i18n.php';

          /**
           * The class responsible for defining all actions that occur in the admin area.
          */
          require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-tulo-payway-admin.php';

          /**
           * Load other dependencies
           */
          require_once plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/autoload.php';

          /**
           * Common functions
           */
          require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tulo-payway-common.php';

          /**
           * The class responsible for defining all actions that occur in the public-facing
          * side of the site.
          */
          require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-tulo-payway-public.php';
          /**
           * API library
           */
          require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tulo-payway-api.php';
          /**
           * SSO2 library
           */
          require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tulo-payway-sso2.php';

          $this->loader = new Tulo_Payway_Server_Loader();

     }

     /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Tulo_Payway_Server_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
     private function set_locale() {

          $plugin_i18n = new Tulo_Payway_Server_i18n();
          $plugin_i18n->set_domain( 'tulo' );

          $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

     }

     public static function get_available_products()
     {
          $value = get_option('tulo_products');
          if($value == null)
          {
               return array();
          }
          if(!is_array($value))
          {
               return array();
          }

          return $value;
     }




     /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
     private function define_admin_hooks() {

          $plugin_admin = new Tulo_Payway_Server_Admin( $this->get_version() );
          $this->loader->add_action( 'admin_menu', $plugin_admin, 'init_admin');
          $this->loader->add_action( 'add_meta_boxes', $plugin_admin, 'add_meta_boxes' );
          $this->loader->add_action( 'save_post', $plugin_admin, 'save_post' );

     }

     /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
     private function define_public_hooks() {

          $plugin_public = new Tulo_Payway_Server_Public( $this->get_version() );
          $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
          $this->loader->add_action( 'init', $plugin_public, 'register_session');
          $this->loader->add_action( 'wp', $plugin_public, 'check_session');
          $this->loader->add_filter('the_content', $plugin_public, 'content_filter' );
          $this->loader->add_filter('post_class', $plugin_public, 'post_class_filter' );

          $this->loader->add_shortcode('tulo_debug_output', $plugin_public, 'debug_output' );
          $this->loader->add_shortcode('tulo_permission_required', $plugin_public, 'shortcode_permission_required' );
          $this->loader->add_shortcode('tulo_buy_button', $plugin_public, 'shortcode_buy_button' );
          $this->loader->add_shortcode('tulo_product_link', $plugin_public, 'shortcode_product_link' );



          $this->loader->add_action( 'wp_ajax_tulo_getproducts', $plugin_public, 'ajax_list_products', 1 );
          $this->loader->add_action( 'wp_ajax_nopriv_tulo_login', $plugin_public, 'ajax_login', 1 );
          $this->loader->add_action( 'wp_ajax_tulo_login', $plugin_public, 'ajax_login', 1 );
          $this->loader->add_action( 'wp_ajax_nopriv_tulo_logout', $plugin_public, 'ajax_logout', 1 );
          $this->loader->add_action( 'wp_ajax_tulo_logout', $plugin_public, 'ajax_logout', 1 );

     }

     /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
     public function run() {
          $this->loader->run();
     }

     /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
     public function get_plugin_name() {
          return $this->plugin_name;
     }

     /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Tulo_Payway_Server_Loader    Orchestrates the hooks of the plugin.
     */
     public function get_loader() {
          return $this->loader;
     }

     /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
     public function get_version() {
          return $this->version;
     }

     /**
      * If user has chosen to be remembered, then we can re-use the refresh token by using the stored email address
      */
     private function auto_login() {
          if (empty($_SESSION['username']) && isset($_COOKIE['tpw_id'])) {
               $email = get_transient( $_COOKIE['tpw_id'] );

               if (!empty($email)) {
                    $_SESSION['username'] = $email;
               }
          }

          return true;
     }

     private static function get_environment_url() {
          $url = $_SESSION['environment_url'];

          if (empty($url)) {
               $environment = get_option('tulo_environment');

               if ($environment == 'prod') {
                    $url = 'https://backend.worldoftulo.com';
               } else {
                    $url = 'https://payway-api.stage.adeprimo.se';
               }

               $_SESSION['environment_url'] = $url;
          }

          return $url;
     }
     

     private function get_access_token() {
          if ($_SESSION['app_access_token_timeout'] && $_SESSION['app_access_token_timeout'] <= time()) {
               unset($_SESSION['app_access_token']);
          }

          $access_token = $_SESSION['app_access_token'];

          if (empty($access_token)) {
               global $wpdb;

               $access_token = get_transient( 'access_token' );

               if (empty($access_token)) {
                    $output = $this->create_application_access_token();
                    $access_token = $output['data']->access_token;
               } else {
                    $_SESSION['app_access_token'] = $access_token;
               }
          }

          return $access_token;
     }

     private function create_application_access_token() {
          $url = Tulo_Payway_Server::get_environment_url() . '/api/authorization/access_token';
          $client_id = get_option('tulo_server_client_id');
          $client_secret = get_option('tulo_server_secret');

          $ch = curl_init();

          curl_setopt_array($ch, array(
              CURLOPT_URL => $url,
              CURLOPT_RETURNTRANSFER => 1,
              CURLOPT_POSTFIELDS => "client_id=".$client_id."&client_secret=".$client_secret."&grant_type=none&scope=/external/account/r /external/account/w",
              CURLOPT_POST => 1,
              CURLOPT_HTTPHEADER => array(
                  'Accept: application/json',
                  'Content-Type: application/x-www-form-urlencoded'
              )
          ));

          $output = curl_exec($ch);
          $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

          if (curl_errno($ch)) {
              $error = curl_error($ch);
          }

          curl_close($ch);

          $data = json_decode($output);

          if ($httpcode == 200) {
               set_transient( 'access_token', $data->access_token, $data->expires_in - 1 );
               $_SESSION['app_access_token'] = $data->access_token;
               $_SESSION['app_access_token_timeout'] = time() + $data->expires_in;
          }

          $response = array(
               'status' => $httpcode,
               'error' => $error,
               'data' => $data
          );

          return $response;
     }

     private function get_user_access_token() {
          if (isset($_SESSION['access_token_timeout']) && $_SESSION['access_token_timeout'] <= time()) {
               unset($_SESSION['access_token']);
          }

          $email = isset($_SESSION['username']) ? $_SESSION['username'] : null;
          $httpcode = 200;
          $error = null;
          $data = null;

          if (!$email) {
               $httpcode = 400;
               $error = 'Not logged in';
          } else {

               $access_token = isset($_SESSION['access_token']) ? $_SESSION['access_token'] : null;

               if (!$access_token) {
                    global $wpdb;

                    $access_token = get_transient( 'access_token_' . $email );

                    if (!$access_token) {
                         $refresh_response = $this->refresh_user_access_token( $email );

                         $httpcode = $refresh_response['status'];
                         $error = $refresh_response['error'];
                         $data = $refresh_response['data'];

                         if ($httpcode) {
                              $access_token = $data->access_token;

                              $_SESSION['access_token'] = $access_token;
                              $_SESSION['access_token_timeout'] = time() + $data->expires_in;
                              set_transient( 'access_token_' . $email, $access_token, $data->expires_in - 1 );
                         }
                    } else {
                         $_SESSION['access_token'] = $access_token;

                         $data = array(
                              'access_token' => $access_token
                         );
                    }
               } else {
                    $data = array(
                         'access_token' => $access_token
                    );
               }
          }

          $response = array(
               'status' => $httpcode,
               'error' => $error,
               'data' => $data
          );

          return $response;
     }

     private function refresh_user_access_token( $email ) {
          $url = Tulo_Payway_Server::get_environment_url() . '/api/authorization/access_token';
          $client_id = get_option('tulo_server_client_id');
          $client_secret = get_option('tulo_server_secret');
          $organisation_id = get_option('tulo_organisation_id');
          $refresh_token = $_SESSION['refresh_token'];
          $error = null;

          if (!$refresh_token) {
               $refresh_token = get_transient( 'refresh_token_' . $email );
          }

          $ch = curl_init();

          curl_setopt_array($ch, array(
               CURLOPT_URL => $url,
               CURLOPT_RETURNTRANSFER => 1,
               CURLOPT_POSTFIELDS => "client_id=".$client_id."&client_secret=".$client_secret."&grant_type=refresh_token&refresh_token=".$refresh_token,
               CURLOPT_POST => 1,
               CURLOPT_HTTPHEADER => array(
                    'Accept: application/json',
                    'Content-Type: application/x-www-form-urlencoded'
               )
          ));

          $output = curl_exec($ch);
          $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

          $data = json_decode($output);

          if (curl_errno($ch)) {
               $error = curl_error($ch);
          }

          curl_close($ch);

          // Something went wrong, logout user
          if ($httpcode !== 200) {
               $this->logout();
          }

          $response = array(
               'status' => $httpcode,
               'error' => $error,
               'data' => $data
          );

          return $response;
     }

     private function check_auth_attempts( $email ) {
          $access_token = $this->get_access_token();

          $url = Tulo_Payway_Server::get_environment_url() . '/external/api/v1/accounts/get_authentication_attempts?email=' . $email;
          $error = null;

          $ch = curl_init();

          curl_setopt_array($ch, array(
               CURLOPT_URL             => $url,
               CURLOPT_RETURNTRANSFER  => 1,
               CURLOPT_HTTPHEADER      => array(
                    'Accept: application/json',
                    'Content-Type: application/x-www-form-urlencoded',
                    'Authorization: Bearer ' . $access_token
               )
          ));

          $output = curl_exec($ch);
          $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

          $data = json_decode($output);

          if (curl_errno($ch)) {
               $error = curl_error($ch);
          }

          curl_close($ch);

          $response = array(
               'status' => $httpcode,
               'error' => $error,
               'data' => $data
          );

          return $response;
     }


     private function login_user( $email, $password ) {          

          $url = Tulo_Payway_Server::get_sso2_url("authenticate");
          $client_id = get_option('tulo_server_client_id');
          $client_secret = get_option('tulo_server_secret');
          $organisation_id = get_option('tulo_organisation_id');
          $error = null;

          $ch = curl_init();

          curl_setopt_array($ch, array(
               CURLOPT_URL => $url,
               CURLOPT_RETURNTRANSFER => 1,
               CURLOPT_POSTFIELDS => "client_id=".$client_id."&client_secret=".$client_secret."&username=".$organisation_id."^".$email."&password=".$password."&grant_type=password&scope=/external/me/w /external/me/r",
               CURLOPT_POST => 1,
               CURLOPT_HTTPHEADER => array(
                    'Accept: application/json',
                    'Content-Type: application/x-www-form-urlencoded'
               )
          ));

          $output = curl_exec($ch);
          $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

          $data = json_decode($output);

          if (curl_errno($ch)) {
               $error = curl_error($ch);
          }

          curl_close($ch);

          $response = array(
               'status' => $httpcode,
               'error' => $error,
               'data' => $data
          );

          return $response;
     }

     private function send_login_attempt( $email, $success, $persist ) {
          $access_token = $this->get_access_token();
          $url = Tulo_Payway_Server::get_environment_url() . '/external/api/v1/accounts/create_authentication_attempt';
          $user_agent = $_SERVER['HTTP_USER_AGENT'];
          $error = null;

          $ch = curl_init();

          curl_setopt_array($ch, array(
              CURLOPT_URL => $url,
              CURLOPT_RETURNTRANSFER => 1,
              CURLOPT_POSTFIELDS => json_encode(array(
                   "email"              => $email,
                   "login_successful"   => $success,
                   "user_agent"         => $user_agent,
                   "persist"            => $persist
              )),
              CURLOPT_POST => 1,
              CURLOPT_HTTPHEADER      => array(
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token
               )
          ));

          $output = curl_exec($ch);
          $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

          $data = json_decode($output);

          if (curl_errno($ch)) {
              $error = curl_error($ch);
          }

          curl_close($ch);

          $response = array(
               'status' => $httpcode,
               'error' => $error,
               'data' => $data
          );

          return $response;
     }

     public function has_access($post_id = null)
     {
          $plugin_public = new Tulo_Payway_Server_Public( $this->get_version() );
          return $plugin_public->has_access($post_id);
     }

     public function get_user_details() {

          $output = array(
               'status' => 400,
               'error' => null,
               'data' => null
          );

          if (isset($_SESSION['user_details'])) {
               $output['status'] = 200;
               $output['data'] = $_SESSION['user_details'];
          } else {

               $output = $this->get_user_access_token();

               if ($output['status'] == 200) {
                    $url = Tulo_Payway_Server::get_environment_url() . '/external/api/v1/me';

                    $ch = curl_init();

                    curl_setopt_array($ch, array(
                         CURLOPT_URL => $url,
                         CURLOPT_RETURNTRANSFER => 1,
                         CURLOPT_HTTPHEADER => array(
                              'Accept: application/json',
                              'Authorization: Bearer ' . $_SESSION['access_token']
                         )
                    ));

                    $user_details_reponse = curl_exec($ch);
                    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                    $output['status'] = $httpcode;
                    $output['data'] = json_decode($user_details_reponse);

                    if (curl_errno($ch)) {
                         $error = curl_error($ch);
                    }

                    curl_close($ch);

                    $response = $data;

                    if ($httpcode == 200) {
                         $_SESSION['user_details'] = $data;
                    }
               }
          }

          $response = array(
               'status' => $output['status'],
               'error' => $output['error'],
               'data' => $output['data']
          );

          return $response;
     }

     public function get_user_active_products() {

          $output = array(
               'status' => 400,
               'error' => null,
               'data' => null
          );

          if (isset($_SESSION['user_active_products'])) {
               $output['status'] = 200;
               $output['data'] = $_SESSION['user_active_products'];
          } else {
               $sso2 = new Tulo_Payway_API_SSO2();
               // Step 1 - not logged in, first visit
               //   - identify
               // Step 2 - we have a session and can login
               // 
               if (!isset($_SESSION['sso2_session_id'])) {
                    // identify                     
                    //$sso2->identify_session();
               } else {
                    if ($_SESSION['sso2_session_status'] == "loggedin") {                         
                         // get session status and exchange authentication ticket to access-token.
                         // fetch products with access-token
                    } else if ($_SESSION['sso2_session_status'] == "anon") {
                         // login time
                    } else if ($_SESSION['sso2_session_status'] == "terminated") {
                         // Delete all info about user and products since we are logged out elsewhere.
                    }

               }
          }

          $response = array(
               'status' => $output['status'],
               'error' => $output['error'],
               'data' => $output['data']
          );

          return $response;
     }

     public function user_has_subscription() {
          $user_active_products = $this->get_user_active_products();

          return !empty($user_active_products['data']->item->active_products);
     }

     public function login( $email, $password, $persist = false ) {
          // Check if account is locked due to too many failed attempts
          $auth_attempts = $this->check_auth_attempts( $email );
          $login_success = false;

          if ($auth_attempts['status'] == 200) {
               // Account not frozen, attempt to login
               if (!$auth_attempts['data']->item->account_frozen) {

                    $output = $this->login_user( $email, $password );

                    if ($output['status'] == 200) {
                         $login_success = true;

                         $_SESSION['access_token'] = $output['data']->access_token;
                         $_SESSION['access_token_timeout'] = time() + $refresh_response['data']->expires_in;
                         $_SESSION['refresh_token'] = $output['data']->refresh_token;
                         $_SESSION['username'] = $email;

                         // If user want to be remembered across browser sessions, store tokens in transient cache (db)
                         if ($persist) {
                              global $wpdb;

                              $unique_id = base64_encode(session_id() . (string)microtime());
                              $refresh_token_expiration = 60 * 60 * 24 * 180;

                              set_transient('access_token_' . $email, $output['data']->access_token, $output['data']->expires_in - 1);
                              set_transient('refresh_token_' . $email, $output['data']->refresh_token, $refresh_token_expiration);
                              set_transient($unique_id, $email, $refresh_token_expiration);

                              setcookie('tpw_id', $unique_id, strtotime( '+180 days' ), '/');
                         } else {
                              setcookie('tpw_check', 1, strtotime( '+180 days' ), '/');
                         }
                    } else if (strpos($output['data']->error_description, 'Username/password do not match') !== false) {
                         // Return translated error message
                         $output['error'] = __('Wrong username or password', 'tulo');
                    } else {
                         // Unknown error, return general translated error message
                         $output['error'] = __('Something went wrong', 'tulo');
                    }

                    // Send the status of the login attempt to Payway in order to prevent brute force attacks
                    $this->send_login_attempt( $email, $login_success, $persist );
               } else {
                    // Account is frozen, return translated error message
                    $auth_attempts['error'] = __('Account locked until', 'tulo');
                    $output = $auth_attempts;
               }
          } else {
               // Unknown error, return general translated error message
               $auth_attempts['error'] = __('Something went wrong', 'tulo');
               $output = $auth_attempts;
          }

          $response = array(
               'status' => $output['status'],
               'error' => $output['error'],
               'data' => $output['data']
          );

          return $response;
     }

     public function logout() {
          global $wpdb;

          $email = $_SESSION['username'];

          unset($_SESSION['refresh_token']);
          unset($_SESSION['access_token']);
          unset($_SESSION['access_token_timeout']);
          unset($_SESSION['username']);
          unset($_SESSION['user_details']);
          unset($_SESSION['user_active_products']);

          delete_transient($_COOKIE['tpw_id'], $email);
          delete_transient('refresh_token_' . $email);
          delete_transient('access_token_' . $email);

          unset($_COOKIE['tpw_id']);
          setcookie('tpw_id', null, -1, '/');
          unset($_COOKIE['tpw_check']);
          setcookie('tpw_check', null, -1, '/');

          return true;
     }
}

function Tulo_Server() {
     return Tulo_Payway_Server::instance();
}

// Global for backwards compatibility.
$GLOBALS['tulo_server'] = Tulo_Server();
