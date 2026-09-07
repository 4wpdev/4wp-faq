<?php
namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classic wp-admin dashboard (metabox widgets), not the Settings screen.
 */
class Admin_Dashboard {
	/**
	 * @var array<string, mixed>|null
	 */
	private static $data = null;

	/**
	 * Enqueue core dashboard scripts and register widgets.
	 */
	public static function on_load() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_script( 'dashboard' );
		wp_enqueue_style( 'dashboard' );
		if ( wp_is_mobile() ) {
			wp_enqueue_script( 'jquery-touch-punch' );
		}

		wp_enqueue_style(
			'forwp-faq-dashboard',
			FORWP_FAQ_PLUGIN_URL . 'assets/admin-dashboard.css',
			[ 'dashboard' ],
			FORWP_FAQ_VERSION
		);
		wp_add_inline_script( 'dashboard', self::category_tree_script() );

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		add_filter( 'screen_settings', [ __CLASS__, 'filter_screen_settings' ], 10, 2 );
		add_filter( 'admin_body_class', [ __CLASS__, 'body_class' ] );

		add_meta_box(
			'forwp_faq_status',
			__( 'FAQ Status', '4wp-faq' ),
			[ __CLASS__, 'render_status' ],
			$screen->id,
			'normal',
			'high'
		);

		add_meta_box(
			'forwp_faq_by_category',
			__( 'By category', '4wp-faq' ),
			[ __CLASS__, 'render_by_category' ],
			$screen->id,
			'column3',
			'default'
		);

		add_meta_box(
			'forwp_faq_top_reused',
			__( 'Most reused', '4wp-faq' ),
			[ __CLASS__, 'render_top_reused' ],
			$screen->id,
			'side',
			'high'
		);

		add_meta_box(
			'forwp_faq_uncategorized',
			__( 'Uncategorized', '4wp-faq' ),
			[ __CLASS__, 'render_uncategorized' ],
			$screen->id,
			'side',
			'default'
		);

		self::migrate_category_box( $screen );
	}

	/**
	 * Dashboard page markup (core metabox holder).
	 */
	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', '4wp-faq' ) );
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$columns = self::layout_columns( $screen );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Dashboard', '4wp-faq' ) . '</h1>';

		if ( isset( $_GET['forwp_faq_scan_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Registry scan completed.', '4wp-faq' );
			echo '</p></div>';
		}

		echo '<div id="dashboard-widgets-wrap">';
		echo '<div id="dashboard-widgets" class="metabox-holder columns-' . esc_attr( (string) $columns ) . '">';
		echo '<div id="postbox-container-1" class="postbox-container">';
		do_meta_boxes( $screen->id, 'normal', '' );
		echo '</div>';
		echo '<div id="postbox-container-2" class="postbox-container">';
		do_meta_boxes( $screen->id, 'side', '' );
		echo '</div>';
		echo '<div id="postbox-container-3" class="postbox-container">';
		do_meta_boxes( $screen->id, 'column3', '' );
		echo '</div>';
		echo '<div id="postbox-container-4" class="postbox-container">';
		do_meta_boxes( $screen->id, 'column4', '' );
		echo '</div>';
		echo '</div>';
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
		echo '</div></div>';
	}

	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		return $classes . ' forwp-faq-dashboard-page';
	}

	/**
	 * Screen Options: 50/50, 33/33/33, 33/66.
	 *
	 * @param string     $settings Existing extra settings HTML.
	 * @param \WP_Screen $screen   Current screen.
	 * @return string
	 */
	public static function filter_screen_settings( $settings, $screen ) {
		if ( ! $screen instanceof \WP_Screen || false === strpos( $screen->id, 'forwp-faq-dashboard' ) ) {
			return $settings;
		}

		$columns = self::layout_columns( $screen );

		ob_start();
		?>
		<fieldset class="columns-prefs forwp-faq-columns-prefs">
			<legend class="screen-layout"><?php esc_html_e( 'Layout', '4wp-faq' ); ?></legend>
			<label class="columns-prefs-2">
				<input type="radio" name="screen_columns" value="2" <?php checked( $columns, 2 ); ?> />
				<?php esc_html_e( '2 columns', '4wp-faq' ); ?>
			</label>
			<label class="columns-prefs-3">
				<input type="radio" name="screen_columns" value="3" <?php checked( $columns, 3 ); ?> />
				<?php esc_html_e( '3 columns', '4wp-faq' ); ?>
			</label>
			<label class="columns-prefs-4">
				<input type="radio" name="screen_columns" value="4" <?php checked( $columns, 4 ); ?> />
				<?php esc_html_e( '33 / 66', '4wp-faq' ); ?>
			</label>
		</fieldset>
		<?php

		return $settings . ob_get_clean();
	}

	/**
	 * @param \WP_Screen $screen Current screen.
	 * @return int 2, 3, or 4.
	 */
	private static function layout_columns( $screen ) {
		$columns = (int) get_user_option( 'screen_layout_' . $screen->id );
		if ( $columns < 2 || $columns > 4 ) {
			return 2;
		}

		return $columns;
	}

	/**
	 * One-time: move By category from the old 2-column order into column 3.
	 *
	 * @param \WP_Screen $screen Current screen.
	 */
	private static function migrate_category_box( $screen ) {
		$user_id = get_current_user_id();
		if ( $user_id < 1 || get_user_meta( $user_id, 'forwp_faq_dash_boxes_v2', true ) ) {
			return;
		}

		update_user_meta( $user_id, 'forwp_faq_dash_boxes_v2', '1' );

		$key   = 'meta-box-order_' . $screen->id;
		$order = get_user_option( $key );
		if ( ! is_array( $order ) ) {
			return;
		}

		$found = false;
		foreach ( [ 'normal', 'side', 'advanced', 'column4' ] as $ctx ) {
			if ( empty( $order[ $ctx ] ) || ! is_string( $order[ $ctx ] ) ) {
				continue;
			}

			$ids = array_values( array_filter( explode( ',', $order[ $ctx ] ) ) );
			if ( ! in_array( 'forwp_faq_by_category', $ids, true ) ) {
				continue;
			}

			$order[ $ctx ] = implode( ',', array_values( array_diff( $ids, [ 'forwp_faq_by_category' ] ) ) );
			$found         = true;
		}

		if ( ! $found ) {
			return;
		}

		$col3 = [];
		if ( ! empty( $order['column3'] ) && is_string( $order['column3'] ) ) {
			$col3 = array_values( array_filter( explode( ',', $order['column3'] ) ) );
		}
		if ( ! in_array( 'forwp_faq_by_category', $col3, true ) ) {
			$col3[] = 'forwp_faq_by_category';
		}
		$order['column3'] = implode( ',', $col3 );

		update_user_meta( $user_id, $key, $order );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function data() {
		if ( null === self::$data ) {
			self::$data = Plugin::get_dashboard_data();
		}

		return self::$data;
	}

	/**
	 * Status widget — WC-style metric rows.
	 */
	public static function render_status() {
		$data  = self::data();
		$stats = isset( $data['stats'] ) && is_array( $data['stats'] ) ? $data['stats'] : [];
		$list  = admin_url( 'edit.php?post_type=' . rawurlencode( Settings::get_post_type() ) );
		$cats  = isset( $data['categories_url'] ) ? (string) $data['categories_url'] : '';
		$setup = ! empty( $data['setup_complete'] );

		if ( ! $setup ) {
			$setup_url = isset( $data['setup_url'] ) ? (string) $data['setup_url'] : '';
			$action    = '';
			if ( $setup_url ) {
				$action = '<a class="button button-primary" href="' . esc_url( $setup_url ) . '">' . esc_html__( 'Open setup', '4wp-faq' ) . '</a>';
			}
			self::render_empty( __( 'Complete registry setup to see dashboard slices.', '4wp-faq' ), $action );
			return;
		}

		$rows = [
			[
				'class' => 'questions',
				'url'   => $list,
				'html'  => sprintf(
					/* translators: %s: count */
					__( 'Questions <strong>%s</strong>', '4wp-faq' ),
					number_format_i18n( (int) ( $stats['total_questions'] ?? 0 ) )
				),
			],
			[
				'class' => 'categories',
				'url'   => $cats,
				'html'  => sprintf(
					/* translators: %s: count */
					__( 'Categories <strong>%s</strong>', '4wp-faq' ),
					number_format_i18n( (int) ( $stats['faq_categories'] ?? 0 ) )
				),
			],
			[
				'class' => 'reused',
				'url'   => $list,
				'html'  => sprintf(
					/* translators: %s: count */
					__( 'Reused <strong>%s</strong>', '4wp-faq' ),
					number_format_i18n( (int) ( $stats['reused_questions'] ?? 0 ) )
				),
			],
			[
				'class' => 'once',
				'url'   => $list,
				'html'  => sprintf(
					/* translators: %s: count */
					__( 'Used once <strong>%s</strong>', '4wp-faq' ),
					number_format_i18n( (int) ( $stats['single_use_questions'] ?? 0 ) )
				),
			],
			[
				'class' => 'pages',
				'url'   => admin_url( 'edit.php?post_type=page' ),
				'html'  => sprintf(
					/* translators: %s: count */
					__( 'Pages with FAQ <strong>%s</strong>', '4wp-faq' ),
					number_format_i18n( (int) ( $stats['pages_with_faq'] ?? 0 ) )
				),
			],
			[
				'class' => 'posts',
				'url'   => admin_url( 'edit.php' ),
				'html'  => sprintf(
					/* translators: %s: count */
					__( 'Posts with FAQ <strong>%s</strong>', '4wp-faq' ),
					number_format_i18n( (int) ( $stats['posts_with_faq'] ?? 0 ) )
				),
			],
			[
				'class' => 'uncat',
				'url'   => $list,
				'html'  => sprintf(
					/* translators: %s: count */
					__( 'Uncategorized <strong>%s</strong>', '4wp-faq' ),
					number_format_i18n( (int) ( $stats['uncategorized'] ?? 0 ) )
				),
			],
			[
				'class' => 'unused',
				'url'   => $list,
				'html'  => sprintf(
					/* translators: %s: count */
					__( 'Unused <strong>%s</strong>', '4wp-faq' ),
					number_format_i18n( (int) ( $stats['unused_questions'] ?? 0 ) )
				),
			],
		];

		echo '<ul class="forwp-faq-status-list">';
		foreach ( $rows as $row ) {
			echo '<li class="' . esc_attr( $row['class'] ) . '">';
			echo '<a href="' . esc_url( $row['url'] ) . '">';
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

		if ( current_user_can( 'manage_options' ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="forwp-faq-dash-scan">';
			echo '<input type="hidden" name="action" value="forwp_faq_scan" />';
			echo '<input type="hidden" name="forwp_faq_redirect" value="' . esc_url( Admin_Settings::get_dashboard_url() ) . '" />';
			wp_nonce_field( 'forwp_faq_scan' );
			echo '<button type="submit" class="button">' . esc_html__( 'Rescan registry', '4wp-faq' ) . '</button>';
			echo '</form>';
		}
		echo '</div>';
	}

	/**
	 * Category slice widget — saved parent → child order, collapsible.
	 */
	public static function render_by_category() {
		$data  = self::data();
		$items = isset( $data['by_category'] ) && is_array( $data['by_category'] ) ? $data['by_category'] : [];

		if ( empty( $items ) ) {
			self::render_empty( __( 'No FAQ categories yet.', '4wp-faq' ) );
			return;
		}

		echo '<ul class="forwp-faq-cat-list">';
		self::render_category_rows( $items );
		echo '</ul>';
	}

	/**
	 * Render category rows recursively — collapsible when children exist.
	 *
	 * @param list<array<string, mixed>> $items  Tree nodes.
	 * @param int                        $depth  Nesting depth.
	 */
	private static function render_category_rows( $items, $depth = 0 ) {
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$name     = isset( $item['name'] ) ? (string) $item['name'] : '';
			$count    = isset( $item['count'] ) ? (int) $item['count'] : 0;
			$url      = isset( $item['url'] ) ? (string) $item['url'] : '';
			$children = isset( $item['children'] ) && is_array( $item['children'] ) ? $item['children'] : [];

			$term_id = isset( $item['id'] ) ? (int) $item['id'] : 0;

			if ( ! empty( $children ) ) {
				echo '<li class="forwp-faq-cat-row forwp-faq-cat-parent" data-depth="' . esc_attr( (string) $depth ) . '">';
				echo '<details data-term-id="' . esc_attr( (string) $term_id ) . '">';
				echo '<summary>';
				if ( $url ) {
					echo '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
				} else {
					echo '<span class="forwp-faq-cat-name">' . esc_html( $name ) . '</span>';
				}
				echo '<span class="forwp-faq-cat-count">' . esc_html( number_format_i18n( $count ) ) . '</span>';
				echo '</summary>';
				echo '<ul class="forwp-faq-cat-children">';
				self::render_category_rows( $children, $depth + 1 );
				echo '</ul>';
				echo '</details>';
				echo '</li>';
			} else {
				echo '<li class="forwp-faq-cat-row" data-depth="' . esc_attr( (string) $depth ) . '">';
				if ( $url ) {
					echo '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
				} else {
					echo '<span class="forwp-faq-cat-name">' . esc_html( $name ) . '</span>';
				}
				echo '<span class="forwp-faq-cat-count">' . esc_html( number_format_i18n( $count ) ) . '</span>';
				echo '</li>';
			}
		}
	}

	/**
	 * Persist collapsed/expanded category state in the browser.
	 *
	 * @return string
	 */
	private static function category_tree_script() {
		return <<<'JS'
jQuery(function($){
	const storageKey = 'forwpFaqDashboardCategoryState';
	const $details = $('#forwp_faq_by_category details[data-term-id]');
	if (!$details.length) {
		return;
	}

	let state = {};
	try {
		state = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {};
	} catch (e) {
		state = {};
	}

	$details.each(function(){
		const termId = String($(this).data('term-id') || '');
		if (!termId) {
			return;
		}

		if (Object.prototype.hasOwnProperty.call(state, termId)) {
			this.open = !!state[termId];
		} else {
			this.open = false;
		}
	});

	$details.on('toggle', function(){
		const termId = String($(this).data('term-id') || '');
		if (!termId) {
			return;
		}

		state[termId] = this.open;
		try {
			window.localStorage.setItem(storageKey, JSON.stringify(state));
		} catch (e) {
			// Ignore storage failures.
		}
	});
});
JS;
	}

	/**
	 * Most reused questions.
	 */
	public static function render_top_reused() {
		$data  = self::data();
		$items = isset( $data['top_reused'] ) && is_array( $data['top_reused'] ) ? $data['top_reused'] : [];

		if ( empty( $items ) ) {
			self::render_empty( __( 'No reused questions yet.', '4wp-faq' ) );
			return;
		}

		echo '<ul class="forwp-faq-dash-list">';
		foreach ( $items as $item ) {
			$title = isset( $item['title'] ) ? (string) $item['title'] : '';
			$count = isset( $item['count'] ) ? (int) $item['count'] : 0;
			$url   = isset( $item['edit_url'] ) ? (string) $item['edit_url'] : '';
			echo '<li>';
			if ( $url ) {
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
			} else {
				echo '<span>' . esc_html( $title ) . '</span>';
			}
			echo '<span class="forwp-faq-dash-count">' . esc_html( number_format_i18n( $count ) ) . '</span>';
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Questions with no category.
	 */
	public static function render_uncategorized() {
		$data  = self::data();
		$items = isset( $data['uncategorized'] ) && is_array( $data['uncategorized'] ) ? $data['uncategorized'] : [];

		if ( empty( $items ) ) {
			self::render_empty( __( 'Every question has a category.', '4wp-faq' ) );
			return;
		}

		echo '<ul class="forwp-faq-dash-list">';
		foreach ( $items as $item ) {
			$title = isset( $item['title'] ) ? (string) $item['title'] : '';
			$url   = isset( $item['edit_url'] ) ? (string) $item['edit_url'] : '';
			echo '<li>';
			if ( $url ) {
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
			} else {
				echo '<span>' . esc_html( $title ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Native empty-state block for dashboard widgets.
	 *
	 * @param string $message Empty copy.
	 * @param string $action  Optional HTML (already escaped).
	 */
	private static function render_empty( $message, $action = '' ) {
		echo '<div class="forwp-faq-dash-empty">';
		echo '<p>' . esc_html( $message ) . '</p>';
		if ( '' !== $action ) {
			echo '<p class="forwp-faq-dash-empty__action">' . $action . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped markup.
		}
		echo '</div>';
	}
}
