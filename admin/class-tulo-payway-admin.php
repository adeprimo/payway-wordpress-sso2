<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://adeprimo
 * @since      1.0.0
 *
 * @package    Tulo_Payway_Server
 * @subpackage Tulo_Payway_Server/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Tulo_Payway_Server
 * @subpackage Tulo_Payway_Server/admin
 * @author     Your Name <email@example.com>
 */
class Tulo_Payway_Server_Admin {


    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $version ) {

        $this->version = $version;

    }

    public function init_admin()
    {
        add_options_page( 'Tulo settings', 'Tulo Payway SSO2', 'manage_options', 'wp-tulo-payway', array($this, 'tulo_render_settings'));
    }
    public function tulo_render_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        $this->enqueue_scripts();
        wp_enqueue_style('tulo-admin', plugin_dir_url( __FILE__ ) . 'css/tulo-admin.css', array(),  $this->version);
        require_once('tulo-payway-server-settings.php');
    }

    public function add_meta_boxes()
    {
        global $post;
        foreach(Tulo_Payway_Server_Admin::get_post_types() as $posttype)
        {
            if($post->post_type == $posttype['name'])
            {
                add_meta_box(Tulo_Payway_Server::$post_meta_key, __('Tulo Payway restrictions', 'tulo'), array( $this, 'show_editor' ), null, 'side');
                return;
            }
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'angular.js', 'https://ajax.googleapis.com/ajax/libs/angularjs/1.4.7/angular.min.js', array( 'jquery' ), $this->version, false );
        wp_enqueue_script( Tulo_Payway_Server::instance()->plugin_name.'productlist.controller', plugin_dir_url( __FILE__ ) . 'js/productlist.controller.js', null, $this->version, false );
        wp_enqueue_script( Tulo_Payway_Server::instance()->plugin_name.'_app', plugin_dir_url( __FILE__ ) . 'js/tulo-admin.js', array( 'jquery', 'angular.js' ), $this->version, false );
    }

    function show_editor()
    {
        global $post;
        $autocheck = false;


        wp_nonce_field( 'tulo_payway_metabox_data', 'tulo_payway_meta_box_nonce' );
        if($post->post_status  == 'auto-draft')
        {
            if(get_option('tulo_'.$post->post_type.'_default_restricted'))
            {
                $autocheck = true;
            }
        }

        $currentvalue = get_post_meta( $post->ID, Tulo_Payway_Server::$post_meta_key, true );
        foreach(Tulo_Payway_Server::get_available_products() as $product)
        {
            $checked = false;
            if(!empty($currentvalue))
            {
                foreach($currentvalue as $selected)
                {
                    if($selected->productid == $product->productid)
                        $checked = true;
                }
            }
            else if($autocheck)
            {
                $checked = true;
            }

        ?>
            <div>
                <input type="checkbox" name="<?php echo $this->get_field_key($product->productid) ?>" <?php echo $checked ? 'checked="checked"':''?> />
                <label><?php echo $product->label ?></label>

            </div>
        <?php }
    }

    private function get_field_key($productkey)
    {
        return Tulo_Payway_Server::$post_meta_key.'_'.$productkey;
    }

    public function save_post($post_id)
    {

        if ( ! isset( $_POST['tulo_payway_meta_box_nonce'] ) ) {
            return;
        }


        if ( ! wp_verify_nonce( $_POST['tulo_payway_meta_box_nonce'], 'tulo_payway_metabox_data' ) ) {
            return;
        }


        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $tuloSaveValue = array();
        foreach(Tulo_Payway_Server::get_available_products() as $product)
        {
            $fieldkey = $this->get_field_key($product->productid);
            if(isset($_POST[$fieldkey]))
            {
                array_push($tuloSaveValue, $product);
            }
        }

        $old = get_post_meta( $post_id, Tulo_Payway_Server::$post_meta_key);
        if(isset($old)) {
            update_post_meta( $post_id, Tulo_Payway_Server::$post_meta_key, $tuloSaveValue );
        } else {
            add_post_meta($post_id, Tulo_Payway_Server::$post_meta_key, $tuloSaveValue);
        }
    }

    public static function get_post_types()
    {
        $args = array(
        'public' => true
        );

        $output = 'objects'; // names or objects

        $post_types = get_post_types( $args, $output );
        $posttypes = array();
        foreach($post_types as $post_type)
        {
            if($post_type->name == 'attachment')
                continue;
            array_push($posttypes, array(
                'name' => $post_type->name,
                'label' =>$post_type->labels->name
                ));
        }
        return $posttypes;
    }

}