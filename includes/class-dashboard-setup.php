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
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
	}

	/**
	 * Status list styles on the main Dashboard.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue( $hook ) {
		if ( 'index.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'forwp-faq-dashboard',
			FORWP_FAQ_PLUGIN_URL . 'assets/admin-dashboard.css',
			[],
			FORWP_FAQ_VERSION
		);
	}

	/**
	 * @return void
	 */
	public static function register_widget() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! Settings::is_setup_complete() ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			wp_add_dashboard_widget(
				'forwp_faq_dashboard_setup',
				__( '4WP FAQ Setup', '4wp-faq' ),
				[ __CLASS__, 'render_widget' ]
			);
			return;
		}

		wp_add_dashboard_widget(
			'forwp_faq_home',
			__( 'FAQ', '4wp-faq' ),
			[ __CLASS__, 'render_home' ]
		);
	}

	/**
	 * Condensed status on the main Dashboard, with a link to the FAQ dashboard.
	 */
	public static function render_home() {
		$data  = Plugin::get_dashboard_data();
		$stats = isset( $data['stats'] ) && is_array( $data['stats'] ) ? $data['stats'] : [];
		$url   = Admin_Settings::get_dashboard_url();
		$list  = admin_url( 'edit.php?post_type=' . rawurlencode( Settings::get_post_type() ) );
		$cats  = isset( $data['categories_url'] ) ? (string) $data['categories_url'] : $list;

		$rows = [
			[
				'class' => 'questions',
				'href'  => $list,
				'html'  => sprintf(
					/* translators: %s: count */
					__( 'Questions <strong>%s</strong>', '4wp-faq' ),
					number_format_i18n( (int) ( $stats['total_questions'] ?? 0 ) )
				),
			],
			[
				'class' => 'categories',
				'href'  => $cats,
				'html'  => sprintf(
					/* translators: %s: count */
					__( 'Categories <strong>%s</strong>', '4wp-faq' ),
					number_format_i18n( (int) ( $stats['faq_categories'] ?? 0 ) )
				),
			],
			[
				'class' => 'reused',
				'href'  => $list,
				'html'  => sprintf(
					/* translators: %s: count */
					__( 'Reused <strong>%s</strong>', '4wp-faq' ),
					number_format_i18n( (int) ( $stats['reused_questions'] ?? 0 ) )
				),
			],
			[
				'class' => 'uncat',
				'href'  => $list,
				'html'  => sprintf(
					/* translators: %s: count */
					__( 'Uncategorized <strong>%s</strong>', '4wp-faq' ),
					number_format_i18n( (int) ( $stats['uncategorized'] ?? 0 ) )
				),
			],
		];

		Admin_Dashboard::render_language_note( $data );

		echo '<ul class="forwp-faq-status-list">';
		foreach ( $rows as $row ) {
			echo '<li class="' . esc_attr( $row['class'] ) . '">';
			echo '<a href="' . esc_url( $row['href'] ) . '">';
			echo wp_kses( $row['html'], [ 'strong' => [] ] );
			echo '</a></li>';
		}
		echo '</ul>';

		echo '<div class="forwp-faq-dash-meta">';
		echo '<span>';
		if ( ! empty( $data['last_scan_label'] ) ) {
			echo esc_html(
				sprintf(
					/* translators: %s: date/time */
					__( 'Last scan: %s', '4wp-faq' ),
					$data['last_scan_label']
				)
			);
		} else {
			echo esc_html__( 'Not scanned yet.', '4wp-faq' );
		}
		echo '</span>';
		echo '<a class="button button-primary" href="' . esc_url( $url ) . '">';
		echo esc_html__( 'Open dashboard', '4wp-faq' );
		echo '</a>';
		echo '</div>';
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
