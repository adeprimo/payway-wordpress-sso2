<?php
use \Firebase\JWT\JWT;

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://example.com
 * @since      1.0.1
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
           * SSO2 API library
           */
          require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tulo-payway-sso2-api.php';
          /**
           * SSO2 session public functions
           */
          require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tulo-payway-sso2-session.php';

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

     public static function get_available_products($includeLoggedIn=true)
     {
          $value = get_option('tulo_products');
          if($value == null)
          {
               return array();
          }
          if(!is_array($value))
          {
               $value = array();
          }

          if ($includeLoggedIn) {
               // Logged in product
               $loggedin = new stdClass();
               $loggedin->productid = "tulo-loggedin";
               $loggedin->label = "Visitor is logged in";
               array_push($value, $loggedin);
          }

          return $value;
     }

     public static function get_available_variables()
     {
          $value = get_option('tulo_paywall_variables');
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
          $this->loader->add_filter( 'the_content', $plugin_public, 'content_filter' );
          $this->loader->add_filter( 'post_class', $plugin_public, 'post_class_filter' );
          $this->loader->add_filter( 'query_vars', $plugin_public, 'tulo_query_vars' );

          $this->loader->add_shortcode( 'tulo_permission_required_loggedin', $plugin_public, 'shortcode_permission_required_loggedin' );
          $this->loader->add_shortcode( 'tulo_permission_required_not_loggedin', $plugin_public, 'shortcode_permission_required_not_loggedin' );
          $this->loader->add_shortcode( 'tulo_buy_button', $plugin_public, 'shortcode_buy_button' );
          $this->loader->add_shortcode( 'tulo_product_link', $plugin_public, 'shortcode_product_link' );
          $this->loader->add_shortcode( 'tulo_user_id', $plugin_public, 'shortcode_loggedin_user_id' );
          $this->loader->add_shortcode( 'tulo_user_name', $plugin_public, 'shortcode_loggedin_user_name' );
          $this->loader->add_shortcode( 'tulo_user_email', $plugin_public, 'shortcode_loggedin_user_email' );
          $this->loader->add_shortcode( 'tulo_user_customer_number', $plugin_public, 'shortcode_loggedin_user_customer_number' );
          $this->loader->add_shortcode( 'tulo_authentication_url', $plugin_public, 'shortcode_authentication_url' );
          $this->loader->add_shortcode( 'tulo_login_logout_link', $plugin_public, 'shortcode_login_logout' );

          $this->loader->add_action( 'wp_ajax_tulo_getproducts', $plugin_public, 'ajax_list_products', 1 );
          $this->loader->add_action( 'wp_ajax_tulo_getvariables', $plugin_public, 'ajax_list_variables', 1 );
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
}

function Tulo_Server() {
     return Tulo_Payway_Server::instance();
}

// Global for backwards compatibility.
$GLOBALS['tulo_server'] = Tulo_Server();
