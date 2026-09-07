<?php
namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FAQ Settings screen — React shell (same pattern as 4wp-weather admin).
 */
class Admin_Settings {
	public const PAGE_SLUG      = 'forwp-faq-settings';
	public const DASHBOARD_SLUG = 'forwp-faq-dashboard';
	public const ADD_GUIDE_SLUG = 'forwp-faq-add-guide';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 20 );
		add_action( 'admin_menu', [ __CLASS__, 'reorder_menu' ], 99 );
		add_filter( 'parent_file', [ __CLASS__, 'filter_parent_file' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
	}

	/**
	 * Settings under the FAQ registry CPT menu.
	 */
	public static function register_menu() {
		if ( Settings::is_setup_complete() ) {
			$post_type = Settings::get_post_type();
			$parent    = 'edit.php?post_type=' . $post_type;

			$dash_hook = add_submenu_page(
				$parent,
				__( 'Dashboard', '4wp-faq' ),
				__( 'Dashboard', '4wp-faq' ),
				'edit_posts',
				self::DASHBOARD_SLUG,
				[ Admin_Dashboard::class, 'render' ]
			);

			if ( is_string( $dash_hook ) ) {
				add_action( 'load-' . $dash_hook, [ Admin_Dashboard::class, 'on_load' ] );
			}

			add_submenu_page(
				$parent,
				__( 'Add FAQ', '4wp-faq' ),
				__( 'Add FAQ', '4wp-faq' ),
				'edit_posts',
				self::ADD_GUIDE_SLUG,
				[ __CLASS__, 'render_add_guide' ]
			);

			add_submenu_page(
				$parent,
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
	 * @return string
	 */
	public static function get_dashboard_url() {
		if ( ! Settings::is_setup_complete() ) {
			return self::get_page_url();
		}

		return admin_url( 'edit.php?post_type=' . rawurlencode( Settings::get_post_type() ) . '&page=' . self::DASHBOARD_SLUG );
	}

	/**
	 * @return string
	 */
	public static function get_add_guide_url() {
		if ( ! Settings::is_setup_complete() ) {
			return self::get_page_url();
		}

		return admin_url( 'edit.php?post_type=' . rawurlencode( Settings::get_post_type() ) . '&page=' . self::ADD_GUIDE_SLUG );
	}

	/**
	 * Put Dashboard first under the FAQ CPT menu.
	 */
	public static function reorder_menu() {
		if ( ! Settings::is_setup_complete() ) {
			return;
		}

		global $submenu;

		$parent = 'edit.php?post_type=' . Settings::get_post_type();
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}

		$dashboard = null;
		$rest      = [];

		foreach ( $submenu[ $parent ] as $item ) {
			if ( isset( $item[2] ) && self::DASHBOARD_SLUG === $item[2] ) {
				$dashboard = $item;
				continue;
			}
			$rest[] = $item;
		}

		if ( $dashboard ) {
			$submenu[ $parent ] = array_merge( [ $dashboard ], $rest );
		}
	}

	/**
	 * Keep the FAQ CPT menu open on Dashboard / Settings.
	 *
	 * @param string $parent_file Parent file.
	 * @return string
	 */
	public static function filter_parent_file( $parent_file ) {
		if ( ! Settings::is_setup_complete() ) {
			return $parent_file;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::DASHBOARD_SLUG === $page || self::PAGE_SLUG === $page || self::ADD_GUIDE_SLUG === $page ) {
			return 'edit.php?post_type=' . Settings::get_post_type();
		}

		return $parent_file;
	}

	/**
	 * @param string $hook_suffix Admin page hook.
	 * @return bool
	 */
	public static function is_settings_screen( $hook_suffix ) {
		if ( ! is_string( $hook_suffix ) ) {
			return false;
		}

		return false !== strpos( $hook_suffix, self::PAGE_SLUG );
	}

	/**
	 * @param string $hook_suffix Admin page hook.
	 * @return bool
	 */
	public static function is_add_guide_screen( $hook_suffix ) {
		return is_string( $hook_suffix ) && false !== strpos( $hook_suffix, self::ADD_GUIDE_SLUG );
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
	 * How to add FAQs — replaces the native post-new.php editor.
	 */
	public static function render_add_guide() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', '4wp-faq' ) );
		}

		$settings_url = self::get_page_url();
		$list_url     = admin_url( 'edit.php?post_type=' . rawurlencode( Settings::get_post_type() ) );

		echo '<div class="wrap forwp-faq-admin-shell">';
		echo '<h1 class="forwp-faq-admin-heading">';
		echo '<span class="forwp-faq-admin-heading__icon" aria-hidden="true">';
		echo wp_kses( self::heading_svg(), self::heading_svg_allowed_html() );
		echo '</span>';
		echo '<span class="forwp-faq-admin-heading__text">';
		echo esc_html__( 'Add FAQ', '4wp-faq' );
		echo '</span>';
		echo '</h1>';

		echo '<div class="forwp-faq-guide">';
		echo '<p class="forwp-faq-guide__lead">';
		echo esc_html__( 'Do not create registry posts by hand. Questions are collected from pages after a scan.', '4wp-faq' );
		echo '</p>';

		echo '<ol class="forwp-faq-guide__steps">';
		echo '<li>' . esc_html__( 'Open a page or post in the editor.', '4wp-faq' ) . '</li>';
		echo '<li>' . esc_html__( 'Add a core Accordion (or select an existing one).', '4wp-faq' ) . '</li>';
		echo '<li>' . esc_html__( 'In the block toolbar, click Convert to 4WP FAQ, or use Transform to → 4WP FAQ. You can convert several Details or Accordion Items at once.', '4wp-faq' ) . '</li>';
		echo '<li>' . esc_html__( 'Write the question in the Accordion heading and the answer in the panel.', '4wp-faq' ) . '</li>';
		echo '<li>' . wp_kses(
			sprintf(
				/* translators: %s: Settings admin URL */
				__( 'Go to <a href="%s">FAQ → Settings</a> and click Rescan registry.', '4wp-faq' ),
				esc_url( $settings_url )
			),
			[ 'a' => [ 'href' => true ] ]
		) . '</li>';
		echo '<li>' . wp_kses(
			sprintf(
				/* translators: %s: All FAQs admin URL */
				__( 'The question appears under <a href="%s">All FAQs</a>.', '4wp-faq' ),
				esc_url( $list_url )
			),
			[ 'a' => [ 'href' => true ] ]
		) . '</li>';
		echo '</ol>';

		echo '<p class="forwp-faq-guide__hub">';
		echo esc_html__( 'FAQ hub page: add 4WP FAQ List and 4WP FAQ Categories. Optional: core Search with “Filter 4WP FAQ List” in the sidebar.', '4wp-faq' );
		echo '</p>';

		echo '<p class="forwp-faq-guide__video">';
		echo esc_html__( 'Video walkthrough — coming soon.', '4wp-faq' );
		echo '</p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		$is_app   = self::is_settings_screen( $hook_suffix );
		$is_guide = self::is_add_guide_screen( $hook_suffix );
		if ( ! $is_app && ! $is_guide ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$asset_file = FORWP_FAQ_PLUGIN_DIR . 'build/admin/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		$style_path = FORWP_FAQ_PLUGIN_DIR . 'build/admin/style-index.css';
		if ( is_readable( $style_path ) ) {
			wp_enqueue_style(
				'forwp-faq-admin',
				FORWP_FAQ_PLUGIN_URL . 'build/admin/style-index.css',
				$is_app ? [ 'wp-components' ] : [],
				$asset['version']
			);
		}

		if ( ! $is_app ) {
			return;
		}

		wp_enqueue_style( 'wp-components' );

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
