<?php

require plugin_dir_path( __FILE__ ) . '../includes/class-tulo-paywall.php';
/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.1
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
 */
class Tulo_Payway_Server_Public {

    /**
     * The version of this plugin.
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    private $common;
    private $session;
    /**
     * Initialize the class and set its properties.
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $version ) {
        $this->version = $version;
        $this->common = new Tulo_Payway_Server_Common();
        $this->session = new Tulo_Payway_Session();
    }

    public static function get_portal_base_url() {
        return Tulo_Payway_Server_Public::get_myaccount_url();
    }
    
    public static function get_myaccount_url() {
        return Tulo_Payway_Server_Common::get_tulo_myaccount_url();
    }
    

    public static function get_product_shop_url($product) {
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
        wp_add_inline_script( Tulo_Payway_Server::instance()->plugin_name, $this->get_data_layer());
        wp_add_inline_script( Tulo_Payway_Server::instance()->plugin_name, $this->get_local_storage());
    }

    public function register_session() {
        //if( !session_id() ) {
        //    session_start();
        //}
    }

    public function should_request_be_excepted() {        
        $this->common->write_log("Checking for SSO exceptions");

        if (isset($_SERVER["HTTP_PURPOSE"]) && $_SERVER["HTTP_PURPOSE"] == "prefetch") {
            return true;
        }

        //$this->common->write_log("SERVER: ".print_r($_SERVER, true));

        $this->common->write_log("Checking IP for excepted request.");
        $except_ip = false;
        $whitelisted_ips = Tulo_Payway_Server_Public::get_whitelisted_ips();
        if (in_array($_SERVER['REMOTE_ADDR'], $whitelisted_ips, false)) {
            $except_ip = true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($iplist as $ip) {
                if (in_array($ip, $whitelisted_ips, false)) {
                    $except_ip = true;
                }
                if ($except_ip) {
                    break;
                }
            }
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED'])) {
           if (in_array($_SERVER['HTTP_X_FORWARDED'], $whitelisted_ips, false)) {
                $except_ip = true;
           }
        }
   
        if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            if (in_array($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'], $whitelisted_ips, false)) {
                $except_ip = true;
            }          
        }

        if (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            if (in_array($_SERVER['HTTP_FORWARDED_FOR'], $whitelisted_ips, false)) {
                $except_ip = true;
            }
        } 
  
        if (!empty($_SERVER['HTTP_FORWARDED'])) {
            if (in_array($_SERVER['HTTP_FORWARDED'], $whitelisted_ips, false)) {
                $except_ip = true;

            }        
        }

        if ($except_ip) {
            $this->common->write_log("IP match, excepting this request from SSO.");
            return true;
        }

        $this->common->write_log("Checking headers for excepted request.");
        // check header?
        if (get_option('tulo_except_header_name') != "") {
            $header = get_option('tulo_except_header_name');
            $value = get_option('tulo_except_header_value');
            if (isset($_SERVER[$header])) {
                if ($_SERVER[$header] == $value) {
                    $this->common->write_log("Header value match, excepting this request from SSO.");
                    return true;
                }
            } 
        }
    
        $this->common->write_log("No exceptions found, proceeding with SSO identification if needed.");
        return false;
    }

    public function check_session($wp) 
    {
        global $wp;
        global $post;
        if (is_admin()) 
            return;
        
        if (get_option("tulo_plugin_active") != "on") 
            return;

    
        if ($this->session->has_session_error()) {
            $this->common->write_log("!! Skipping identification. Currently session identification problems");
            return;
        }

        if ($this->should_request_be_excepted()) {
            return;
        }
        
        if (strpos($_SERVER["REQUEST_URI"], "favicon") === false) {
            if (get_query_var("tpw_session_refresh") != "") {
                $this->common->write_log("!! forced session session refresh using query param");
                $this->session->refresh();
                $currentUrl = home_url( $wp->request );
                $permalinkStructure = get_option( 'permalink_structure' );
                if ($permalinkStructure == "plain" || $permalinkStructure == "") {
                    $queryVars = $wp->query_vars;
                    unset($queryVars['tpw_session_refresh']);
                    $currentUrl = add_query_arg( $queryVars, home_url( $wp->request ) );
                }
                if (strpos($currentUrl, "?") === false) {
                    $currentUrl .= "?tpw=".time();
                } else {
                    $currentUrl .= "&tpw=".time();
                }
                $this->common->write_log("!! session has been refreshed, redirecting to: ".$currentUrl);
                header("Location: ".$currentUrl, true, 302);
                die();
            }
        }

        if ( isset($post->ID) && strpos($_SERVER["REQUEST_URI"], "favicon") === false) {
            $this->common->write_log("[check_session]");            
            $established = $this->session->established();
            $this->common->write_log("established status: ".$established);            
            if ($established == Tulo_Payway_API_SSO2::SESSION_ESTABLISHED_STATUS_WARM) {
                $this->common->write_log("basic SSO2 session is established.");                
                if ($this->session->needs_refresh()) {
                    $this->common->write_log("session expired, needs refresh");
                    $this->session->refresh();
                } else { 
                    $restrictions = Tulo_Payway_Server_Public::get_post_restrictions($post->ID);
                    if (!empty($restrictions) && !$this->session->is_logged_in()) {
                        $this->common->write_log("session not expired but page is restricted and user is not logged in, needs refresh");
                        $this->common->write_log($restrictions);
                        $this->session->refresh();
                    } 
                }

                $status = $this->session->get_status();
                if ($status == "loggedin" && $this->session->is_logged_in()) {                    
                    $this->common->write_log("logged in as: ".$this->session->get_user_name()." (".$this->session->get_user_email().")");
                    $this->common->write_log($this->session->get_user_active_products());
                } else if ($status == "anon") {
                    //$this->common->write_log("not logged in, user needs to login if accessing restricted content");                    
                }
                
            }
            else if ($established == Tulo_Payway_API_SSO2::SESSION_ESTABLISHED_STATUS_COLD) {
                $this->common->write_log("session is cold but exist in cookie, refresh session data");
                $this->session->refresh();
            }
            else {
                $restricted_only = get_option("tulo_session_restricted_only");
                $this->common->write_log("session for restricted content only: ".$restricted_only);

                if (isset($restricted_only) && $restricted_only == "on") {
                    $restrictions = $this->get_post_restrictions($post->ID);
                    if (!empty($restrictions)) {
                        $this->common->write_log("!! post has restrictions, no session available. will identify");
                        $this->session->identify();
                    } else {
                        $this->common->write_log("!! post has no restrictions and 'restricted only' is enabled, no need to identify");
                    }
                } else {
                    $this->common->write_log("!! 'restricted_only' is not enabled, establishing session");
                    $this->session->identify();
                }

            }    
        }
    }

    public static function get_post_restrictions($post_id = null) {
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

    public static function get_whitelisted_ips() {
        $ips = explode("\n", get_option('tulo_whitelist_ip'));

        foreach ($ips as $key => $value) {
        $ips[$key] = trim($value);
        }

        return $ips;
    }

    public function has_access($post_id = null, $restrictions = null) {

        if (is_admin()) 
            return;

        if($restrictions == null) {
            $restrictions = Tulo_Payway_Server_Public::get_post_restrictions($post_id);
        }
        if(empty($restrictions)) {
            return true;
        }

        if ($this->should_request_be_excepted()) {
            return true;
        }

        // If restrictions only require logged in user, return true if user is logged in
        if ($this->restrictions_require_login_only($restrictions) && $this->session->is_logged_in()) {
            $this->common->write_log("!! restrictions require login only");
            return true;
        }

        // Early return if the user is not logged in
        if(!$this->session->is_logged_in()) {
            return false;
        }


        $user_products = $this->session->get_user_active_products();
        $this->common->write_log("User products: ".print_r($user_products, true));
        $this->common->write_log("Restrictions: ".print_r($restrictions, true));
        foreach($restrictions as $restriction)
        {
            if ($restriction->productid == "tulo-loggedin") {
                $this->common->write_log("user has access through 'logged-in' requirement");
                return true;
            }
            foreach($user_products as $product)
            {
                if($restriction->productid == $product) {
                    return true;
                }
            }
        }
        return false;
    }

    public function post_class_filter($classes) {
        $restrictions = Tulo_Payway_Server_Public::get_post_restrictions();
        if(empty($restrictions)) {
            $classes[] = 'tulo_public';
        }

        global $post;
        $post_id = intval( $post->ID );

        if($this->has_access($post_id, $restrictions)) {
            $classes[] = 'tulo_access';
        } else {
            $classes[] = 'tulo_no_access';
        }

        return $classes;
    }
    
    private function get_data_layer() {
        $userId = "";
        $userEmail = "";
        $userCustomerNumber = "";
        $userProducts = '[]';
        if ($this->session->is_logged_in()) {
            if (get_option('tulo_expose_account_id', false)) {
                $userId = $this->session->get_user_id();
            }            
            if (get_option('tulo_expose_email', false)) {
                $userEmail = $this->session->get_user_email();
            }
            if (get_option('tulo_expose_customer_number', false)) {
                $userCustomerNumber = $this->session->get_user_customer_number();
            }                    
            $userProducts = json_encode($this->session->get_user_active_products());
        }
        return '  if (window.dataLayer!==undefined) { dataLayer.push({"tulo": {"user" : { "id": "'.$userId.'", "email": "'.$userEmail.'", "customer_number": "'.$userCustomerNumber.'", "products":'.$userProducts.'}}}); }';
    }

    private function get_local_storage() {
        $userId = "";
        $userName = "";
        $userEmail = "";
        $userCustomerNumber = "";
        $userProducts = '[]';
        if ($this->session->is_logged_in()) {
            if (get_option('tulo_expose_account_id', false)) {
                $userId = $this->session->get_user_id();
            }            
            if (get_option('tulo_expose_email', false)) {
                $userEmail = $this->session->get_user_email();
            }
            if (get_option('tulo_expose_customer_number', false)) {
                $userCustomerNumber = $this->session->get_user_customer_number();
            }                    
            $userName = $this->session->get_user_name();
            $userProducts = json_encode($this->session->get_user_active_products());
        }
        return ' if (window.localStorage) { localStorage.setItem("tulo.account_name", "'.$userName.'"); localStorage.setItem("tulo.account_email", "'.$userEmail.'"); localStorage.setItem("tulo.account_customer_number", "'.$userCustomerNumber.'"); localStorage.setItem("tulo.account_id", "'.$userId.'"); localStorage.setItem("tulo.account_user_products", '.$userProducts.'); }';  
    }

    public function content_filter($content) {

        if (get_option("tulo_plugin_active") != "on") 
            return $content;
            
        if($this->has_access()) {              
            return $content;
        }

        if (is_admin()) {
            return $content;
        }

        do_action('tulo_before_permission_required');

        global $post;
        //$output  = $this->get_script_content();
        $output = '<div class="paygate">';
        $output .= '    <div class="info-box-wrapper">';
        if(!empty($post->post_excerpt)) {
            $output .= '        <div class="info-box article">';
            $output .= $post->post_excerpt;
            $output .= '        </div>';
        }

        $restrictions = Tulo_Payway_Server_Public::get_post_restrictions();
        if (get_option("tulo_paywall_enabled") != "on") {

            $output .= '        <div class="info-box permission_required">';
            if ($this->session->is_logged_in()) {
                $output .= do_shortcode('[tulo_permission_required_loggedin]');
            } else {
                $output .= do_shortcode('[tulo_permission_required_not_loggedin]');
            }
            
            $output .= '        </div>';

            foreach($restrictions as $restriction) {
                $product = $this->find_product($restriction->productid);
                if($product == null)
                    continue;
    
                if(empty($product->buyinfo))
                    continue;
                $output .= '<div class="tulo-product-wrapper info-box">';
                $output .= $product->buyinfo;
                $output .= '</div>';
    
            }    
        }

        if (get_option("tulo_paywall_enabled") == "on" && get_option("tulo_paywall_clientside_enabled") != "on") {
            $output .= $this->initialize_paywall($restrictions);
        }

        if (get_option("tulo_paywall_clientside_enabled") == "on") {
            $output .= $this->initialize_paywall_clientside($restrictions);
        }


        $output .= '</div>';
        $output .= '</div>';

        do_action('tulo_after_permission_required');
        return $output;
    }

    private function restrictions_require_login_only($post_restrictions) {
        foreach($post_restrictions as $restriction) {
            if ($restriction->productid == "tulo-loggedin")
                return true;            
        }
        return false;
    }

    private function initialize_paywall_clientside($post_restrictions)    
    {
        $this->common->write_log("initialize paywall clientside");
        $output = $this->initialize_paywall($post_restrictions, true);
        return $output;
    }

    private function initialize_paywall($post_restrictions, $late_init = false)    
    {
        if (is_admin()) 
            return;

        if (get_option("tulo_plugin_active") != "on") 
            return $content;

        $paywall = new Tulo_Paywall_Common();
        $debug = get_option("tulo_paywall_js_debug_enabled") == "on" ? "true" : "false";

        $custom_variables = $paywall->get_custom_variables();
        $this->common->write_log("custom variables: ".print_r($custom_variables, true));

        $spinner_html = get_option("tulo_paywall_spinner_html");
        if ($spinner_html == "") {
            $loading = __('Loading paywall...', 'tulo');
            $spinner_html = '<div class="lds-dual-ring"></div><p><i>'.$loading.'</i></p>';
        }
        $output = '<div id="tulo-paywall"><template id="paywall-loader-template">'.$spinner_html.'</template><div id="paywall-container"></div></div>';
        if (get_option("tulo_paywall_css_enabled") == "on") {
            $output .= '<link rel="stylesheet" href="'.$paywall->get_paywall_css().'"/>';
        }

        $jwtToken = "";
        if (!$late_init) {
            $jwtToken = $paywall->get_signature($post_restrictions);
        }

        $output .= '<script src="'.$paywall->get_paywall_js().'"></script>';
        $output .= '<script type="text/javascript">
                     var paywallCfg = {
                        debug: '.$debug.',
                        url: "'.$paywall->get_paywall_url().'",
                        jwtToken: "'.$jwtToken.'",
                        accountOrigin: "'.$paywall->get_account_origin().'",
                        trafficSource: "'.$paywall->get_traffic_source().'",
                        merchantReference: "'.$paywall->get_merchant_reference().'",
                        returnUrl: "'.$paywall->get_return_url().'",
                        backUrl: "'.$paywall->get_back_url().'",
                        utmSource: "",
                        loginUrl: "'.$paywall->get_login_url().'",
                        shopUrl: "'.$paywall->get_shop_url().'",
                        ticketLoginUrl: "'.$paywall->get_ticket_login_url().'",
                        utmMedium: "",
                        utmCampaign: "",
                        utmContent: "",
                        customVariables: '.$custom_variables.',
                        resources: {
                            errorHeader: "'.$paywall->get_error_header().'",
                            errorDescription: "'.$paywall->get_error_message().'"
                        },
                        engageTracking: {
                            articleId: "'.$paywall->get_article_id().'",
                            sections: [],
                            categories: []
                        }
                     };
                    </script>';
        if (!$late_init) {
            $output .= '<script type="text/javascript">new TuloPaywall().Init(paywallCfg);</script>';
        } else {
            $restrictions = base64_encode(serialize($post_restrictions));
            $output .= '<script type="text/javascript">
                var restrictions = "'.$restrictions.'";
                async function generatePaywallSignature(restrictions) {
                    var response = await fetch(tulo_params.url, {
                        method: "POST",
                        credentials: "include",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: "action=tulo_pw_signature&restrictions="+restrictions
                    });
                    if (response.ok) {
                        return await response.text();
                    } else {
                        return "error";
                    }
                }
                async function initPaywall() {
                    var pwJWT = await generatePaywallSignature(restrictions);
                    if (pwJWT != "error") {
                        paywallCfg.jwtToken = pwJWT;
                        new TuloPaywall().Init(paywallCfg);
                    }
                }
                initPaywall();
            </script>';
        }
        return $output;
    }


    private $available_products;
    private function find_product($productid) {
        if($this->available_products == null)
            $this->available_products = Tulo_Payway_Server::get_available_products();
        foreach($this->available_products as $product)
        {
            if($productid ==$product->productid)
                return $product;
        }
        return null;
    }

    public function shortcode_permission_required_loggedin() {
        return get_option('tulo_permission_required_loggedin');
    }

    public function shortcode_permission_required_not_loggedin() {
        return get_option('tulo_permission_required_not_loggedin');
    }

    public function shortcode_login_logout() {
        $output = "";
        if ($this->session->is_logged_in()) {
            $user = trim($this->session->get_user_name()) == "" ? $this->session->get_user_email() : $this->session->get_user_name();
            $output .= "<div class=\"user-greeting\">Hi <a href=\"#\" class=\"js-tuloMyAccount\" >".$user."</a>.</div><a href=\"#\" class=\"js-tuloLogout is-hidden\">Logout</a>";
        } else {
            $output .= "<a href=\"".$this->common->get_authentication_url()."\">Login</a>";
        }
        return $output;
    }

    public function shortcode_loggedin_user_id() {
        return $this->session->get_user_id();
    }

    public function shortcode_loggedin_user_name() {
        return $this->session->get_user_name();
    }

    public function shortcode_loggedin_user_email() {
        return $this->session->get_user_email();
    }

    public function shortcode_loggedin_user_customer_number() {
        return $this->session->get_user_customer_number();
    }

    public function shortcode_buy_button($atts) {
        if(!isset($atts['class'])) {
            $atts['class'] = '';
        }
        $retval = '<button class="js-tuloBuy '.$atts['class'].'" data-product="'.$atts['product'].'">';
        $retval .= __('Buy', 'tulo');
        $retval .= '</button>';
        return $retval;
    }

    public function shortcode_product_link($atts, $content = null) {
        $output = '<a href="'.Tulo_Payway_Server_Public::get_product_shop_url($atts['product']).'" class="'.$atts['class'].'">';
        $output .= $content;
        $output .= '</a>';
        return $output;
    }

    public function shortcode_authentication_url() {
        return $this->common->get_authentication_url();
    }

    public function ajax_list_products() {
        header('Content-Type: application/json');
        echo json_encode(Tulo_Payway_Server::get_available_products($includeLoggedIn=false));

        die();
    }

    public function ajax_list_variables() {
        header('Content-Type: application/json');
        echo json_encode(Tulo_Payway_Server::get_available_variables());

        die();
    }

    public function ajax_login() {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'password');

        if (isset($username) && isset($password)) {            
            $response = $this->session->authenticate($username, $password);

            echo json_encode($response);
        } else {
            echo json_encode(array(
                'status' => 400,
                'error' => 'Make sure all login details are submitted.'
            ));
        }

        wp_die();
    }

    public function ajax_logout() {
        $response = $this->session->logout(true);
        echo json_encode($response);

        wp_die();
    }

    public function ajax_paywall_jwt() {        
        $restrictions = base64_decode(filter_input(INPUT_POST, 'restrictions'));
        $post_restrictions = unserialize($restrictions);
        $this->common->write_log("<CLIENT> post restrictions unserialized: ".print_r($post_restrictions, true));
        $paywall = new Tulo_Paywall_Common();
        $signature = $paywall->get_signature($post_restrictions);
        $this->common->write_log("<CLIENT> generated signature: ".$signature);
        
        header('Content-Type: text/plain');
        echo $signature;
        wp_die();
    }

    public function tulo_query_vars($qvars) {
        $qvars[] = "tpw_session_refresh";
        return $qvars;
    }


}
