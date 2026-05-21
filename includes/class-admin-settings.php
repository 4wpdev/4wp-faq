<?php
namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FAQ Settings screen — React shell (same pattern as 4wp-weather admin).
 */
class Admin_Settings {
	public const PAGE_SLUG = 'forwp-faq-settings';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
	}

	/**
	 * Settings under the FAQ registry CPT menu.
	 */
	public static function register_menu() {
		if ( Settings::is_setup_complete() ) {
			$post_type = Settings::get_post_type();

			add_submenu_page(
				'edit.php?post_type=' . $post_type,
				__( '4WP FAQ Settings', '4wp-faq' ),
				__( 'Settings', '4wp-faq' ),
				'manage_options',
				self::PAGE_SLUG,
				[ __CLASS__, 'render_page' ]
			);
			return;
		}

		add_options_page(
			__( '4WP FAQ Settings', '4wp-faq' ),
			__( '4WP FAQ', '4wp-faq' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * @return string
	 */
	public static function get_page_url() {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * @param string $hook_suffix Admin page hook.
	 * @return bool
	 */
	public static function is_settings_screen( $hook_suffix ) {
		return is_string( $hook_suffix ) && false !== strpos( $hook_suffix, self::PAGE_SLUG );
	}

	/**
	 * Markup for React mount (forwp-faq-admin-root).
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', '4wp-faq' ) );
		}

		echo '<div class="wrap forwp-faq-admin-shell">';
		echo '<h1 class="forwp-faq-admin-heading">';
		echo '<span class="forwp-faq-admin-heading__icon" aria-hidden="true">';
		echo wp_kses( self::heading_svg(), self::heading_svg_allowed_html() );
		echo '</span>';
		echo '<span class="forwp-faq-admin-heading__text">';
		echo esc_html__( '4WP FAQ', '4wp-faq' );
		echo '</span>';
		echo '</h1>';
		echo '<div id="forwp-faq-admin-root" class="forwp-faq-admin-root" aria-live="polite"></div>';
		echo '</div>';
	}

	/**
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( ! self::is_settings_screen( $hook_suffix ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$asset_file = FORWP_FAQ_PLUGIN_DIR . 'build/admin/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_style( 'wp-components' );

		$style_path = FORWP_FAQ_PLUGIN_DIR . 'build/admin/style-index.css';
		if ( is_readable( $style_path ) ) {
			wp_enqueue_style(
				'forwp-faq-admin',
				FORWP_FAQ_PLUGIN_URL . 'build/admin/style-index.css',
				[ 'wp-components' ],
				$asset['version']
			);
		}

		wp_enqueue_script(
			'forwp-faq-admin',
			FORWP_FAQ_PLUGIN_URL . 'build/admin/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'forwp-faq-admin', '4wp-faq' );

		wp_localize_script(
			'forwp-faq-admin',
			'forwpFaqAdmin',
			[
				'restRoot' => esc_url_raw( rest_url() ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			]
		);
	}

	/**
	 * Allowed tags for the static admin heading icon SVG.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function heading_svg_allowed_html() {
		return [
			'svg'  => [
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'aria-hidden' => true,
			],
			'path' => [
				'd'    => true,
				'fill' => true,
			],
		];
	}

	/**
	 * @return string
	 */
	private static function heading_svg() {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="none" aria-hidden="true"><path d="M12 2a8 8 0 0 0-8 8c0 3.4 2.1 6.3 5 7.5V20a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-2.5c2.9-1.2 5-4.1 5-7.5a8 8 0 0 0-8-8Zm0 4.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm-2 6.25a2 2 0 1 1 4 0 2 2 0 0 1-4 0Z" fill="currentColor"/></svg>';
	}
}
