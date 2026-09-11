<?php
/**
 * FAQ category term fields, document SEO, and native title/description swap on category URLs.
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
	public const META_SEO_IMAGE     = 'forwp_seo_image';
	public const PRIMARY_META       = '_forwp_faq_primary_term';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_term_meta' ], 20 );
		add_action( 'wp_head', [ __CLASS__, 'render_meta_description' ], 1 );
		add_filter( 'pre_get_document_title', [ __CLASS__, 'filter_document_title' ], 99 );
		add_filter( 'document_title_parts', [ __CLASS__, 'filter_document_title_parts' ], 99 );

		add_filter( 'render_block_core/post-title', [ __CLASS__, 'filter_title_block' ], 10, 3 );
		add_filter( 'render_block_core/query-title', [ __CLASS__, 'filter_title_block' ], 10, 3 );
		add_filter( 'render_block_core/term-name', [ __CLASS__, 'filter_title_block' ], 10, 3 );
		add_filter( 'render_block_core/post-excerpt', [ __CLASS__, 'filter_description_block' ], 10, 3 );
		add_filter( 'render_block_core/term-description', [ __CLASS__, 'filter_description_block' ], 10, 3 );

		add_filter( 'get_canonical_url', [ __CLASS__, 'filter_canonical_url' ], 20, 2 );
		add_filter( 'wpseo_canonical', [ __CLASS__, 'filter_pretty_canonical' ], 20 );
		add_filter( 'wpseo_opengraph_url', [ __CLASS__, 'filter_pretty_canonical' ], 20 );
		add_filter( 'wpseo_opengraph_image', [ __CLASS__, 'filter_opengraph_image' ], 20 );
		add_filter( 'wpseo_twitter_image', [ __CLASS__, 'filter_opengraph_image' ], 20 );

		add_filter( 'wpseo_title', [ __CLASS__, 'filter_plain_title' ], 99 );
		add_filter( 'wpseo_metadesc', [ __CLASS__, 'filter_plain_description' ], 20 );
		add_filter( 'wpseo_opengraph_title', [ __CLASS__, 'filter_plain_title' ], 99 );
		add_filter( 'wpseo_opengraph_desc', [ __CLASS__, 'filter_plain_description' ], 20 );
		add_filter( 'wpseo_twitter_title', [ __CLASS__, 'filter_plain_title' ], 99 );
		add_filter( 'wpseo_twitter_description', [ __CLASS__, 'filter_plain_description' ], 20 );

		add_filter( 'rank_math/frontend/title', [ __CLASS__, 'filter_plain_title' ], 99 );
		add_filter( 'rank_math/frontend/description', [ __CLASS__, 'filter_plain_description' ], 20 );
		add_filter( 'rank_math/frontend/canonical', [ __CLASS__, 'filter_pretty_canonical' ], 20 );
		add_filter( 'rank_math/opengraph/facebook/title', [ __CLASS__, 'filter_plain_title' ], 20 );
		add_filter( 'rank_math/opengraph/facebook/description', [ __CLASS__, 'filter_plain_description' ], 20 );
		add_filter( 'rank_math/opengraph/facebook/image', [ __CLASS__, 'filter_opengraph_image' ], 20 );
		add_filter( 'rank_math/opengraph/twitter/image', [ __CLASS__, 'filter_opengraph_image' ], 20 );

		if ( is_admin() ) {
			add_action( 'admin_init', [ __CLASS__, 'register_term_form_hooks' ] );
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_term_image_assets' ] );
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
	 * Media library picker for the category SEO image.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function enqueue_term_image_assets( $hook ) {
		if ( ! Settings::is_setup_complete() ) {
			return;
		}

		$taxonomy = Settings::get_taxonomy();
		$screen   = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! in_array( $hook, [ 'edit-tags.php', 'term.php' ], true ) ) {
			return;
		}

		if ( ! is_object( $screen ) || ! isset( $screen->taxonomy ) || $screen->taxonomy !== $taxonomy ) {
			return;
		}

		wp_enqueue_media();
		wp_add_inline_script(
			'jquery',
			'(function($){$(function(){var frame;$(".forwp-faq-seo-image-select").on("click",function(e){e.preventDefault();if(frame){frame.open();return;}frame=wp.media({title:"' . esc_js( __( 'Select image', '4wp-faq' ) ) . '",button:{text:"' . esc_js( __( 'Use image', '4wp-faq' ) ) . '"},multiple:false});frame.on("select",function(){var a=frame.state().get("selection").first().toJSON();var src=(a.sizes&&a.sizes.medium)?a.sizes.medium.url:a.url;$("#forwp_seo_image").val(a.id);$("#forwp_seo_image_preview").html("<img src=\\""+src+"\\" alt=\\"\\" style=\\"max-width:220px;height:auto\\" />");});frame.open();});$(".forwp-faq-seo-image-remove").on("click",function(e){e.preventDefault();$("#forwp_seo_image").val("");$("#forwp_seo_image_preview").empty();});});}(jQuery));'
		);
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

		register_term_meta(
			$taxonomy,
			self::META_SEO_IMAGE,
			[
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => static function () {
					return current_user_can( 'manage_categories' );
				},
				'description'       => __( 'SEO image', '4wp-faq' ),
			]
		);
	}

	/**
	 * Fields on Add FAQ Category.
	 */
	public static function render_add_fields() {
		?>
		<div class="form-field">
			<label for="forwp_display_title"><?php esc_html_e( 'Display title', '4wp-faq' ); ?></label>
			<input type="text" name="forwp_display_title" id="forwp_display_title" value="" />
			<p class="description"><?php esc_html_e( 'On-page title on pretty category URLs (core Title, Query Title, or Term Name). Empty: category name.', '4wp-faq' ); ?></p>
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
		<div class="form-field">
			<label><?php esc_html_e( 'SEO image', '4wp-faq' ); ?></label>
			<?php self::render_image_control( 0 ); ?>
			<p class="description"><?php esc_html_e( 'Open Graph image on pretty category URLs. Empty: hub page image.', '4wp-faq' ); ?></p>
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
				<p class="description"><?php esc_html_e( 'On-page title on pretty category URLs (core Title, Query Title, or Term Name). Empty: category name.', '4wp-faq' ); ?></p>
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
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'SEO image', '4wp-faq' ); ?></th>
			<td>
				<?php self::render_image_control( (int) $term->term_id ); ?>
				<p class="description"><?php esc_html_e( 'Open Graph image on pretty category URLs. Empty: hub page image.', '4wp-faq' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Image picker markup for add/edit term forms.
	 *
	 * @param int $term_id Term ID (0 on add).
	 */
	private static function render_image_control( $term_id ) {
		$image_id = $term_id > 0 ? (int) get_term_meta( $term_id, self::META_SEO_IMAGE, true ) : 0;
		$src      = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<input type="hidden" name="forwp_seo_image" id="forwp_seo_image" value="<?php echo esc_attr( (string) $image_id ); ?>" />
		<div id="forwp_seo_image_preview">
			<?php if ( is_string( $src ) && '' !== $src ) : ?>
				<img src="<?php echo esc_url( $src ); ?>" alt="" style="max-width:220px;height:auto" />
			<?php endif; ?>
		</div>
		<p>
			<button type="button" class="button forwp-faq-seo-image-select"><?php esc_html_e( 'Select image', '4wp-faq' ); ?></button>
			<button type="button" class="button forwp-faq-seo-image-remove"><?php esc_html_e( 'Remove', '4wp-faq' ); ?></button>
		</p>
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

		if ( isset( $_POST['forwp_seo_image'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$image_id = absint( wp_unslash( $_POST['forwp_seo_image'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $image_id > 0 ) {
				update_term_meta( $term_id, self::META_SEO_IMAGE, $image_id );
			} else {
				delete_term_meta( $term_id, self::META_SEO_IMAGE );
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
	 * Term for on-page title / description and document meta — pretty category URLs only.
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
	 * On-page title: custom display title, else term name.
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
	 * Canonical / og:url for the pretty category request.
	 *
	 * @return string
	 */
	public static function get_pretty_canonical_url() {
		$term = self::get_seo_term();
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}

		return Faq_Filter::get_category_url( $term->slug, true );
	}

	/**
	 * @param string        $canonical Current canonical.
	 * @param \WP_Post|null $post      Queried post.
	 * @return string
	 */
	public static function filter_canonical_url( $canonical, $post = null ) {
		unset( $post );

		return self::prefer_pretty_canonical( $canonical );
	}

	/**
	 * @param string $canonical Current canonical / og:url.
	 * @return string
	 */
	public static function filter_pretty_canonical( $canonical ) {
		return self::prefer_pretty_canonical( $canonical );
	}

	/**
	 * Replace the hub canonical with the pretty category URL, keeping scheme/host.
	 *
	 * @param string $canonical Current canonical.
	 * @return string
	 */
	private static function prefer_pretty_canonical( $canonical ) {
		$url = self::get_pretty_canonical_url();
		if ( '' === $url ) {
			return $canonical;
		}

		if ( is_string( $canonical ) && '' !== $canonical ) {
			$scheme = wp_parse_url( $canonical, PHP_URL_SCHEME );
			if ( is_string( $scheme ) && '' !== $scheme ) {
				$url = set_url_scheme( $url, $scheme );
			}
		}

		return $url;
	}

	/**
	 * Category SEO image URL, if set.
	 *
	 * @param \WP_Term $term Term.
	 * @return string
	 */
	public static function get_seo_image_url( $term ) {
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}

		$image_id = (int) get_term_meta( (int) $term->term_id, self::META_SEO_IMAGE, true );
		if ( $image_id <= 0 ) {
			$image_id = (int) get_term_meta( (int) $term->term_id, 'thumbnail_id', true );
		}

		if ( $image_id > 0 ) {
			$url = wp_get_attachment_image_url( $image_id, 'full' );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		if ( class_exists( '\WPSEO_Taxonomy_Meta' ) ) {
			$yoast = \WPSEO_Taxonomy_Meta::get_term_meta( $term, $term->taxonomy, 'opengraph-image' );
			if ( is_string( $yoast ) && '' !== trim( $yoast ) ) {
				return trim( $yoast );
			}
		}

		$rank = get_term_meta( (int) $term->term_id, 'rank_math_facebook_image', true );
		if ( is_string( $rank ) && '' !== trim( $rank ) ) {
			return trim( $rank );
		}

		return '';
	}

	/**
	 * Prefer the category image; keep the hub page image when the category has none.
	 *
	 * @param string $image Current og/twitter image.
	 * @return string
	 */
	public static function filter_opengraph_image( $image ) {
		$term = self::get_seo_term();
		if ( ! $term instanceof \WP_Term ) {
			return $image;
		}

		$url = self::get_seo_image_url( $term );

		return '' !== $url ? $url : $image;
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
	 * Native category Description (term field) for Excerpt / Term Description.
	 *
	 * @param \WP_Term $term Term.
	 * @return string
	 */
	public static function get_display_description( $term ) {
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}

		$raw = isset( $term->description ) ? (string) $term->description : '';

		return trim( $raw );
	}

	/**
	 * Swap Display title into core Title / Query Title / Term Name on pretty category URLs.
	 * Heading level (h1-h6 / p) stays whatever the template set.
	 *
	 * @param string               $content      Block HTML.
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @param \WP_Block|null       $block        Block instance.
	 * @return string
	 */
	public static function filter_title_block( $content, $parsed_block, $block = null ) {
		$term = self::get_seo_term();
		if ( ! $term instanceof \WP_Term ) {
			return $content;
		}

		$heading = self::get_display_title( $term );
		if ( '' === $heading ) {
			return $content;
		}

		$name = isset( $parsed_block['blockName'] ) ? (string) $parsed_block['blockName'] : '';
		if ( 'core/post-title' === $name && ! self::is_queried_post_context( $parsed_block, $block ) ) {
			return $content;
		}
		if ( 'core/term-name' === $name && ! self::is_current_or_unscoped_term_context( $parsed_block, $block, $term ) ) {
			return $content;
		}
		if ( 'core/query-title' === $name ) {
			$attrs = isset( $parsed_block['attrs'] ) && is_array( $parsed_block['attrs'] ) ? $parsed_block['attrs'] : [];
			$type  = isset( $attrs['type'] ) ? (string) $attrs['type'] : '';
			if ( '' !== $type && 'archive' !== $type ) {
				return $content;
			}
		}

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return self::render_title_fallback( $name, $parsed_block, $heading );
		}

		return self::replace_title_text( $content, $heading );
	}

	/**
	 * Swap category Description into core Excerpt / Term Description on pretty category URLs.
	 *
	 * @param string               $content      Block HTML.
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @param \WP_Block|null       $block        Block instance.
	 * @return string
	 */
	public static function filter_description_block( $content, $parsed_block, $block = null ) {
		$term = self::get_seo_term();
		if ( ! $term instanceof \WP_Term ) {
			return $content;
		}

		$desc = self::get_display_description( $term );
		if ( '' === $desc ) {
			return $content;
		}

		$name = isset( $parsed_block['blockName'] ) ? (string) $parsed_block['blockName'] : '';
		if ( 'core/post-excerpt' === $name ) {
			if ( ! self::is_queried_post_context( $parsed_block, $block ) ) {
				return $content;
			}
			if ( ! is_string( $content ) || '' === $content ) {
				return $content;
			}

			$plain   = esc_html( trim( wp_strip_all_tags( $desc ) ) );
			$updated = preg_replace(
				'/(<p class="[^"]*wp-block-post-excerpt__excerpt[^"]*">)(.*?)(<\/p>)/is',
				'$1' . $plain . '$3',
				$content,
				1
			);

			return is_string( $updated ) ? $updated : $content;
		}

		if ( 'core/term-description' === $name ) {
			if ( ! self::is_current_or_unscoped_term_context( $parsed_block, $block, $term ) ) {
				return $content;
			}

			$inner = wpautop( wp_kses_post( $desc ) );
			if ( ! is_string( $content ) || '' === trim( $content ) ) {
				return self::render_term_description_fallback( $parsed_block, $inner );
			}

			$updated = preg_replace(
				'/(<div\b[^>]*>)(.*?)(<\/div>)/is',
				'$1' . $inner . '$3',
				$content,
				1
			);

			return is_string( $updated ) ? $updated : $content;
		}

		return $content;
	}

	/**
	 * Title / Excerpt that belong to the current hub page, not items in a Query Loop.
	 *
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @param \WP_Block|null       $block        Block instance.
	 * @return bool
	 */
	private static function is_queried_post_context( $parsed_block, $block ) {
		$context = self::block_context( $parsed_block, $block );
		if ( ! isset( $context['postId'] ) ) {
			return false;
		}

		return (int) $context['postId'] === (int) get_queried_object_id();
	}

	/**
	 * Term Name / Term Description: unscoped (page template) or this FAQ category.
	 *
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @param \WP_Block|null       $block        Block instance.
	 * @param \WP_Term             $term         Active FAQ category.
	 * @return bool
	 */
	private static function is_current_or_unscoped_term_context( $parsed_block, $block, $term ) {
		$context = self::block_context( $parsed_block, $block );
		if ( ! isset( $context['termId'] ) ) {
			return true;
		}

		return (int) $context['termId'] === (int) $term->term_id;
	}

	/**
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @param \WP_Block|null       $block        Block instance.
	 * @return array<string, mixed>
	 */
	private static function block_context( $parsed_block, $block ) {
		if ( is_object( $block ) && isset( $block->context ) && is_array( $block->context ) ) {
			return $block->context;
		}

		if ( isset( $parsed_block['context'] ) && is_array( $parsed_block['context'] ) ) {
			return $parsed_block['context'];
		}

		return [];
	}

	/**
	 * @param string $html    Block HTML.
	 * @param string $heading Replacement text.
	 * @return string
	 */
	private static function replace_title_text( $html, $heading ) {
		$safe    = esc_html( $heading );
		$updated = preg_replace_callback(
			'/<((?:h[1-6]|p))\b[^>]*>.*?<\/\1>/is',
			static function ( $match ) use ( $safe ) {
				$tag_html = $match[0];
				if ( preg_match( '/<a\b[^>]*>.*?<\/a>/is', $tag_html ) ) {
					return preg_replace( '/(<a\b[^>]*>)(.*?)(<\/a>)/is', '$1' . $safe . '$3', $tag_html, 1 );
				}

				return preg_replace( '/(<(?:h[1-6]|p)\b[^>]*>)(.*?)(<\/(?:h[1-6]|p)>)/is', '$1' . $safe . '$3', $tag_html, 1 );
			},
			$html,
			1
		);

		return is_string( $updated ) ? $updated : $html;
	}

	/**
	 * When Query Title / Term Name render empty on a page, still output the category title.
	 *
	 * @param string               $name         Block name.
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @param string               $heading      Display title.
	 * @return string
	 */
	private static function render_title_fallback( $name, $parsed_block, $heading ) {
		$attrs = isset( $parsed_block['attrs'] ) && is_array( $parsed_block['attrs'] ) ? $parsed_block['attrs'] : [];
		$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : ( 'core/term-name' === $name ? 0 : 1 );
		$tag   = 0 === $level ? 'p' : 'h' . $level;
		if ( ! preg_match( '/^(?:h[1-6]|p)$/', $tag ) ) {
			$tag = 'h1';
		}

		$classes = [];
		if ( ! empty( $attrs['textAlign'] ) ) {
			$classes[] = 'has-text-align-' . sanitize_html_class( (string) $attrs['textAlign'] );
		}

		$class_map = [
			'core/post-title'  => 'wp-block-post-title',
			'core/query-title' => 'wp-block-query-title',
			'core/term-name'   => 'wp-block-term-name',
		];
		if ( isset( $class_map[ $name ] ) ) {
			$classes[] = $class_map[ $name ];
		}

		$class_attr = implode( ' ', array_filter( $classes ) );
		$wrapper    = function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes( [ 'class' => $class_attr ] )
			: 'class="' . esc_attr( $class_attr ) . '"';

		return sprintf(
			'<%1$s %2$s>%3$s</%1$s>',
			$tag,
			$wrapper,
			esc_html( $heading )
		);
	}

	/**
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @param string               $inner        KSesed description HTML.
	 * @return string
	 */
	private static function render_term_description_fallback( $parsed_block, $inner ) {
		$attrs   = isset( $parsed_block['attrs'] ) && is_array( $parsed_block['attrs'] ) ? $parsed_block['attrs'] : [];
		$classes = [];
		if ( ! empty( $attrs['textAlign'] ) ) {
			$classes[] = 'has-text-align-' . sanitize_html_class( (string) $attrs['textAlign'] );
		}

		$wrapper = function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes( [ 'class' => implode( ' ', $classes ) ] )
			: 'class="' . esc_attr( implode( ' ', array_merge( [ 'wp-block-term-description' ], $classes ) ) ) . '"';

		return '<div ' . $wrapper . '>' . $inner . '</div>';
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
