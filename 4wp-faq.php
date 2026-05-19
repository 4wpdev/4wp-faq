<?php
/**
 * Plugin Name: 4WP FAQ
 * Plugin URI:        https://github.com/4wpdev/4wp-faq
 * Description: Not just another FAQ block. A smart wrapper that adds intelligence without breaking your design. Adds JSON-LD schema, aggregation, and usage context while working on top of existing content with zero duplication.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Tested up to:      6.9
 * Author:            4WP Team
 * Author URI:        https://4wp.dev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 4wp-faq
 *
 * @package ForWP\FAQ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FORWP_FAQ_VERSION', '1.0.0' );
define( 'FORWP_FAQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FORWP_FAQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once FORWP_FAQ_PLUGIN_DIR . 'includes/class-settings.php';
require_once FORWP_FAQ_PLUGIN_DIR . 'includes/class-setup-wizard.php';
require_once FORWP_FAQ_PLUGIN_DIR . 'includes/class-setup-rest.php';
require_once FORWP_FAQ_PLUGIN_DIR . 'includes/class-dashboard-setup.php';
require_once FORWP_FAQ_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once FORWP_FAQ_PLUGIN_DIR . 'includes/class-admin-rest.php';
require_once FORWP_FAQ_PLUGIN_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', [ 'ForWP\FAQ\Plugin', 'init' ] );

register_activation_hook( __FILE__, [ 'ForWP\FAQ\Plugin', 'on_activation' ] );
register_deactivation_hook( __FILE__, [ 'ForWP\FAQ\Plugin', 'on_deactivation' ] );


