<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       http://adeprimo.se
 * @since      1.0.0
 *
 * @package    Tulo_Payway_Server
 * @subpackage Tulo_Payway_Server/admin/partials
 */



if(isset($_POST['action']) && $_POST['action'] == 'update')
{

    update_option("tulo_server_client_id", $_POST["tulo_server_client_id"]);
    update_option("tulo_server_secret", $_POST["tulo_server_secret"]);
    update_option("tulo_organisation_id", $_POST["tulo_organisation_id"]);
    update_option("tulo_environment", $_POST["tulo_environment"]);
    update_option('tulo_whitelist_ip', $_POST["tulo_whitelist_ip"]);
    foreach(Tulo_Payway_Server_Admin::get_post_types() as $posttype)
    {
        $key = 'tulo_'.$posttype['name'].'_default_restricted';
        if (isset($_POST[$key]))
            update_option($key, $_POST[$key]);
    }
    update_option("tulo_permission_required_loggedin", stripslashes($_POST["tulo_permission_required_loggedin"]));
    update_option("tulo_permission_required_not_loggedin", stripslashes($_POST["tulo_permission_required_not_loggedin"]));
    $products = json_decode(stripslashes($_POST["tulo_products_val"]));
    update_option("tulo_products", $products);
}

function tulo_server_render_text_option_setting($label, $name, $helper = null)
{
    $value = get_option($name);
    ?>

    <tr>
	    <th scope="row">
                <label for="<?php echo $name ?>">
                    <?php echo esc_html( $label ); ?>
                </label>
        </th>
        <td>
            <input class="regular-text " type="text" name="<?php echo $name ?>" id="<?php echo $name ?>" value="<?php echo esc_attr( $value ) ?>" />
            <?php if($helper != null){
                      echo $helper;
                  } ?>
        </td>
    </tr>
<?php }

function tulo_server_render_text($label, $name)
{
    //$value = get_option($name);
    ?>

    <tr>
	    <th scope="row">
                <label for="<?php echo $name ?>">
                    <?php echo esc_html( $label ); ?>
                </label>
        </th>
        <td>
            <p class="regular-text ">
            <?php echo plugin_dir_url(__DIR__)."/landing.php (should be registered in Payway)"?>
            </p>
        </td>
    </tr>
<?php }

function tulo_server_render_bool_option_setting($label, $name, $helper = null)
{

    $value = get_option($name);
?>

    <tr>
	    <th scope="row">
                <label for="<?php echo $name ?>">
                    <?php echo esc_html( $label ); ?>
                </label>
        </th>
        <td>
            <input class="regular-checkbox " type="checkbox" name="<?php echo $name ?>" id="<?php echo $name ?>" <?php echo $value ? 'checked="checked"':''?> />
            <?php if($helper != null){
                      echo $helper;
                  } ?>
        </td>
    </tr>
<?php
}

function tulo_server_render_env_setting()
{
    $key = 'tulo_environment';
    $value = get_option($key);
    ?>

    <tr>
	    <th scope="row">
                <label for="<?php echo $key ?>">
                    <?php _e('Environment', 'tulo'); ?>
                </label>
        </th>
    <td>
        <select name="<?php echo $key ?>" id="<?php echo $key ?>" >
            <option value="prod" <?php echo $value == 'prod' ? 'selected="selected"':'' ?>>prod</option>
            <option value="stage" <?php echo $value == 'stage' ? 'selected="selected"':'' ?>>stage</option>
        </select>
        </td>
    </tr>
<?php }


function tulo_server_render_required_setting_not_loggedin()
{
    $key = 'tulo_permission_required_not_loggedin';
    $value = get_option($key);
    ?>
    <tr>
	    <th scope="row">
            <label for="<?php echo $key ?>">
                <?php _e('If permission required', 'tulo'); ?><br />
                <i><?php _e('Code will be rendered if product is missing and user is not logged in:', 'tulo'); ?></i>
            </label>
        </th>
        <td>
            <textarea name="<?php echo $key ?>" class="tulo_permission_required"><?php echo $value ?></textarea>
        </td>
    </tr>
<?php

}

function tulo_server_render_required_setting_loggedin()
{
    $key = 'tulo_permission_required_loggedin';
    $value = get_option($key);
    ?>
    <tr>
	    <th scope="row">
            <label for="<?php echo $key ?>">
                <?php _e('If permission required', 'tulo'); ?><br />
                <i><?php _e('Code will be rendered if product is missing and user already logged in:', 'tulo'); ?></i>
            </label>
        </th>
        <td>
            <textarea name="<?php echo $key ?>" class="tulo_permission_required"><?php echo $value ?></textarea>
        </td>
    </tr>
<?php

}

function tulo_server_render_product_list()
{
?>
<h2><?php _e('Available products', 'tulo') ?></h2>

<table class="form-table tulo-products"  ng-controller="ProductListController">
    <tr class="tulo_product_row {{product.productid}}" ng-repeat="product in model.Products">
        <th scope="row">
            <?php _e('Productname', 'tulo'); ?>
            <input type="text" class="label" ng-model="product.label">

        </th>
        <td><?php _e('Productkey', 'tulo') ?>:
            <input type="text" class="productid" ng-model="product.productid">
            <a class="delete" ng-click="delete(product)"><?php _e('Delete', 'tulo') ?></a>
            <p>
            <?php _e('Buy info', 'tulo') ?>:<br>
            <i><?php _e('Code below will be rendered if this product is required', 'tulo') ?></i>
            </p>
            <textarea class="buy-info" ng-model="product.buyinfo"></textarea>
            <br><?php _e('Useful shortodes', 'tulo') ?>:
            <div class="shortcodes">
                <div class="button" ng-click="insertShortcode($event, product)">[tulo_buy_button product="{{product.productid}}"]</div>
                <div class="button" ng-click="insertShortcode($event, product, 'tulo_product_link')">[tulo_product_link product="{{product.productid}}"]</div>
            </div>
        </td>
    </tr>
    <tr>
        <td>
            <a class="tulo_add_product" ng-click="addProduct()"><?php _e('Add product', 'tulo'); ?></a>
            <textarea name="tulo_products_val">{{model.Products}}</textarea>
        </td>
    </tr>

    </table>


    <?php
}

function tulo_server_render_whitelist_ips() {
  $key = 'tulo_whitelist_ip';
  $value = get_option($key);
?>
  <tr>
    <th scope="row">
      <label for="<?php echo $key; ?>">
        <?php _e('Whitelist IP Addresses', 'tulo'); ?>
        <i><?php _e('Seperate the ip addresses with a new row', 'tulo'); ?></i>
      </label>
    </th>
    <td>
      <textarea name="<?php echo $key; ?>" class="tulo_whitelist_ip"><?php echo $value; ?></textarea>
    </td>
  </tr>

<?php
}
?>

<div class="wrap" ng-app="tulo.admin">
  <h1><?php esc_html_e( 'Tulo settings', 'tulo'); ?></h1>
  <form name="form" action="" method="post" id="tulo-server-options">
  <?php wp_nonce_field('options-options') ?>
  <input type="hidden" name="action" value="update" />
  <table class="form-table">
      <?php
      tulo_server_render_text_option_setting(__('API Client id', 'tulo'), 'tulo_server_client_id');
      tulo_server_render_text_option_setting(__('API Secret', 'tulo'), 'tulo_server_secret');
      tulo_server_render_text(__('Redirect url', 'tulo'), 'tulo_redirect_uri');
      tulo_server_render_text_option_setting(__('Organisation id', 'tulo'), 'tulo_organisation_id');
      $posttypes = Tulo_Payway_Server_Admin::get_post_types();

      foreach($posttypes as $post_type)
      {

          $posttypename = htmlentities($post_type['label']);
          $posttypename = strtolower($posttypename);

          $label = __('New %s are restricted', 'tulo');
          $label = sprintf($label, $posttypename);
          tulo_server_render_bool_option_setting($label, 'tulo_'.$post_type['name'].'_default_restricted');
      }

      tulo_server_render_env_setting();
      tulo_server_render_required_setting_not_loggedin();
      tulo_server_render_required_setting_loggedin();
      tulo_server_render_whitelist_ips();
      ?>

  </table>
  <hr/>
  <?php tulo_server_render_product_list(); ?>

  <?php submit_button( __( 'Save Changes' ), 'primary', 'Update' ); ?>

  </form>
</div>
