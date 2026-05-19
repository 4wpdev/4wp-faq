<?php
namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Short dashboard widget — link to full setup wizard only (WC-style).
 */
class Dashboard_Setup {
	/**
	 * Register widget when registry setup is not complete.
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', [ __CLASS__, 'register_widget' ] );
	}

	/**
	 * @return void
	 */
	public static function register_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( Settings::is_setup_complete() ) {
			return;
		}

		wp_add_dashboard_widget(
			'forwp_faq_dashboard_setup',
			__( '4WP FAQ Setup', '4wp-faq' ),
			[ __CLASS__, 'render_widget' ]
		);
	}

	/**
	 * Minimal CTA: open Settings → 4WP FAQ Setup (slugs configured there).
	 */
	public static function render_widget() {
		$setup_url = Setup_Wizard::get_page_url();
		?>
		<p>
			<strong><?php esc_html_e( 'Step 1 of 1', '4wp-faq' ); ?></strong>
		</p>
		<p><?php esc_html_e( 'Set up the optional FAQ registry.', '4wp-faq' ); ?></p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $setup_url ); ?>">
				<?php esc_html_e( 'Open setup', '4wp-faq' ); ?>
			</a>
		</p>
		<?php
	}
}
