<?php
/**
 * FSE blocks: 4WP FAQ List, Card, and Categories.
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register and render registry display blocks.
 */
class Display_Blocks {
	public const LIST_BLOCK       = 'forwp/faq-list';
	public const CARD_BLOCK       = 'forwp/faq-card';
	public const CATEGORIES_BLOCK = 'forwp/faq-categories';
	public const COUNT_BLOCK      = 'forwp/faq-count';
	public const STORE            = 'forwp/faq';

	public const LIST_SCRIPT       = 'forwp-faq-list-editor';
	public const CARD_SCRIPT       = 'forwp-faq-card-editor';
	public const CATEGORIES_SCRIPT = 'forwp-faq-categories-editor';
	public const COUNT_SCRIPT      = 'forwp-faq-count-editor';
	public const VIEW_SCRIPT       = 'forwp-faq-view';
	public const LIST_STYLE        = 'forwp-faq-list';
	public const CARD_STYLE        = 'forwp-faq-card';
	public const CATEGORIES_STYLE  = 'forwp-faq-categories';

	/**
	 * Hook registration.
	 */
	public static function init() {
		Faq_Filter::init();
		add_action( 'init', [ __CLASS__, 'register_blocks' ] );
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'localize_editor_scripts' ], 20 );
		add_filter( 'render_block_core/search', [ __CLASS__, 'render_related_search' ], 10, 2 );
		add_shortcode( 'forwp_faq_count', [ __CLASS__, 'shortcode_count' ] );
	}

	/**
	 * Register block types and assets.
	 */
	public static function register_blocks() {
		self::register_assets();

		$blocks = [
			'faq-list'       => [
				'script' => self::LIST_SCRIPT,
				'style'  => self::LIST_STYLE,
				'render' => [ __CLASS__, 'render_list' ],
			],
			'faq-card'       => [
				'script' => self::CARD_SCRIPT,
				'style'  => self::CARD_STYLE,
				'render' => [ __CLASS__, 'render_card' ],
			],
			'faq-categories' => [
				'script' => self::CATEGORIES_SCRIPT,
				'style'  => self::CATEGORIES_STYLE,
				'render' => [ __CLASS__, 'render_categories' ],
			],
			'faq-count'      => [
				'script' => self::COUNT_SCRIPT,
				'style'  => self::LIST_STYLE,
				'render' => [ __CLASS__, 'render_count' ],
			],
		];

		foreach ( $blocks as $folder => $config ) {
			$path = FORWP_FAQ_PLUGIN_DIR . 'blocks/' . $folder;
			if ( ! is_readable( $path . '/block.json' ) ) {
				continue;
			}

			register_block_type(
				$path,
				[
					'editor_script'   => $config['script'],
					'style'           => $config['style'],
					'editor_style'    => $config['style'],
					'render_callback' => $config['render'],
				]
			);
		}
	}

	/**
	 * Register built editor scripts, view script, and front-end styles.
	 */
	private static function register_assets() {
		$scripts = [
			self::LIST_SCRIPT       => 'faq-list',
			self::CARD_SCRIPT       => 'faq-card',
			self::CATEGORIES_SCRIPT => 'faq-categories',
			self::COUNT_SCRIPT      => 'faq-count',
		];

		foreach ( $scripts as $handle => $file ) {
			$asset_file = FORWP_FAQ_PLUGIN_DIR . 'build/' . $file . '.asset.php';
			if ( ! is_readable( $asset_file ) ) {
				continue;
			}

			$asset = include $asset_file;
			wp_register_script(
				$handle,
				FORWP_FAQ_PLUGIN_URL . 'build/' . $file . '.js',
				isset( $asset['dependencies'] ) ? $asset['dependencies'] : [],
				isset( $asset['version'] ) ? $asset['version'] : FORWP_FAQ_VERSION,
				true
			);
		}

		if ( function_exists( 'wp_register_script_module' ) ) {
			$view_file = FORWP_FAQ_PLUGIN_DIR . 'assets/faq-view.js';
			if ( is_readable( $view_file ) ) {
				wp_register_script_module(
					self::VIEW_SCRIPT,
					FORWP_FAQ_PLUGIN_URL . 'assets/faq-view.js',
					[
						[
							'id'     => '@wordpress/interactivity',
							'import' => 'static',
						],
					],
					(string) filemtime( $view_file )
				);
			}
		}

		$card_style        = FORWP_FAQ_PLUGIN_DIR . 'build/style-faq-card.css';
		$list_style        = FORWP_FAQ_PLUGIN_DIR . 'build/style-faq-list.css';
		$categories_style  = FORWP_FAQ_PLUGIN_DIR . 'build/style-faq-categories.css';

		if ( is_readable( $card_style ) ) {
			wp_register_style(
				self::CARD_STYLE,
				FORWP_FAQ_PLUGIN_URL . 'build/style-faq-card.css',
				[],
				(string) filemtime( $card_style )
			);
		}

		if ( is_readable( $list_style ) ) {
			wp_register_style(
				self::LIST_STYLE,
				FORWP_FAQ_PLUGIN_URL . 'build/style-faq-list.css',
				is_readable( $card_style ) ? [ self::CARD_STYLE ] : [],
				(string) filemtime( $list_style )
			);
		}

		if ( is_readable( $categories_style ) ) {
			wp_register_style(
				self::CATEGORIES_STYLE,
				FORWP_FAQ_PLUGIN_URL . 'build/style-faq-categories.css',
				[],
				(string) filemtime( $categories_style )
			);
		}
	}

	/**
	 * Shared editor config for list/card/categories inspectors.
	 */
	public static function localize_editor_scripts() {
		$config = [
			'registrySetupComplete' => Settings::is_setup_complete(),
			'postType'              => Settings::get_post_type(),
			'taxonomy'              => Settings::get_taxonomy(),
			'categoriesPath'        => '/forwp-faq/v1/editor/categories',
			'seoUrlsEnabled'        => Settings::is_seo_urls_enabled(),
		];

		foreach ( [ self::LIST_SCRIPT, self::CARD_SCRIPT, self::CATEGORIES_SCRIPT, self::COUNT_SCRIPT ] as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_localize_script( $handle, 'forwpFaqDisplay', $config );
			}
		}
	}

	/**
	 * Bind a core Search block to the FAQ Interactivity store when opted in.
	 *
	 * @param string               $content    Block HTML.
	 * @param array<string, mixed> $block      Parsed block.
	 * @return string
	 */
	public static function render_related_search( $content, $block ) {
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
		if ( empty( $attrs['forwpFaqFilter'] ) || ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		if ( ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $content;
		}

		self::ensure_runtime();

		$processor = new \WP_HTML_Tag_Processor( $content );
		if ( $processor->next_tag( 'form' ) ) {
			$processor->set_attribute( 'data-wp-interactive', self::STORE );
			$processor->set_attribute( 'data-wp-on--submit', 'actions.preventSearchSubmit' );
		}

		$processor = new \WP_HTML_Tag_Processor( $processor->get_updated_html() );
		if ( $processor->next_tag( 'input' ) ) {
			$processor->set_attribute( 'data-wp-interactive', self::STORE );
			$processor->set_attribute( 'data-wp-on--input', 'actions.setSearch' );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Render 4WP FAQ List.
	 *
	 * @param array     $attributes Block attributes.
	 * @param string    $content    Inner content.
	 * @param \WP_Block $block      Block instance.
	 * @return string
	 */
	public static function render_list( $attributes, $content, $block ) {
		unset( $content );

		$layout           = isset( $attributes['layout'] ) ? sanitize_key( (string) $attributes['layout'] ) : 'grouped';
		$filters          = self::resolve_shared_term_filters(
			$attributes['includeTermIds'] ?? [],
			$attributes['excludeTermIds'] ?? []
		);
		$include_term_ids = $filters['includeTermIds'];
		$exclude_term_ids = $filters['excludeTermIds'];
		$card_attrs       = self::resolve_card_attributes( $attributes, $block );
		$active           = Faq_Filter::get_active_slug();

		if ( 'flat' !== $layout ) {
			$layout = 'grouped';
		}

		self::ensure_runtime();

		$wrapper_attrs = [
			'class'               => 'forwp-faq-list is-layout-' . $layout,
			'data-wp-interactive' => self::STORE,
		];

		$typo_style = self::typography_style_attr( $card_attrs );
		if ( '' !== $typo_style ) {
			$wrapper_attrs['style'] = $typo_style;
		}

		$wrapper = get_block_wrapper_attributes( $wrapper_attrs );

		if ( ! Settings::is_setup_complete() ) {
			return sprintf(
				'<div %s><p class="forwp-faq-list__empty">%s</p></div>',
				$wrapper,
				esc_html__( 'Complete 4WP FAQ registry setup to display this list.', '4wp-faq' )
			);
		}

		$html = '<div ' . $wrapper . '>';

		$preview_limit = '' === $active ? Settings::get_preview_per_category() : 0;
		$seo_urls      = self::document_uses_seo_urls();

		if ( 'flat' === $layout ) {
			$posts = Registry_Content::get_visible_posts( $include_term_ids, $exclude_term_ids, $active, $preview_limit, 'flat' );
			$html .= self::render_items( $posts, $card_attrs );
		} else {
			$groups = Registry_Content::get_visible_group_tree( $include_term_ids, $exclude_term_ids, $active, $preview_limit );
			if ( empty( $groups ) ) {
				$html .= self::render_empty();
			} else {
				$html .= self::render_group_tree( $groups, $active, $seo_urls, $card_attrs );
			}
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render 4WP FAQ Categories (sidebar / navigation).
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_categories( $attributes ) {
		$orientation      = isset( $attributes['orientation'] ) ? sanitize_key( (string) $attributes['orientation'] ) : 'vertical';
		$show_all         = ! isset( $attributes['showAll'] ) || ! empty( $attributes['showAll'] );
		$show_count       = ! isset( $attributes['showCount'] ) || ! empty( $attributes['showCount'] );
		$seo_urls         = Settings::is_seo_urls_enabled() && ! empty( $attributes['seoUrls'] );
		$filters          = self::resolve_shared_term_filters(
			$attributes['includeTermIds'] ?? [],
			$attributes['excludeTermIds'] ?? []
		);
		$include_term_ids = $filters['includeTermIds'];
		$exclude_term_ids = $filters['excludeTermIds'];
		$all_label        = isset( $attributes['allLabel'] ) && is_string( $attributes['allLabel'] ) && '' !== $attributes['allLabel']
			? $attributes['allLabel']
			: __( 'All categories', '4wp-faq' );
		$nav_label        = isset( $attributes['label'] ) && is_string( $attributes['label'] ) && '' !== $attributes['label']
			? $attributes['label']
			: __( 'Categories', '4wp-faq' );

		if ( 'horizontal' !== $orientation ) {
			$orientation = 'vertical';
		}

		self::ensure_runtime();

		$wrapper = get_block_wrapper_attributes(
			[
				'class'               => 'forwp-faq-categories is-orientation-' . $orientation,
				'data-wp-interactive' => self::STORE,
			]
		);

		if ( ! Settings::is_setup_complete() ) {
			return sprintf(
				'<nav %s><p class="forwp-faq-categories__empty">%s</p></nav>',
				$wrapper,
				esc_html__( 'Complete 4WP FAQ registry setup to display categories.', '4wp-faq' )
			);
		}

		$terms  = Registry_Content::get_nav_tree( $include_term_ids, $exclude_term_ids );
		$active = Faq_Filter::get_active_slug();

		$html  = '<nav ' . $wrapper . ' aria-label="' . esc_attr( $nav_label ) . '">';
		$html .= '<p class="forwp-faq-categories__label">' . esc_html( $nav_label ) . '</p>';
		$html .= '<ul class="forwp-faq-categories__list">';

		if ( $show_all ) {
			$all_url = Faq_Filter::get_nav_url( '', $seo_urls );
			$html   .= self::render_category_item( '', $all_label, $all_url, $seo_urls, $active, null );
		}

		$html .= self::render_category_nodes( $terms, $seo_urls, $active, $show_count );

		$html .= '</ul>';

		if ( empty( $terms ) && ! $show_all ) {
			$html .= '<p class="forwp-faq-categories__empty">' . esc_html__( 'No FAQ categories yet.', '4wp-faq' ) . '</p>';
		}

		$html .= '</nav>';

		return $html;
	}

	/**
	 * Render 4WP FAQ Count (live number for All / category URL / search).
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_count( $attributes ) {
		unset( $attributes );

		self::ensure_runtime();

		$context = self::get_list_query_context();
		$count   = Registry_Content::count_visible_posts(
			$context['includeTermIds'],
			$context['excludeTermIds'],
			$context['activeSlug'],
			$context['previewLimit'],
			$context['layout']
		);

		$wrapper = get_block_wrapper_attributes(
			[
				'class'               => 'forwp-faq-count',
				'data-wp-interactive' => self::STORE,
				'data-wp-text'        => 'state.visibleCount',
			]
		);

		return '<span ' . $wrapper . '>' . esc_html( (string) $count ) . '</span>';
	}

	/**
	 * Shortcode [forwp_faq_count] — same live number as 4WP FAQ Count.
	 *
	 * @return string
	 */
	public static function shortcode_count() {
		return self::render_count( [] );
	}

	/**
	 * Include/exclude, layout, and preview settings for the current document.
	 *
	 * @return array{includeTermIds: int[], excludeTermIds: int[], layout: string, seoUrls: bool, activeSlug: string, previewLimit: int}
	 */
	public static function get_list_query_context() {
		$filters = self::resolve_shared_term_filters( [], [] );
		$layout  = 'grouped';
		$seo     = false;

		foreach ( self::get_document_contents() as $content ) {
			self::inspect_display_blocks( parse_blocks( $content ), $layout, $seo );
		}

		$active = Faq_Filter::get_active_slug();

		return [
			'includeTermIds' => $filters['includeTermIds'],
			'excludeTermIds' => $filters['excludeTermIds'],
			'layout'         => $layout,
			'seoUrls'        => Settings::is_seo_urls_enabled() && $seo,
			'activeSlug'     => $active,
			'previewLimit'   => '' === $active ? Settings::get_preview_per_category() : 0,
		];
	}

	/**
	 * Whether the current document contains a block.
	 *
	 * @param string $block_name Block name.
	 * @return bool
	 */
	public static function document_has_block( $block_name ) {
		foreach ( self::get_document_contents() as $content ) {
			if ( has_block( $block_name, $content ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether 4WP FAQ Categories has SEO URLs enabled in this document.
	 *
	 * @return bool
	 */
	private static function document_uses_seo_urls() {
		return ! empty( self::get_list_query_context()['seoUrls'] );
	}

	/**
	 * @param array[] $blocks Parsed blocks.
	 * @param string  $layout Layout found.
	 * @param bool    $seo    SEO URLs found.
	 */
	private static function inspect_display_blocks( $blocks, &$layout, &$seo ) {
		foreach ( $blocks as $block ) {
			$name  = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];

			if ( self::LIST_BLOCK === $name && ! empty( $attrs['layout'] ) ) {
				$layout = sanitize_key( (string) $attrs['layout'] );
			}

			if ( self::CATEGORIES_BLOCK === $name && ! empty( $attrs['seoUrls'] ) ) {
				$seo = true;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::inspect_display_blocks( $block['innerBlocks'], $layout, $seo );
			}
		}
	}

	/**
	 * Union include/exclude from 4WP FAQ List and 4WP FAQ Categories in the same document.
	 *
	 * Empty include on one block inherits the sibling. Non-empty includes are unioned so the
	 * nav never lists a category that was not queried, and the list includes every nav category.
	 * Skipped in the editor REST renderer so live inspector attributes stay authoritative.
	 *
	 * @param mixed $own_include Own include term IDs.
	 * @param mixed $own_exclude Own exclude term IDs.
	 * @return array{includeTermIds: int[], excludeTermIds: int[]}
	 */
	private static function resolve_shared_term_filters( $own_include, $own_exclude ) {
		$own = [
			'includeTermIds' => Registry_Content::sanitize_term_ids( $own_include ),
			'excludeTermIds' => Registry_Content::sanitize_term_ids( $own_exclude ),
		];

		if ( ! self::should_merge_document_filters() ) {
			return $own;
		}

		static $cache = null;

		if ( null === $cache ) {
			$include_sets = [];
			$exclude_ids  = [];
			$found        = false;

			foreach ( self::get_document_contents() as $content ) {
				$before = count( $include_sets );
				self::collect_filters_from_blocks( parse_blocks( $content ), $include_sets, $exclude_ids );
				if ( count( $include_sets ) > $before ) {
					$found = true;
				}
			}

			if ( ! $found ) {
				$cache = false;
			} else {
				$union = [];
				foreach ( $include_sets as $set ) {
					if ( empty( $set ) ) {
						continue;
					}
					foreach ( $set as $id ) {
						$union[] = (int) $id;
					}
				}

				$cache = [
					'includeTermIds' => array_values( array_unique( $union ) ),
					'excludeTermIds' => array_values( array_unique( $exclude_ids ) ),
				];
			}
		}

		return false === $cache ? $own : $cache;
	}

	/**
	 * Editor block-renderer REST requests send a single block; do not overlay saved siblings.
	 *
	 * @return bool
	 */
	private static function should_merge_document_filters() {
		if ( is_admin() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		return true;
	}

	/**
	 * Post content plus the current FSE template (list and nav often live in different columns).
	 *
	 * @return string[]
	 */
	private static function get_document_contents() {
		$contents = [];
		$post     = get_post();

		if ( $post instanceof \WP_Post && is_string( $post->post_content ) && '' !== $post->post_content ) {
			$contents[] = $post->post_content;
		}

		global $_wp_current_template_content;
		if ( is_string( $_wp_current_template_content ) && '' !== $_wp_current_template_content ) {
			$contents[] = $_wp_current_template_content;
		}

		return $contents;
	}

	/**
	 * @param array[] $blocks       Parsed blocks.
	 * @param int[][] $include_sets Collected include arrays (empty = all).
	 * @param int[]   $exclude_ids  Collected exclude IDs.
	 */
	private static function collect_filters_from_blocks( $blocks, array &$include_sets, array &$exclude_ids ) {
		foreach ( $blocks as $block ) {
			$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			if ( self::LIST_BLOCK === $name || self::CATEGORIES_BLOCK === $name ) {
				$attrs           = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
				$include_sets[] = Registry_Content::sanitize_term_ids( $attrs['includeTermIds'] ?? [] );
				foreach ( Registry_Content::sanitize_term_ids( $attrs['excludeTermIds'] ?? [] ) as $id ) {
					$exclude_ids[] = $id;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::collect_filters_from_blocks( $block['innerBlocks'], $include_sets, $exclude_ids );
			}
		}
	}

	/**
	 * Render FAQ Card from Query Loop / list context.
	 *
	 * @param array     $attributes Block attributes.
	 * @param string    $content    Inner content.
	 * @param \WP_Block $block      Block instance.
	 * @return string
	 */
	public static function render_card( $attributes, $content, $block ) {
		unset( $content );

		$post_id = 0;
		if ( isset( $block->context['postId'] ) ) {
			$post_id = (int) $block->context['postId'];
		}
		if ( $post_id <= 0 ) {
			$post_id = (int) get_the_ID();
		}

		return self::render_card_for_post( $post_id, $attributes );
	}

	/**
	 * Card attributes from the inner FAQ Card, with list-level fallback (editor SSR).
	 *
	 * @param array          $attributes List attributes.
	 * @param \WP_Block|null $block      List block instance.
	 * @return array{displayMode: string, showSources: bool, sourcesLabel: string, showPostType: bool, questionStyle: array, answerStyle: array}
	 */
	private static function resolve_card_attributes( $attributes, $block ) {
		$inner = [];
		if ( $block instanceof \WP_Block && ! empty( $block->parsed_block['innerBlocks'][0]['attrs'] ) ) {
			$inner = $block->parsed_block['innerBlocks'][0]['attrs'];
		}

		$display = isset( $inner['displayMode'] ) ? sanitize_key( (string) $inner['displayMode'] ) : '';
		if ( '' === $display && isset( $attributes['cardDisplayMode'] ) ) {
			$display = sanitize_key( (string) $attributes['cardDisplayMode'] );
		}
		if ( 'heading' !== $display ) {
			$display = 'accordion';
		}

		if ( array_key_exists( 'showSources', $inner ) ) {
			$show_sources = ! empty( $inner['showSources'] );
		} else {
			$show_sources = ! empty( $attributes['cardShowSources'] );
		}

		if ( array_key_exists( 'sourcesLabel', $inner ) ) {
			$sources_label = sanitize_text_field( (string) $inner['sourcesLabel'] );
		} elseif ( isset( $attributes['cardSourcesLabel'] ) ) {
			$sources_label = sanitize_text_field( (string) $attributes['cardSourcesLabel'] );
		} else {
			$sources_label = __( 'Used in', '4wp-faq' );
		}

		if ( array_key_exists( 'showPostType', $inner ) ) {
			$show_post_type = ! empty( $inner['showPostType'] );
		} else {
			$show_post_type = ! empty( $attributes['cardShowPostType'] );
		}

		$question_style = [];
		if ( isset( $inner['questionStyle'] ) && is_array( $inner['questionStyle'] ) ) {
			$question_style = $inner['questionStyle'];
		} elseif ( isset( $attributes['cardQuestionStyle'] ) && is_array( $attributes['cardQuestionStyle'] ) ) {
			$question_style = $attributes['cardQuestionStyle'];
		}

		$answer_style = [];
		if ( isset( $inner['answerStyle'] ) && is_array( $inner['answerStyle'] ) ) {
			$answer_style = $inner['answerStyle'];
		} elseif ( isset( $attributes['cardAnswerStyle'] ) && is_array( $attributes['cardAnswerStyle'] ) ) {
			$answer_style = $attributes['cardAnswerStyle'];
		}

		return [
			'displayMode'   => $display,
			'showSources'   => $show_sources,
			'sourcesLabel'  => $sources_label,
			'showPostType'  => $show_post_type,
			'questionStyle' => self::sanitize_typography_style( $question_style ),
			'answerStyle'   => self::sanitize_typography_style( $answer_style ),
		];
	}

	/**
	 * @param mixed $style Raw style object.
	 * @return array{fontSize?: string, fontFamily?: string, fontWeight?: string, color?: string}
	 */
	private static function sanitize_typography_style( $style ) {
		if ( ! is_array( $style ) ) {
			return [];
		}

		$out = [];

		if ( ! empty( $style['fontSize'] ) ) {
			$size = self::sanitize_css_size( $style['fontSize'] );
			if ( '' !== $size ) {
				$out['fontSize'] = $size;
			}
		}

		if ( ! empty( $style['fontFamily'] ) ) {
			$family = self::sanitize_css_font_family( $style['fontFamily'] );
			if ( '' !== $family ) {
				$out['fontFamily'] = $family;
			}
		}

		if ( ! empty( $style['fontWeight'] ) ) {
			$weight = sanitize_text_field( (string) $style['fontWeight'] );
			if ( preg_match( '/^(normal|bold|[1-9]00)$/', $weight ) ) {
				$out['fontWeight'] = $weight;
			}
		}

		if ( ! empty( $style['color'] ) ) {
			$color = self::sanitize_css_color( $style['color'] );
			if ( '' !== $color ) {
				$out['color'] = $color;
			}
		}

		return $out;
	}

	/**
	 * Allow hex, rgb/rgba/hsl, and CSS variables (incl. Gutenberg preset refs).
	 *
	 * @param mixed $value Raw color.
	 * @return string
	 */
	private static function sanitize_css_color( $value ) {
		$raw = trim( (string) $value );
		if ( '' === $raw ) {
			return '';
		}

		// Gutenberg attribute form: var:preset|color|slug → CSS variable.
		if ( preg_match( '/^var:preset\|color\|([a-z0-9\-]+)$/i', $raw, $m ) ) {
			return 'var(--wp--preset--color--' . strtolower( $m[1] ) . ')';
		}

		$hex = sanitize_hex_color( $raw );
		if ( $hex ) {
			return $hex;
		}

		if ( preg_match( '/^var\(--wp--preset--color--[a-z0-9\-]+\)$/i', $raw ) ) {
			return $raw;
		}

		if ( preg_match( '/^var\(--[a-zA-Z0-9_\-]+\)$/', $raw ) ) {
			return $raw;
		}

		if ( preg_match( '/^rgba?\(\s*[\d.%\s,]+\s*(?:\/\s*[\d.]+\s*)?\)$/i', $raw ) ) {
			return $raw;
		}

		if ( preg_match( '/^hsla?\(\s*[\d.%\s,\/]+\s*\)$/i', $raw ) ) {
			return $raw;
		}

		return '';
	}

	/**
	 * @param mixed $value Raw size.
	 * @return string
	 */
	private static function sanitize_css_size( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( is_numeric( $value ) ) {
			return ( (float) $value ) . 'px';
		}

		if ( preg_match( '/^(\d+(\.\d+)?)(px|rem|em|%)$/', $value ) ) {
			return $value;
		}

		if ( preg_match( '/^var\(--wp--preset--font-size--[a-z0-9\-]+\)$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * @param mixed $value Raw font-family.
	 * @return string
	 */
	private static function sanitize_css_font_family( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^var\(--wp--preset--font-family--[a-z0-9\-]+\)$/', $value ) ) {
			return $value;
		}

		// Allow simple family stacks: letters, spaces, commas, quotes, hyphens.
		if ( preg_match( '/^[a-zA-Z0-9\s,\-\'"\.]+$/', $value ) && strlen( $value ) <= 120 ) {
			return $value;
		}

		return '';
	}

	/**
	 * Build inline CSS custom properties for question/answer typography.
	 *
	 * @param array<string, mixed> $card_attrs Card attributes.
	 * @return string
	 */
	private static function typography_style_attr( $card_attrs ) {
		$question = isset( $card_attrs['questionStyle'] ) && is_array( $card_attrs['questionStyle'] )
			? $card_attrs['questionStyle']
			: [];
		$answer = isset( $card_attrs['answerStyle'] ) && is_array( $card_attrs['answerStyle'] )
			? $card_attrs['answerStyle']
			: [];

		$map = [
			'--forwp-faq-q-size'   => $question['fontSize'] ?? '',
			'--forwp-faq-q-family' => $question['fontFamily'] ?? '',
			'--forwp-faq-q-weight' => $question['fontWeight'] ?? '',
			'--forwp-faq-q-color'  => $question['color'] ?? '',
			'--forwp-faq-a-size'   => $answer['fontSize'] ?? '',
			'--forwp-faq-a-family' => $answer['fontFamily'] ?? '',
			'--forwp-faq-a-weight' => $answer['fontWeight'] ?? '',
			'--forwp-faq-a-color'  => $answer['color'] ?? '',
		];

		$parts = [];
		foreach ( $map as $prop => $val ) {
			$val = trim( (string) $val );
			if ( '' === $val ) {
				continue;
			}
			$parts[] = $prop . ':' . $val;
		}

		return implode( ';', $parts );
	}

	/**
	 * Nested category groups (parent wraps children).
	 *
	 * @param array                $groups     Nested groups.
	 * @param string               $active     Active slug.
	 * @param bool                 $seo_urls   Pretty permalinks.
	 * @param array<string, mixed> $card_attrs Card attributes.
	 * @param string[]             $ancestors  Ancestor slugs.
	 * @return string
	 */
	private static function render_group_tree( $groups, $active, $seo_urls, $card_attrs, $ancestors = [] ) {
		$html = '';

		foreach ( $groups as $group ) {
			$posts    = isset( $group['posts'] ) && is_array( $group['posts'] ) ? $group['posts'] : [];
			$children = isset( $group['children'] ) && is_array( $group['children'] ) ? $group['children'] : [];
			if ( empty( $posts ) && empty( $children ) ) {
				continue;
			}

			$term  = $group['term'] ?? null;
			$slug  = $term instanceof \WP_Term ? $term->slug : '';
			$title = $term instanceof \WP_Term
				? Faq_Terms::get_display_title( $term )
				: __( 'Uncategorized', '4wp-faq' );

			$hidden      = ( '' !== $active && $slug !== $active && ! in_array( $active, $ancestors, true ) );
			$is_child    = ! empty( $ancestors );
			$truncated   = ! empty( $group['truncated'] );
			$group_class = 'forwp-faq-list__group' . ( $is_child ? ' is-child' : '' );
			$branch      = $ancestors;
			if ( '' !== $slug ) {
				$branch[] = $slug;
			}

			$html .= '<section class="' . esc_attr( $group_class ) . '"' . self::context_attr(
				[
					'slug'      => $slug,
					'ancestors' => implode( ' ', $ancestors ),
				]
			) . ' data-wp-bind--hidden="!state.isGroupVisible"' . ( $hidden ? ' hidden' : '' ) . '>';
			$html .= '<h2 class="forwp-faq-list__group-title">' . esc_html( $title ) . '</h2>';
			if ( ! empty( $posts ) ) {
				$html .= self::render_items( $posts, $card_attrs );
				if ( $truncated && $term instanceof \WP_Term ) {
					$html .= self::render_see_all( $term, $seo_urls, (int) ( $group['total'] ?? 0 ) );
				}
			}
			if ( ! empty( $children ) ) {
				$html .= '<div class="forwp-faq-list__children">';
				$html .= self::render_group_tree( $children, $active, $seo_urls, $card_attrs, $branch );
				$html .= '</div>';
			}
			$html .= '</section>';
		}

		return $html;
	}

	/**
	 * @param \WP_Post[]           $posts      Registry posts.
	 * @param array<string, mixed> $card_attrs Card attributes.
	 * @return string
	 */
	private static function render_items( $posts, $card_attrs ) {
		if ( empty( $posts ) ) {
			return self::render_empty();
		}

		$html = '<div class="forwp-faq-list__items">';
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$html .= self::render_card_for_post( (int) $post->ID, $card_attrs );
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * @return string
	 */
	private static function render_empty() {
		return '<p class="forwp-faq-list__empty">' . esc_html__( 'No FAQ entries found.', '4wp-faq' ) . '</p>';
	}

	/**
	 * Markup for one registry FAQ card.
	 *
	 * @param int                  $post_id    Registry post ID.
	 * @param array<string, mixed> $attributes Card attributes.
	 * @return string
	 */
	public static function render_card_for_post( $post_id, $attributes ) {
		$post_id = (int) $post_id;
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		if ( Settings::is_setup_complete() && $post->post_type !== Settings::get_post_type() ) {
			return '';
		}

		$display      = isset( $attributes['displayMode'] ) ? sanitize_key( (string) $attributes['displayMode'] ) : 'accordion';
		$show_sources = ! empty( $attributes['showSources'] );
		$show_type    = ! empty( $attributes['showPostType'] );
		$sources_label = isset( $attributes['sourcesLabel'] )
			? sanitize_text_field( (string) $attributes['sourcesLabel'] )
			: __( 'Used in', '4wp-faq' );
		if ( 'heading' !== $display ) {
			$display = 'accordion';
		}

		$question   = get_the_title( $post );
		$answer     = Registry_Content::get_answer( $post_id );
		$sources    = $show_sources ? Registry_Content::get_sources( $post_id ) : [];
		$panel_id   = 'forwp-faq-card-panel-' . $post_id;
		$term_slugs = Registry_Content::get_post_term_slugs_with_ancestors( $post_id );
		$search     = strtolower( trim( $question . ' ' . ( $answer['text'] ?? '' ) ) );
		$active     = Faq_Filter::get_active_slug();
		$hidden     = ( '' !== $active && ! in_array( $active, $term_slugs, true ) );

		$extra   = isset( $attributes['className'] ) ? trim( (string) $attributes['className'] ) : '';
		$classes = trim( 'wp-block-forwp-faq-card forwp-faq-card is-display-' . $display . ' ' . $extra );

		$style_attr = '';
		$typo       = self::typography_style_attr(
			[
				'questionStyle' => self::sanitize_typography_style( $attributes['questionStyle'] ?? [] ),
				'answerStyle'   => self::sanitize_typography_style( $attributes['answerStyle'] ?? [] ),
			]
		);
		if ( '' !== $typo ) {
			$style_attr = ' style="' . esc_attr( $typo ) . '"';
		}

		$context = self::context_attr(
			[
				'cats'       => implode( ' ', $term_slugs ),
				'searchText' => $search,
			]
		);

		$hidden_attr = ' data-wp-bind--hidden="!state.isItemVisible"' . ( $hidden ? ' hidden' : '' );

		$body  = self::render_answer( $answer );
		$body .= self::render_sources( $sources, $sources_label, $show_type );

		if ( 'heading' === $display ) {
			return sprintf(
				'<article class="%1$s"%2$s%3$s%4$s><h3 class="forwp-faq-card__question">%5$s</h3><div class="forwp-faq-card__body">%6$s</div></article>',
				esc_attr( $classes ),
				$context,
				$hidden_attr,
				$style_attr,
				esc_html( $question ),
				$body
			);
		}

		return sprintf(
			'<article class="%1$s"%2$s%3$s%4$s><details class="forwp-faq-card__details"><summary class="forwp-faq-card__question" aria-controls="%5$s">%6$s</summary><div id="%5$s" class="forwp-faq-card__body">%7$s</div></details></article>',
			esc_attr( $classes ),
			$context,
			$hidden_attr,
			$style_attr,
			esc_attr( $panel_id ),
			esc_html( $question ),
			$body
		);
	}

	/**
	 * @param array{text: string, html: string} $answer Answer payload.
	 * @return string
	 */
	private static function render_answer( $answer ) {
		$html = isset( $answer['html'] ) ? trim( (string) $answer['html'] ) : '';
		$text = isset( $answer['text'] ) ? trim( (string) $answer['text'] ) : '';

		if ( '' !== $html ) {
			return '<div class="forwp-faq-card__answer">' . wp_kses_post( $html ) . '</div>';
		}

		if ( '' !== $text ) {
			return '<p class="forwp-faq-card__answer">' . esc_html( $text ) . '</p>';
		}

		return '';
	}

	/**
	 * @param list<array{title: string, url: string, post_type_label: string}> $sources        Sources.
	 * @param string                                                           $sources_label  Label placeholder.
	 * @param bool                                                             $show_post_type Show CPT type.
	 * @return string
	 */
	private static function render_sources( $sources, $sources_label = '', $show_post_type = false ) {
		if ( empty( $sources ) ) {
			return '';
		}

		$html = '<div class="forwp-faq-card__sources">';
		if ( '' !== $sources_label ) {
			$html .= '<p class="forwp-faq-card__sources-label">' . esc_html( $sources_label ) . '</p>';
		}
		$html .= '<ul class="forwp-faq-card__sources-list">';

		foreach ( $sources as $source ) {
			$html .= '<li class="forwp-faq-card__sources-item">';
			$html .= '<a href="' . esc_url( $source['url'] ) . '">' . esc_html( $source['title'] ) . '</a>';
			if ( $show_post_type && ! empty( $source['post_type_label'] ) ) {
				$html .= ' <span class="forwp-faq-card__sources-type">' . esc_html( $source['post_type_label'] ) . '</span>';
			}
			$html .= '</li>';
		}

		$html .= '</ul></div>';

		return $html;
	}

	/**
	 * Nested category nav items.
	 *
	 * @param list<array{term: \WP_Term, children: array}> $nodes     Tree.
	 * @param bool                                           $seo_urls Pretty permalinks.
	 * @param string                                         $active   Active slug.
	 * @param bool                                           $show_count Show counts.
	 * @return string
	 */
	private static function render_category_nodes( $nodes, $seo_urls, $active, $show_count ) {
		$html = '';

		foreach ( $nodes as $node ) {
			$term = $node['term'] ?? null;
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$url      = Faq_Filter::get_nav_url( $term->slug, $seo_urls );
			$count    = null;
			if ( $show_count ) {
				$count = isset( $node['inclusive_count'] ) ? (int) $node['inclusive_count'] : (int) $term->count;
			}
			$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : [];
			$inner    = '';

			if ( ! empty( $children ) ) {
				$inner  = '<ul class="forwp-faq-categories__children">';
				$inner .= self::render_category_nodes( $children, $seo_urls, $active, $show_count );
				$inner .= '</ul>';
			}

			$html .= self::render_category_item(
				$term->slug,
				$term->name,
				$url,
				$seo_urls,
				$active,
				$count,
				$inner,
				! empty( $children )
			);
		}

		return $html;
	}

	/**
	 * Link from a truncated All-view group to the full category.
	 *
	 * @param \WP_Term $term     Term.
	 * @param bool     $seo_urls Pretty permalinks.
	 * @param int      $total    Full count.
	 * @return string
	 */
	private static function render_see_all( $term, $seo_urls, $total ) {
		unset( $total );

		$url = Faq_Filter::get_category_url( $term->slug, $seo_urls );
		/* translators: %s: FAQ category name */
		$label = sprintf( __( 'View all in %s', '4wp-faq' ), Faq_Terms::get_display_title( $term ) );

		return '<p class="forwp-faq-list__more"><a class="forwp-faq-list__more-link" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></p>';
	}

	/**
	 * One category nav item.
	 *
	 * @param string   $slug        Term slug (empty = all).
	 * @param string   $label       Label.
	 * @param string   $url         Href.
	 * @param bool     $seo_urls    Pretty permalinks.
	 * @param string   $active      Active slug.
	 * @param int|null $count       Optional count.
	 * @param string   $inner       Optional nested list HTML.
	 * @param bool     $has_children Whether the item has children.
	 * @return string
	 */
	private static function render_category_item( $slug, $label, $url, $seo_urls, $active, $count, $inner = '', $has_children = false ) {
		$is_active = ( $slug === $active );
		$context   = self::context_attr(
			[
				'slug'    => $slug,
				'url'     => $url,
				'seoUrls' => (bool) $seo_urls,
			]
		);

		$classes = 'forwp-faq-categories__item';
		if ( $is_active ) {
			$classes .= ' is-active';
		}
		if ( $has_children ) {
			$classes .= ' has-children';
		}

		$html  = '<li class="' . esc_attr( $classes ) . '"' . $context . ' data-wp-class--is-active="state.isNavActive">';
		$html .= '<a class="forwp-faq-categories__link" href="' . esc_url( $url ) . '" data-faq-cat="' . esc_attr( $slug ) . '" data-wp-on--click="actions.selectCategory">';
		$html .= '<span class="forwp-faq-categories__term">' . esc_html( $label ) . '</span>';
		if ( null !== $count ) {
			$html .= '<span class="forwp-faq-categories__count">' . esc_html( (string) $count ) . '</span>';
		}
		$html .= '</a>';
		$html .= $inner;
		$html .= '</li>';

		return $html;
	}

	/**
	 * Enqueue view script and hydrate Interactivity state once.
	 */
	private static function ensure_runtime() {
		static $done = false;

		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			wp_enqueue_script_module( self::VIEW_SCRIPT );
		}

		if ( $done || ! function_exists( 'wp_interactivity_state' ) ) {
			return;
		}

		$context = self::get_list_query_context();
		$count   = Registry_Content::count_visible_posts(
			$context['includeTermIds'],
			$context['excludeTermIds'],
			$context['activeSlug'],
			$context['previewLimit'],
			$context['layout']
		);

		$done = true;
		wp_interactivity_state(
			self::STORE,
			[
				'category'       => $context['activeSlug'],
				'search'         => '',
				'visibleCount'   => $count,
				'isNavActive'    => static function () {
					$state    = wp_interactivity_state( 'forwp/faq' );
					$context  = wp_interactivity_get_context( 'forwp/faq' );
					$category = isset( $state['category'] ) ? (string) $state['category'] : '';
					$slug     = isset( $context['slug'] ) ? (string) $context['slug'] : '';

					return $category === $slug;
				},
				'isGroupVisible' => static function () {
					$state    = wp_interactivity_state( 'forwp/faq' );
					$context  = wp_interactivity_get_context( 'forwp/faq' );
					$category = isset( $state['category'] ) ? (string) $state['category'] : '';
					if ( '' === $category ) {
						return true;
					}

					$slug      = isset( $context['slug'] ) ? (string) $context['slug'] : '';
					$ancestors = isset( $context['ancestors'] ) ? preg_split( '/\s+/', (string) $context['ancestors'], -1, PREG_SPLIT_NO_EMPTY ) : [];

					return $slug === $category || in_array( $category, $ancestors, true );
				},
				'isItemVisible'  => static function () {
					$state    = wp_interactivity_state( 'forwp/faq' );
					$context  = wp_interactivity_get_context( 'forwp/faq' );
					$category = isset( $state['category'] ) ? (string) $state['category'] : '';
					$cats     = isset( $context['cats'] ) ? preg_split( '/\s+/', (string) $context['cats'], -1, PREG_SPLIT_NO_EMPTY ) : [];
					if ( '' !== $category && ! in_array( $category, $cats, true ) ) {
						return false;
					}

					return true;
				},
			]
		);
	}

	/**
	 * @param array<string, mixed> $context Context data.
	 * @return string
	 */
	private static function context_attr( $context ) {
		if ( function_exists( 'wp_interactivity_data_wp_context' ) ) {
			return ' ' . wp_interactivity_data_wp_context( $context );
		}

		return ' data-wp-context="' . esc_attr( wp_json_encode( $context ) ) . '"';
	}
}
