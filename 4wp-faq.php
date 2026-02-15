<?php
/**
 * Plugin Name: 4WP FAQ
 * Plugin URI: https://4wp.dev/plugin/4wp-faq/
 * Description: Not just another FAQ block. A smart wrapper that adds intelligence without breaking your design. Adds JSON-LD schema, aggregation, and usage context while working on top of existing content with zero duplication.
 * Version: 0.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: 4WP Team
 * Author URI: https://4wp.dev
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: forwp-faq
 *
 * @package ForWP\FAQ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FORWP_FAQ_VERSION', '0.2.0' );
define( 'FORWP_FAQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FORWP_FAQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once FORWP_FAQ_PLUGIN_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', [ 'ForWP\FAQ\Plugin', 'init' ] );

register_activation_hook( __FILE__, [ 'ForWP\FAQ\Plugin', 'on_activation' ] );
register_deactivation_hook( __FILE__, [ 'ForWP\FAQ\Plugin', 'on_deactivation' ] );


