<?php
/**
 * Load plugin PHP dependencies with graceful failure on incomplete installs.
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required include paths relative to the plugin root.
 *
 * @return list<string>
 */
function get_required_include_files(): array {
	return [
		'includes/class-settings.php',
		'includes/class-category-resolver.php',
		'includes/integrations/class-polylang.php',
		'includes/integrations/class-yoast.php',
		'includes/class-editor-rest.php',
		'includes/class-setup-wizard.php',
		'includes/class-setup-rest.php',
		'includes/class-dashboard-setup.php',
		'includes/class-admin-settings.php',
		'includes/class-admin-rest.php',
		'includes/class-plugin.php',
	];
}

/**
 * Require plugin files or register an admin notice when the install is incomplete.
 *
 * @param string $plugin_dir Absolute plugin directory path.
 * @return bool True when every required file was loaded.
 */
function load_required_includes( string $plugin_dir ): bool {
	$missing = [];

	foreach ( get_required_include_files() as $relative ) {
		$path = $plugin_dir . $relative;
		if ( ! is_readable( $path ) ) {
			$missing[] = $relative;
			continue;
		}

		require_once $path;
	}

	if ( empty( $missing ) ) {
		return true;
	}

	add_action(
		'admin_notices',
		static function () use ( $missing ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$list = implode( ', ', array_map( 'esc_html', $missing ) );
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p><p><code>%s</code></p></div>',
				esc_html__( '4WP FAQ is missing required files.', '4wp-faq' ),
				esc_html__( 'Re-upload the full plugin package from GitHub or WordPress.org. The site stayed online because the plugin stopped loading safely.', '4wp-faq' ),
				esc_html( $list )
			);
		}
	);

	return false;
}
