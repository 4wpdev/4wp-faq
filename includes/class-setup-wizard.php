<?php
namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional setup UI (React + @wordpress/components). Never redirects or blocks admin.
 */
class Setup_Wizard {
	public const PAGE_SLUG            = 'forwp-faq-setup';
	public const ACTIVATION_TRANSIENT = 'forwp_faq_activation_notice';

	/**
	 * Register admin hooks.
	 */
	public static function init() {
		delete_transient( 'forwp_faq_activation_redirect' );

		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'maybe_flag_activation_notice' ] );
		add_action( 'admin_notices', [ __CLASS__, 'render_admin_notices' ] );
	}

	/**
	 * Settings submenu — not a top-level trap.
	 */
	public static function register_menu() {
		if ( Settings::is_setup_complete() ) {
			return;
		}

		add_options_page(
			__( '4WP FAQ Setup', '4wp-faq' ),
			__( '4WP FAQ Setup', '4wp-faq' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * After activation: flag a dashboard notice only (no redirect).
	 */
	public static function maybe_flag_activation_notice() {
		if ( ! get_transient( self::ACTIVATION_TRANSIENT ) ) {
			return;
		}

		delete_transient( self::ACTIVATION_TRANSIENT );

		if ( Settings::is_setup_pending() ) {
			update_option( 'forwp_faq_show_setup_notice', '1' );
		}
	}

	/**
	 * Lightweight notices; setup actions live in React / REST.
	 */
	public static function render_admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['forwp_faq_setup_skipped'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'FAQ registry setup skipped. Blocks and JSON-LD schema remain active.', '4wp-faq' )
			);
			return;
		}

		if ( ! Settings::is_setup_pending() || ! get_option( 'forwp_faq_show_setup_notice', '' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'dashboard' === $screen->id ) {
			return;
		}

		$setup_url = self::get_page_url();
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<?php esc_html_e( '4WP FAQ: schema is active on the front end. Set up the optional FAQ registry from the Dashboard widget or Settings → 4WP FAQ Setup.', '4wp-faq' ); ?>
				<a class="button button-secondary" style="margin-left: 8px;" href="<?php echo esc_url( $setup_url ); ?>">
					<?php esc_html_e( 'Open setup', '4wp-faq' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * @return string
	 */
	public static function get_page_url() {
		return admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Full setup page — React root only (WP components, no custom CSS bundle).
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', '4wp-faq' ) );
		}

		self::enqueue_setup_scripts( 'page' );
		echo '<div data-forwp-faq-setup-root data-context="page"></div>';
	}

	/**
	 * Enqueue setup app + config for dashboard widget or options page.
	 *
	 * @param string $context page|widget.
	 */
	public static function enqueue_setup_scripts( $context = 'page' ) {
		$asset_file = FORWP_FAQ_PLUGIN_DIR . 'build/setup.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: [
				'dependencies' => [ 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
				'version'      => FORWP_FAQ_VERSION,
			];

		wp_enqueue_script(
			'forwp-faq-setup',
			FORWP_FAQ_PLUGIN_URL . 'build/setup.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'forwp-faq-setup', '4wp-faq' );

		wp_localize_script(
			'forwp-faq-setup',
			'forwpFaqSetup',
			[
				'suggestedPostType' => Settings::get_suggested_post_type_slug(),
				'defaultTaxonomy'   => Settings::DEFAULT_TAXONOMY,
				'hasLegacyPosts'    => Settings::has_legacy_registry_posts(),
				'isSkipped'         => Settings::is_setup_skipped(),
				'dashboardUrl'      => admin_url(),
				'setupUrl'          => self::get_page_url(),
				'context'           => $context,
			]
		);

		wp_enqueue_style( 'wp-components' );
	}
}
