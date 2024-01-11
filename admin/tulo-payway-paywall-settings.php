<?php

/**
 * Provide a admin area view for the Paywall part of the Payway plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       http://adeprimo.se
 * @since      1.2.0
 *
 * @package    Tulo_Payway_Server
 * @subpackage Tulo_Payway_Server/admin/partials
 */


if(isset($_POST['action']) && $_POST['action'] == 'update')
{
    update_option('tulo_paywall_enabled', isset($_POST["tulo_paywall_enabled"]) ? "on" : "" );
    update_option('tulo_paywall_error_header', $_POST["tulo_paywall_error_header"]);
    update_option('tulo_paywall_error_message', $_POST["tulo_paywall_error_message"]);
    //update_option('tulo_paywall_error_function', $_POST["tulo_paywall_error_function"]);
    update_option('tulo_paywall_template_login_url', $_POST["tulo_paywall_template_login_url"]);
    update_option('tulo_paywall_template_shop_url', $_POST["tulo_paywall_template_shop_url"]);
    update_option('tulo_paywall_client_id', $_POST["tulo_paywall_client_id"]);
    update_option('tulo_paywall_secret', $_POST["tulo_paywall_secret"]);
    update_option('tulo_paywall_spinner_html', $_POST["tulo_paywall_spinner_html"]);
    update_option('tulo_paywall_title', $_POST["tulo_paywall_title"]);
    update_option('tulo_paywall_account_origin', $_POST["tulo_paywall_account_origin"]);
    update_option('tulo_paywall_css_enabled', isset($_POST["tulo_paywall_css_enabled"]) ? "on" :"");
    update_option('tulo_paywall_static_selector_key', $_POST["tulo_paywall_static_selector_key"]);
    update_option('tulo_paywall_dynamic_selector_key', $_POST["tulo_paywall_dynamic_selector_key"]);
    update_option('tulo_paywall_product_selector_key', isset($_POST["tulo_paywall_product_selector_key"]) ? "on" : "");
    update_option('tulo_paywall_traffic_source', $_POST["tulo_paywall_traffic_source"]);
    update_option('tulo_paywall_merchant_reference_static', $_POST["tulo_paywall_merchant_reference_static"]);
    update_option('tulo_paywall_merchant_reference_link', isset($_POST["tulo_paywall_merchant_reference_link"]) ? "on" : "");
    update_option('tulo_paywall_js_debug_enabled', isset($_POST["tulo_paywall_js_debug_enabled"]) ? "on" :"");

    $variables = json_decode(stripslashes($_POST["tulo_variables_val"]));
    update_option("tulo_paywall_variables", $variables);
}

function tulo_server_render_spinner_html()
{
    $key = 'tulo_paywall_spinner_html';
    $value = get_option($key);
    ?>
    <tr>
	    <th scope="row">
            <label for="<?php echo $key ?>">
                <?php _e('Spinner html', 'tulo'); ?><br />
                <i><?php _e('HTML shown when paywall is loading.', 'tulo'); ?></i>
            </label>
        </th>
        <td>
            <textarea name="<?php echo $key ?>" class="tulo_paywall_spinner_html"><?php echo $value ?></textarea>
        </td>
    </tr>
<?php

}

function tulo_server_render_text_option_setting($label, $name, $helper = null, $placeholder = "")
{
    $ph = $placeholder != "" ? "placeholder=\"".$placeholder."\"" : "";
    $value = get_option($name);
    ?>
    <tr>
	    <th scope="row">
                <label for="<?php echo $name ?>">
                    <?php echo esc_html( $label ); ?>
                </label>
        </th>
        <td>
            <input <?php echo $ph?> class="regular-text " type="text" name="<?php echo $name ?>" id="<?php echo $name ?>" value="<?php echo esc_attr( $value ) ?>" />
            <br/>
            <?php if($helper != null){
                      echo $helper;
                  } ?>
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

function tulo_server_render_option_titles($label) 
{
    $api = new Tulo_Payway_API();
    $titles = $api->get_titles();
    $key = "tulo_paywall_title";
    $value = get_option($key);

    ?>

    <tr>
        <th scope="row">
            <label for="<?php echo $key?>">
                <?php echo $label ?>
            </label>
        </th>
    </tr>
    <td>
        <select name="<?php echo $key?>" id="<?php echo $key ?>">
            <?php foreach($titles as $title): ?>
                <option <?php echo $value == $title->code ? 'selected="selected"':'' ?> value="<?php echo $title->code; ?>"><?php echo $title->name; ?></option>
            <?php endforeach; ?>                    
        </select>
    </td>
 
<?php }

function tulo_server_render_custom_variables()
{
?>
<table class="form-table tulo-variables"  ng-controller="VariableListController">
    <tr class="tulo_variable_row {{variable.key}}" ng-repeat="variable in model.Variables">
        <th scope="row">
            <?php _e('Variable key', 'tulo'); ?>
            <input type="text" class="label" ng-model="variable.key">

        </th>
        <td>
            <?php _e('Variable value', 'tulo') ?>
            <input type="text" class="value" ng-model="variable.value">
            <a class="delete" ng-click="deleteVariable(variable)"><?php _e('Variable delete', 'tulo') ?></a>            
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <textarea name="tulo_variables_val">{{model.Variables}}</textarea>
            <a class="tulo_add_variable" ng-click="addVariable()"><?php _e('Add variable', 'tulo'); ?></a>

        </td>
    </tr>
    <tr>
            <td colspan="2">
                <p><?php echo __('Tulo Paywall custom variable.', 'tulo');?></p>
            </td>
    </tr>


</table>

    <?php
}

?>

<div class="wrap" ng-app="tulo.admin">
    <h1><?php esc_html_e( 'Tulo Paywall settings', 'tulo'); ?></h1>
    <form name="form" action="" method="post" id="tulo-paywall-options">
    <?php wp_nonce_field('options-options') ?>
    <input type="hidden" name="action" value="update" />
    
    <h2><?php esc_html_e( 'Paywall basic settings', 'tulo'); ?></h1>
    <table class="form-table">

        <?php
            tulo_server_render_bool_option_setting(__("Tulo Paywall enabled", "tulo"), "tulo_paywall_enabled");
            tulo_server_render_text_option_setting(__("API Client id", "tulo"), "tulo_paywall_client_id", __("Paywall API user client id", "tulo"));
            tulo_server_render_text_option_setting(__("API Secret", "tulo"), "tulo_paywall_secret", __("Paywall API user secret", "tulo"));
        ?>
    </table>
    <hr/>
    <h2><?php esc_html_e( 'Paywall selection settings', 'tulo'); ?></h1>
    <table class="form-table">

        <?php
            tulo_server_render_text_option_setting(__("Tulo Paywall title", "tulo"), "tulo_paywall_title", __("Tulo Payway title code where Paywall is configured", "tulo"));
            tulo_server_render_text_option_setting(__("Tulo Paywall Static Selector Key", "tulo"), "tulo_paywall_static_selector_key", __("If there are more than one Paywall active in Tulo, this static key will select which Paywall to select for presentation. If static and dynamic keys are left blank, Tulo will display the first Paywall created.", "tulo"));
            tulo_server_render_text_option_setting(__("Tulo Paywall Dynamic Selector Key", "tulo"), "tulo_paywall_dynamic_selector_key", __("Enter the name of a session variable that holds the Paywall selection key, if no value is defined on the session variable, the static key will be used.", "tulo"));
            tulo_server_render_bool_option_setting(__("Tulo Paywall Product Selector Key", "tulo"), "tulo_paywall_product_selector_key", __("Use the locked article's required product code as selector, must map to a Paywall with matching key.", "tulo"));

        ?>
    </table>

    <hr/>
    <h2><?php esc_html_e( 'Paywall analytics settings', 'tulo'); ?></h1>
    <table class="form-table">

        <?php
            tulo_server_render_text_option_setting(__("Tulo Paywall Traffic Source", "tulo"), "tulo_paywall_traffic_source", __("Leave empty if no traffic source should be used (not recommended).", "tulo"), "article-paywall");
            tulo_server_render_text_option_setting(__("Tulo Paywall Merchant Reference (static)", "tulo"), "tulo_paywall_merchant_reference_static", __("Leave empty if no merchant reference should be used or if inferred from article link.", "tulo"));
            tulo_server_render_bool_option_setting(__("Tulo Paywall Merchant Reference from link", "tulo"), "tulo_paywall_merchant_reference_link", __("Get merchant reference from current article link", "tulo"));
            tulo_server_render_text_option_setting(__("Tulo Paywall Account Origin", "tulo"), "tulo_paywall_account_origin", __("New accounts will be created with this account origin", "tulo"));
        ?>
    </table>

    <hr/>
    <h2><?php esc_html_e( 'Paywall look and feel settings', 'tulo'); ?></h1>
    <table class="form-table">

        <?php
            tulo_server_render_spinner_html();
            tulo_server_render_bool_option_setting(__("Tulo Paywall CSS enabled", "tulo"), "tulo_paywall_css_enabled", __("Check to include Tulo CSS for the paywall", "tulo"));
        ?>

    </table>

    <hr/>
    <h2><?php esc_html_e( 'Paywall error handling settings', 'tulo'); ?></h1>
    <table class="form-table">

        <?php
            tulo_server_render_text_option_setting(__("Tulo Paywall Error header", "tulo"), "tulo_paywall_error_header", __("Error header text shown when the paywall cannot be loaded", "tulo"));
            tulo_server_render_text_option_setting(__("Tulo Paywall Error description", "tulo"), "tulo_paywall_error_message", __("Error description text shown when the paywall cannot be loaded", "tulo"));
            //tulo_server_render_text_option_setting(__("Tulo Paywall Error function", "tulo"), "tulo_paywall_error_function", __("External function called when the paywall cannot be loaded", "tulo"));
        ?>
        <tr>
            <td colspan="2">
                <p><?php echo __('Tulo Paywall error handling.', 'tulo');?></p>
            </td>
        </tr>
    </table>
    <hr/>
    <h2><?php esc_html_e( 'Paywall other settings', 'tulo'); ?></h1>
    <table class="form-table">

        <?php
            tulo_server_render_text_option_setting(__("Tulo Paywall Login Url", "tulo"), "tulo_paywall_template_login_url", __("Login URL that can be used in the Paywall template", "tulo"));
            tulo_server_render_text_option_setting(__("Tulo Paywall Shop Url", "tulo"), "tulo_paywall_template_shop_url", __("Shop URL that can be used in the Paywall template", "tulo"));
            tulo_server_render_bool_option_setting(__("Javascript debug enabled", "tulo"), "tulo_paywall_js_debug_enabled", __("Check to see extra debug statements in the Javascript console", "tulo"));
        ?>
    </table>
    <hr/>
    <h2><?php _e('Paywall custom variables', 'tulo') ?></h2>
    <table class="form-table">

        <?php
            tulo_server_render_custom_variables();
        ?>

    </table>
    

  <hr/>

  <?php submit_button( __( 'Save Changes' ), 'primary', 'Update' ); ?>

  </form>
</div>
