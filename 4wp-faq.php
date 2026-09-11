<?php
/**
 * Plugin Name: 4WP FAQ
 * Plugin URI:        https://github.com/4wpdev/4wp-faq
 * Description: Not just another FAQ block. A smart wrapper that adds intelligence without breaking your design. Adds JSON-LD schema, aggregation, and usage context while working on top of existing content with zero duplication.
 * Version:           2.4.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Tested up to:      7.1
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

define( 'FORWP_FAQ_VERSION', '2.4.0' );
define( 'FORWP_FAQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FORWP_FAQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$forwp_faq_loader = FORWP_FAQ_PLUGIN_DIR . 'includes/load.php';
if ( ! is_readable( $forwp_faq_loader ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			echo '<div class="notice notice-error"><p><strong>' . esc_html__( '4WP FAQ is missing includes/load.php.', '4wp-faq' ) . '</strong> ' . esc_html__( 'Re-upload the complete plugin package.', '4wp-faq' ) . '</p></div>';
		}
	);
	return;
}

require_once $forwp_faq_loader;

if ( ! ForWP\FAQ\load_required_includes( FORWP_FAQ_PLUGIN_DIR ) ) {
	return;
}

add_action( 'plugins_loaded', [ 'ForWP\FAQ\Plugin', 'init' ] );

register_activation_hook( __FILE__, [ 'ForWP\FAQ\Plugin', 'on_activation' ] );
register_deactivation_hook( __FILE__, [ 'ForWP\FAQ\Plugin', 'on_deactivation' ] );
