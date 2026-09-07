<?php
/**
 * FAQ category term fields, document SEO, and H1 swap on category URLs.
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Term meta for FAQ categories and front-end document overrides.
 */
class Faq_Terms {
	public const META_DISPLAY_TITLE = 'forwp_display_title';
	public const META_SEO_TITLE     = 'forwp_seo_title';
	public const META_SEO_DESC      = 'forwp_seo_description';
	public const PRIMARY_META       = '_forwp_faq_primary_term';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_term_meta' ], 20 );
		add_action( 'wp_head', [ __CLASS__, 'render_meta_description' ], 1 );
		add_filter( 'pre_get_document_title', [ __CLASS__, 'filter_document_title' ], 99 );
		add_filter( 'document_title_parts', [ __CLASS__, 'filter_document_title_parts' ], 99 );
		add_filter( 'render_block_core/heading', [ __CLASS__, 'filter_heading_block' ], 10, 2 );

		add_filter( 'wpseo_title', [ __CLASS__, 'filter_plain_title' ], 99 );
		add_filter( 'wpseo_metadesc', [ __CLASS__, 'filter_plain_description' ], 20 );
		add_filter( 'wpseo_opengraph_title', [ __CLASS__, 'filter_plain_title' ], 99 );
		add_filter( 'wpseo_opengraph_desc', [ __CLASS__, 'filter_plain_description' ], 20 );
		add_filter( 'wpseo_twitter_title', [ __CLASS__, 'filter_plain_title' ], 99 );
		add_filter( 'wpseo_twitter_description', [ __CLASS__, 'filter_plain_description' ], 20 );

		add_filter( 'rank_math/frontend/title', [ __CLASS__, 'filter_plain_title' ], 99 );
		add_filter( 'rank_math/frontend/description', [ __CLASS__, 'filter_plain_description' ], 20 );
		add_filter( 'rank_math/opengraph/facebook/title', [ __CLASS__, 'filter_plain_title' ], 20 );
		add_filter( 'rank_math/opengraph/facebook/description', [ __CLASS__, 'filter_plain_description' ], 20 );

		if ( is_admin() ) {
			add_action( 'admin_init', [ __CLASS__, 'register_term_form_hooks' ] );
		}
	}

	/**
	 * Bind add/edit form fields to the configured taxonomy slug.
	 */
	public static function register_term_form_hooks() {
		if ( ! Settings::is_setup_complete() ) {
			return;
		}

		$taxonomy = Settings::get_taxonomy();
		add_action( $taxonomy . '_add_form_fields', [ __CLASS__, 'render_add_fields' ] );
		add_action( $taxonomy . '_edit_form_fields', [ __CLASS__, 'render_edit_fields' ], 10, 1 );
		add_action( 'created_' . $taxonomy, [ __CLASS__, 'save_term_meta' ] );
		add_action( 'edited_' . $taxonomy, [ __CLASS__, 'save_term_meta' ] );
	}

	/**
	 * Register term meta for REST and sanitizing.
	 */
	public static function register_term_meta() {
		if ( ! Settings::is_setup_complete() ) {
			return;
		}

		$taxonomy = Settings::get_taxonomy();
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		foreach (
			[
				self::META_DISPLAY_TITLE => __( 'Display title', '4wp-faq' ),
				self::META_SEO_TITLE     => __( 'SEO title', '4wp-faq' ),
				self::META_SEO_DESC      => __( 'SEO description', '4wp-faq' ),
			] as $key => $description
		) {
			register_term_meta(
				$taxonomy,
				$key,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static function () {
						return current_user_can( 'manage_categories' );
					},
					'description'       => $description,
				]
			);
		}
	}

	/**
	 * Fields on Add FAQ Category.
	 */
	public static function render_add_fields() {
		?>
		<div class="form-field">
			<label for="forwp_display_title"><?php esc_html_e( 'Display title', '4wp-faq' ); ?></label>
			<input type="text" name="forwp_display_title" id="forwp_display_title" value="" />
			<p class="description"><?php esc_html_e( 'Extended heading used as the page H1 on SEO category URLs. Empty: category name.', '4wp-faq' ); ?></p>
		</div>
		<div class="form-field">
			<label for="forwp_seo_title"><?php esc_html_e( 'SEO title', '4wp-faq' ); ?></label>
			<input type="text" name="forwp_seo_title" id="forwp_seo_title" value="" />
			<p class="description"><?php esc_html_e( 'Document title on SEO category URLs. If filled, used as-is (no site name suffix). Empty: display title + site name.', '4wp-faq' ); ?></p>
		</div>
		<div class="form-field">
			<label for="forwp_seo_description"><?php esc_html_e( 'SEO description', '4wp-faq' ); ?></label>
			<textarea name="forwp_seo_description" id="forwp_seo_description" rows="4"></textarea>
			<p class="description"><?php esc_html_e( 'Meta description on SEO category URLs.', '4wp-faq' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Fields on Edit FAQ Category.
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function render_edit_fields( $term ) {
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		$display = (string) get_term_meta( $term->term_id, self::META_DISPLAY_TITLE, true );
		$seo     = (string) get_term_meta( $term->term_id, self::META_SEO_TITLE, true );
		$desc    = (string) get_term_meta( $term->term_id, self::META_SEO_DESC, true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="forwp_display_title"><?php esc_html_e( 'Display title', '4wp-faq' ); ?></label></th>
			<td>
				<input type="text" name="forwp_display_title" id="forwp_display_title" value="<?php echo esc_attr( $display ); ?>" />
				<p class="description"><?php esc_html_e( 'Extended heading used as the page H1 on SEO category URLs. Empty: category name.', '4wp-faq' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="forwp_seo_title"><?php esc_html_e( 'SEO title', '4wp-faq' ); ?></label></th>
			<td>
				<input type="text" name="forwp_seo_title" id="forwp_seo_title" value="<?php echo esc_attr( $seo ); ?>" />
				<p class="description"><?php esc_html_e( 'Document title on SEO category URLs. If filled, used as-is (no site name suffix). Empty: display title + site name.', '4wp-faq' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="forwp_seo_description"><?php esc_html_e( 'SEO description', '4wp-faq' ); ?></label></th>
			<td>
				<textarea name="forwp_seo_description" id="forwp_seo_description" rows="4"><?php echo esc_textarea( $desc ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Meta description on SEO category URLs.', '4wp-faq' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * @param int $term_id Term ID.
	 */
	public static function save_term_meta( $term_id ) {
		$term_id = (int) $term_id;
		if ( $term_id <= 0 || ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$fields = [
			self::META_DISPLAY_TITLE => 'forwp_display_title',
			self::META_SEO_TITLE     => 'forwp_seo_title',
			self::META_SEO_DESC      => 'forwp_seo_description',
		];

		foreach ( $fields as $meta_key => $post_key ) {
			if ( ! isset( $_POST[ $post_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				continue;
			}

			$value = sanitize_text_field( wp_unslash( (string) $_POST[ $post_key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' === $value ) {
				delete_term_meta( $term_id, $meta_key );
			} else {
				update_term_meta( $term_id, $meta_key, $value );
			}
		}
	}

	/**
	 * Active FAQ category term for the current request, if any.
	 *
	 * @return \WP_Term|null
	 */
	public static function get_active_term() {
		$slug = Faq_Filter::get_active_slug();
		if ( '' === $slug ) {
			return null;
		}

		$taxonomy = Settings::get_taxonomy();
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}

		$term = get_term_by( 'slug', $slug, $taxonomy );

		return $term instanceof \WP_Term ? $term : null;
	}

	/**
	 * Term for document H1 / title / meta — pretty category URLs only.
	 *
	 * @return \WP_Term|null
	 */
	public static function get_seo_term() {
		if ( ! Faq_Filter::is_pretty_category_request() ) {
			return null;
		}

		return self::get_active_term();
	}

	/**
	 * H1 / group heading: custom display title, else term name.
	 *
	 * @param \WP_Term $term Term.
	 * @return string
	 */
	public static function get_display_title( $term ) {
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}

		$custom = get_term_meta( (int) $term->term_id, self::META_DISPLAY_TITLE, true );
		if ( is_string( $custom ) && '' !== trim( $custom ) ) {
			return trim( $custom );
		}

		return (string) $term->name;
	}

	/**
	 * Custom SEO title when the term field is filled.
	 *
	 * @param \WP_Term $term Term.
	 * @return string Empty when the field is not set.
	 */
	public static function get_custom_seo_title( $term ) {
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}

		$custom = get_term_meta( (int) $term->term_id, self::META_SEO_TITLE, true );

		return is_string( $custom ) ? trim( $custom ) : '';
	}

	/**
	 * Document title for a category URL (custom SEO title or display title).
	 *
	 * @param \WP_Term $term Term.
	 * @return string
	 */
	public static function get_seo_title( $term ) {
		$custom = self::get_custom_seo_title( $term );

		return '' !== $custom ? $custom : self::get_display_title( $term );
	}

	/**
	 * Full document title: custom SEO title as-is, otherwise display title + site name.
	 *
	 * @param \WP_Term $term Term.
	 * @return string
	 */
	public static function get_document_title( $term ) {
		$custom = self::get_custom_seo_title( $term );
		if ( '' !== $custom ) {
			return $custom;
		}

		$seo  = self::get_seo_title( $term );
		$site = get_bloginfo( 'name', 'display' );
		$site = is_string( $site ) ? trim( $site ) : '';

		if ( '' === $seo ) {
			return $site;
		}

		if ( '' === $site || false !== strpos( $seo, $site ) ) {
			return $seo;
		}

		return $seo . ' – ' . $site;
	}

	/**
	 * Meta description for a category URL.
	 *
	 * @param \WP_Term $term Term.
	 * @return string
	 */
	public static function get_seo_description( $term ) {
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}

		$custom = get_term_meta( (int) $term->term_id, self::META_SEO_DESC, true );

		return is_string( $custom ) ? trim( $custom ) : '';
	}

	/**
	 * @param string $title Current title.
	 * @return string
	 */
	public static function filter_document_title( $title ) {
		$term = self::get_seo_term();
		if ( ! $term instanceof \WP_Term ) {
			return $title;
		}

		$seo = self::get_document_title( $term );

		return '' !== $seo ? $seo : $title;
	}

	/**
	 * @param array<string, string> $parts Title parts.
	 * @return array<string, string>
	 */
	public static function filter_document_title_parts( $parts ) {
		$term = self::get_seo_term();
		if ( ! $term instanceof \WP_Term || ! is_array( $parts ) ) {
			return $parts;
		}

		$custom = self::get_custom_seo_title( $term );
		if ( '' !== $custom ) {
			$parts['title'] = $custom;
			unset( $parts['site'], $parts['tagline'] );

			return $parts;
		}

		$seo = self::get_seo_title( $term );
		if ( '' !== $seo ) {
			$parts['title'] = $seo;
		}

		return $parts;
	}

	/**
	 * @param string $title Title from Yoast / Rank Math.
	 * @return string
	 */
	public static function filter_plain_title( $title ) {
		$term = self::get_seo_term();
		if ( ! $term instanceof \WP_Term ) {
			return $title;
		}

		$seo = self::get_document_title( $term );

		return '' !== $seo ? $seo : $title;
	}

	/**
	 * @param string $description Description from Yoast / Rank Math.
	 * @return string
	 */
	public static function filter_plain_description( $description ) {
		$term = self::get_seo_term();
		if ( ! $term instanceof \WP_Term ) {
			return $description;
		}

		$desc = self::get_seo_description( $term );

		return '' !== $desc ? $desc : $description;
	}

	/**
	 * Native meta description when no SEO plugin owns the tag.
	 */
	public static function render_meta_description() {
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}

		$term = self::get_seo_term();
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		$desc = self::get_seo_description( $term );
		if ( '' === $desc ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
	}

	/**
	 * Replace the first core Heading H1 with the category display title.
	 *
	 * @param string               $content Block HTML.
	 * @param array<string, mixed> $block   Parsed block.
	 * @return string
	 */
	public static function filter_heading_block( $content, $block ) {
		static $replaced = false;

		if ( $replaced || ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		$term = self::get_seo_term();
		if ( ! $term instanceof \WP_Term ) {
			return $content;
		}

		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
		$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : 2;
		if ( 1 !== $level ) {
			return $content;
		}

		$heading = self::get_display_title( $term );
		if ( '' === $heading ) {
			return $content;
		}

		$updated = preg_replace(
			'/(<h1\b[^>]*>)(.*?)(<\/h1>)/is',
			'$1' . esc_html( $heading ) . '$3',
			$content,
			1
		);

		if ( is_string( $updated ) && $updated !== $content ) {
			$replaced = true;
			return $updated;
		}

		return $content;
	}

	/**
	 * Primary FAQ category term ID for a registry post.
	 *
	 * @param int $post_id Registry post ID.
	 * @return int
	 */
	public static function get_primary_term_id( $post_id ) {
		$post_id = (int) $post_id;
		$ids     = Registry_Content::get_post_term_ids( $post_id );
		if ( empty( $ids ) ) {
			return 0;
		}

		$primary = (int) get_post_meta( $post_id, self::PRIMARY_META, true );
		if ( $primary > 0 && in_array( $primary, $ids, true ) ) {
			return $primary;
		}

		return (int) $ids[0];
	}
}
