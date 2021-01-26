<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Tulo_Payway_Server
 * @subpackage Tulo_Payway_Server/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Tulo_Payway_Server
 * @subpackage Tulo_Payway_Server/public
 * @author     Your Name <email@example.com>
 */
class Tulo_Payway_Server_Public {

    /**
     * The version of this plugin.
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    private $common;
    private $sso;
    /**
     * Initialize the class and set its properties.
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $version ) {

        $this->version = $version;
        $this->common = new Tulo_Payway_Server_Common();
        $this->sso = new Tulo_Payway_API_SSO2();
    }

    public static function get_product_shop_url($product)
    {
        $orgid = get_option('tulo_organisation_id');
        $thisuri = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $thisuri = urlencode($thisuri);
        switch(get_option('tulo_environment'))
        {
            case 'stage':
                return 'https://'.$orgid.'.payway-portal.stage.adeprimo.se/v2/shop/'.$product.'?returnUrl='.$thisuri;
            case 'test':
                return 'https://'.$orgid.'.payway-portal.test.adeprimo.se/v2/shop/'.$product.'?returnUrl='.$thisuri;
            default:
                return 'https://'.$orgid.'.portal.worldoftulo.com/v2/shop/'.$product.'?returnUrl='.$thisuri;
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_style('tulo-admin', plugin_dir_url( __FILE__ ) . 'css/tulo-public.css', array(),  $this->version);
        wp_enqueue_script( Tulo_Payway_Server::instance()->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wp-tulo.js', array(), $this->version, false );

        wp_localize_script( Tulo_Payway_Server::instance()->plugin_name, 'tulo_params', array('url' => admin_url('admin-ajax.php')));
        wp_localize_script( Tulo_Payway_Server::instance()->plugin_name, 'tulo_settings', array(
            'clientid' => get_option('tulo_client_id'),
            'redirecturi' => get_option('tulo_redirect_uri'),
            'env' => get_option('tulo_environment'),
            'oid' => get_option('tulo_organisation_id')
        ));
    }

    public function register_session() {
        if( !session_id() )
        {
            session_start();
        }
    }

    public function check_session($wp) {
        global $post;
        if (is_admin()) 
            return;

        if ( isset($post->ID) && strpos($_SERVER["REQUEST_URI"], "favicon") === false) {
            $this->common->write_log("In check session");            
            if ($this->sso->session_established()) {
                $this->common->write_log("Basic SSO2 session established: ".$this->sso->sso_session_id());
                if ($this->sso->session_needs_refresh()) {
                    $this->sso->refresh_session();
                }
                $status = $this->sso->get_session_status();
                if ($status == "loggedin") {                    
                    $this->common->write_log("Logged in as: ".$this->sso->get_user_name()." (".$this->sso->get_user_email().")");
                    $this->common->write_log($this->sso->get_user_active_products());
                } else if ($status == "anon") {
                    $this->common->write_log("Not logged in, user needs to login if accessing restricted content");                    
                }
                
            } else {
                $this->common->write_log("BEGIN Identify");
                $this->sso->identify_session();
            }    
        }
    }

    public static function get_post_restrictions($post_id = null)
    {
        if( !$post_id )
        {
            global $post;

            if( $post )
            {
                $post_id = intval( $post->ID );
            }
        }
        $restrictions = get_post_meta( $post_id, Tulo_Payway_Server::$post_meta_key, true );
        return $restrictions;
    }

    public static function get_whitelisted_ips()
    {
        $ips = explode("\n", get_option('tulo_whitelist_ip'));

        foreach ($ips as $key => $value) {
        $ips[$key] = trim($value);
        }

        return $ips;
    }

    public function has_access($post_id = null, $restrictions = null)
    {
        if($restrictions == null) {
            $restrictions = Tulo_Payway_Server_Public::get_post_restrictions($post_id);
        }

        if(empty($restrictions)) {
            return true;
        }

        $whitelisted_ips = Tulo_Payway_Server_Public::get_whitelisted_ips();
        if(in_array($_SERVER['REMOTE_ADDR'], $whitelisted_ips, false)) {
            return true;
        }

        // Early return if the user has no products
        if(!$this->sso->user_has_subscription()) {
            return false;
        }

        $user_products = $this->sso->get_user_active_products();

        foreach($restrictions as $restriction)
        {
            foreach($user_products as $product)
            {
                if($restriction->productid == $product) {
                    return true;
                }
            }
        }
        return false;
    }

    public function post_class_filter($classes)
    {
        $restrictions = Tulo_Payway_Server_Public::get_post_restrictions();
        if(empty($restrictions)) {
            $classes[] = 'tulo_public';
        }

        global $post;
        $post_id = intval( $post->ID );

        //if (is_admin()) {
        //    return $classes;
        //}

        if($this->has_access($post_id, $restrictions)) {
            $classes[] = 'tulo_access';
        } else {
            $classes[] = 'tulo_no_access';
        }

        return $classes;
    }
    
    public function content_filter($content)
    {
        if($this->has_access())
            return $content;

        do_action('tulo_before_permission_required');

        global $post;
        $output  = '<div class="paygate">';
        $output .= '    <div class="info-box-wrapper">';
        if(!empty($post->post_excerpt))
        {
            $output .= '        <div class="info-box article">';
            $output .= $post->post_excerpt;
            $output .= '        </div>';
        }


        $output .= '        <div class="info-box permission_required">';
        if ($this->sso->is_logged_in()) {
            $output .= do_shortcode('[tulo_permission_required_loggedin]');
        } else {
            $output .= do_shortcode('[tulo_permission_required_not_loggedin]');
        }
        
        
        $output .= '        </div>';
        $restrictions = Tulo_Payway_Server_Public::get_post_restrictions();

        foreach($restrictions as $restriction)
        {
            $product = $this->find_product($restriction->productid);
            if($product == null)
                continue;

            if(empty($product->buyinfo))
                continue;
            $output .= '<div class="tulo-product-wrapper info-box">';
            $output .= $product->buyinfo;
            $output .= '</div>';

        }
        $output .= '</div>';
        $output .= '</div>';
        do_action('tulo_after_permission_required');
        return $output;
    }
    private $available_products;
    private function find_product($productid)
    {
        if($this->available_products == null)
            $this->available_products = Tulo_Payway_Server::get_available_products();
        foreach($this->available_products as $product)
        {
            if($productid ==$product->productid)
                return $product;
        }
        return null;
    }

    public function shortcode_permission_required_loggedin()
    {
        return get_option('tulo_permission_required_loggedin');
    }

    public function shortcode_permission_required_not_loggedin()
    {
        return get_option('tulo_permission_required_not_loggedin');
    }

    public function shortcode_loggedin_user_name()
    {
        return $this->sso->get_user_name();
    }

    public function shortcode_loggedin_user_email()
    {
        return $this->sso->get_user_email();
    }

    

    public function shortcode_buy_button($atts)
    {
        $retval = '<button class="js-tuloBuy '.$atts['class'].'" data-product="'.$atts['product'].'">';
        $retval .= __('Buy', 'tulo');
        $retval .= '</button>';
        return $retval;
    }

    public function shortcode_product_link($atts, $content = null)
    {
        $output = '<a href="'.Tulo_Payway_Server_Public::get_product_shop_url($atts['product']).'" class="'.$atts['class'].'">';
        $output .= $content;
        $output .= '</a>';
        return $output;
    }

    public function ajax_list_products(){

        header('Content-Type: application/json');

        echo json_encode(Tulo_Payway_Server::get_available_products());

        die();
    }

    public function ajax_login()
    {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'password');
        $persist = filter_input(INPUT_POST, 'persist', FILTER_VALIDATE_BOOLEAN);

        if (isset($username) && isset($password) && isset($persist)) {            
            $response = $this->sso->authenticate_user($username, $password);

            echo json_encode($response);
        } else {
            echo json_encode(array(
                'status' => 400,
                'error' => 'Make sure all login details are submitted.'
            ));
        }

        wp_die();
    }

    public function ajax_logout()
    {
        $response = $this->sso->logout_user();
        echo json_encode($response);

        wp_die();
    }
}
