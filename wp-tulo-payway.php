<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://adeprimo.se
 * @since             1.2.4.1-beta
 * @package           Tulo_Payway_Server
 *
 * @wordpress-plugin
 * Plugin Name:       Tulo Payway Connector for Wordpress
 * Description:       This plugin integrates with the SSO2 single sign on solution in Tulo Payway. Now with support for Tulo Paywall.
 * Version:           1.2.4.1-beta6
 * Author:            Adeprimo AB
 * Author URI:        https://adeprimo.se
 * Text Domain:       tulo
 * Domain Path: /lang
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-tulo-payway-activator.php
 */
function activate_server_plugin_name() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-tulo-payway-activator.php';
	Tulo_Payway_Server_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-tulo-payway-deactivator.php
 */
function deactivate_server_plugin_name() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-tulo-payway-deactivator.php';
	Tulo_Payway_Server_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_server_plugin_name' );
register_deactivation_hook( __FILE__, 'deactivate_server_plugin_name' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-tulo-payway.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_server_plugin_name() {

	$plugin = new Tulo_Payway_Server();
	$plugin->run();

}
run_server_plugin_name();
