<?php
/**
 * FAQ case: generate category copy (description + titles + meta).
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ\Ai;

use ForWP\FAQ\Faq_Terms;
use ForWP\FAQ\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Entity case: FAQ category fields from one prompt.
 */
class Term_Description {
	/**
	 * JSON keys mapped to form fields.
	 *
	 * @return list<array{id: string, field: string, label: string}>
	 */
	public static function slots() {
		return [
			[
				'id'    => 'description',
				'field' => '#description',
				'label' => __( 'Description', '4wp-faq' ),
			],
			[
				'id'    => 'display_title',
				'field' => '#forwp_display_title',
				'label' => __( 'Display title', '4wp-faq' ),
			],
			[
				'id'    => 'seo_title',
				'field' => '#forwp_seo_title',
				'label' => __( 'SEO title', '4wp-faq' ),
			],
			[
				'id'    => 'seo_description',
				'field' => '#forwp_seo_description',
				'label' => __( 'SEO description', '4wp-faq' ),
			],
		];
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'forwp-faq/v1',
			'/terms/(?P<id>\d+)/ai-prompt',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'get_prompt' ],
				'permission_callback' => [ __CLASS__, 'can_edit_term' ],
				'args'                => [
					'id' => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			'forwp-faq/v1',
			'/terms/(?P<id>\d+)/ai-generate',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'generate_fields' ],
				'permission_callback' => [ __CLASS__, 'can_edit_term' ],
				'args'                => [
					'id'     => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'prompt' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					],
				],
			]
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_edit_term( $request ) {
		if ( ! Settings::is_setup_complete() ) {
			return false;
		}

		$taxonomy = Settings::get_taxonomy();
		$tax      = get_taxonomy( $taxonomy );
		if ( ! $tax ) {
			return false;
		}

		if ( ! current_user_can( $tax->cap->manage_terms ) ) {
			return false;
		}

		$term = get_term( (int) $request['id'], $taxonomy );

		return $term instanceof \WP_Term;
	}

	/**
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'term.php' !== $hook || ! Settings::is_setup_complete() ) {
			return;
		}

		if ( ! Settings::can_use_ai_field_markup() ) {
			return;
		}

		$taxonomy = Settings::get_taxonomy();
		$screen   = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! is_object( $screen ) || ! isset( $screen->taxonomy ) || $screen->taxonomy !== $taxonomy ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$term_id = isset( $_GET['tag_ID'] ) ? absint( wp_unslash( $_GET['tag_ID'] ) ) : 0;
		if ( $term_id <= 0 ) {
			return;
		}

		\ForWP\AI\Panel::enqueue(
			[
				'slots'         => self::slots(),
				'prompt_path'   => '/forwp-faq/v1/terms/' . $term_id . '/ai-prompt',
				'generate_path' => '/forwp-faq/v1/terms/' . $term_id . '/ai-generate',
				'i18n'          => [
					'generate' => __( 'Generate with AI', '4wp-faq' ),
					'fab'      => __( 'AI prompt', '4wp-faq' ),
					'title'    => __( 'Category copy', '4wp-faq' ),
					'prompt'   => __( 'Prompt', '4wp-faq' ),
					'send'     => __( 'Send', '4wp-faq' ),
					'clear'    => __( 'Clear', '4wp-faq' ),
					'restore'  => __( 'Restore default', '4wp-faq' ),
					'apply'    => __( 'Apply', '4wp-faq' ),
					'applyAll' => __( 'Apply all', '4wp-faq' ),
					'refine'   => __( 'Refine', '4wp-faq' ),
					'close'    => __( 'Close', '4wp-faq' ),
					'move'     => __( 'Drag to move', '4wp-faq' ),
					'working'  => __( 'Generating…', '4wp-faq' ),
					'loading'  => __( 'Loading prompt…', '4wp-faq' ),
					'done'     => __( 'Inserted into the form. Review, then Update.', '4wp-faq' ),
					'confirm'  => __( 'Replace existing values in the form?', '4wp-faq' ),
					'empty'    => __( 'Write or restore the prompt first.', '4wp-faq' ),
					'error'    => __( 'Could not generate category copy.', '4wp-faq' ),
				],
			]
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_prompt( $request ) {
		$term = get_term( (int) $request['id'], Settings::get_taxonomy() );

		if ( ! $term instanceof \WP_Term ) {
			return new \WP_Error(
				'forwp_faq_term_invalid',
				__( 'FAQ category not found.', '4wp-faq' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! Settings::can_use_ai_field_markup() ) {
			return new \WP_Error(
				'forwp_faq_ai_disabled',
				__( 'AI field markup is not enabled. Turn it on under Settings → 4WP FAQ.', '4wp-faq' ),
				[ 'status' => 403 ]
			);
		}

		return new \WP_REST_Response(
			[
				'prompt' => self::build_prompt( $term ),
			],
			200
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function generate_fields( $request ) {
		$term = get_term( (int) $request['id'], Settings::get_taxonomy() );

		if ( ! $term instanceof \WP_Term ) {
			return new \WP_Error(
				'forwp_faq_term_invalid',
				__( 'FAQ category not found.', '4wp-faq' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! Settings::can_use_ai_field_markup() ) {
			return new \WP_Error(
				'forwp_faq_ai_disabled',
				__( 'AI field markup is not enabled. Turn it on under Settings → 4WP FAQ.', '4wp-faq' ),
				[ 'status' => 403 ]
			);
		}

		$keys   = self::slot_ids();
		$fields = \ForWP\AI\Client::generate_json(
			(string) $request->get_param( 'prompt' ),
			self::system_instruction(),
			480,
			$keys
		);

		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		if ( ! self::has_copy( $fields ) ) {
			return new \WP_Error(
				'forwp_ai_empty',
				__( 'The model returned empty text.', '4wp-faq' ),
				[ 'status' => 502 ]
			);
		}

		return new \WP_REST_Response(
			[
				'fields' => $fields,
			],
			200
		);
	}

	/**
	 * @return string[]
	 */
	private static function slot_ids() {
		$ids = [];
		foreach ( self::slots() as $slot ) {
			$ids[] = $slot['id'];
		}

		return $ids;
	}

	/**
	 * @param array<string, mixed> $fields Mapped fields.
	 * @return bool
	 */
	private static function has_copy( array $fields ) {
		foreach ( $fields as $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string
	 */
	private static function system_instruction() {
		return implode(
			"\n",
			[
				'You write FAQ category copy for a public website.',
				'Output JSON only. No markdown, no extra keys.',
				'Schema: {"description":"","display_title":"","seo_title":"","seo_description":""}',
				'description: 1 or 2 sentences, plain text.',
				'display_title: short on-page heading. Not a slogan dump.',
				'seo_title: document title, under 60 characters, no site name.',
				'seo_description: meta description, under 160 characters.',
				'Use the language from the prompt. Do not invent facts beyond the category name and questions.',
			]
		);
	}

	/**
	 * @param \WP_Term $term Term.
	 * @return string
	 */
	private static function build_prompt( $term ) {
		$language  = self::language_label( $term->term_id );
		$questions = self::question_titles( (int) $term->term_id );
		$site      = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$current   = self::current_values( $term );

		$lines   = [];
		$lines[] = 'Write copy for this FAQ category. Fill every field in the JSON schema.';
		$lines[] = 'Site: ' . $site;
		$lines[] = 'Language: ' . $language;
		$lines[] = 'Category name: ' . $term->name;

		if ( ! empty( $questions ) ) {
			$lines[] = 'Questions in this category:';
			foreach ( $questions as $title ) {
				$lines[] = '- ' . $title;
			}
		}

		$filled = [];
		foreach ( $current as $key => $value ) {
			if ( '' !== $value ) {
				$filled[] = $key . ': ' . $value;
			}
		}
		if ( ! empty( $filled ) ) {
			$lines[] = 'Current values (revise if useful, do not copy blindly):';
			foreach ( $filled as $line ) {
				$lines[] = '- ' . $line;
			}
		}

		$lines[] = 'Do not invent facts beyond the name and questions.';

		return implode( "\n", $lines );
	}

	/**
	 * @param \WP_Term $term Term.
	 * @return array<string, string>
	 */
	private static function current_values( $term ) {
		return [
			'description'     => trim( wp_strip_all_tags( (string) $term->description ) ),
			'display_title'   => trim( (string) get_term_meta( $term->term_id, Faq_Terms::META_DISPLAY_TITLE, true ) ),
			'seo_title'       => trim( (string) get_term_meta( $term->term_id, Faq_Terms::META_SEO_TITLE, true ) ),
			'seo_description' => trim( (string) get_term_meta( $term->term_id, Faq_Terms::META_SEO_DESC, true ) ),
		];
	}

	/**
	 * @param int $term_id Term ID.
	 * @return string
	 */
	private static function language_label( $term_id ) {
		$slug = '';
		if ( function_exists( 'pll_get_term_language' ) ) {
			$got  = pll_get_term_language( $term_id );
			$slug = is_string( $got ) ? $got : '';
		}

		if ( '' === $slug ) {
			$slug = 'en';
		}

		$map = [
			'en' => 'English',
			'es' => 'Spanish',
			'de' => 'German',
			'ru' => 'Russian',
			'uk' => 'Ukrainian',
		];

		return isset( $map[ $slug ] ) ? $map[ $slug ] : $slug;
	}

	/**
	 * @param int $term_id Term ID.
	 * @return string[]
	 */
	private static function question_titles( $term_id ) {
		if ( ! Settings::is_setup_complete() ) {
			return [];
		}

		$query_args = [
			'post_type'              => Settings::get_post_type(),
			'post_status'            => 'publish',
			'posts_per_page'         => 20,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => [
				[
					'taxonomy' => Settings::get_taxonomy(),
					'field'    => 'term_id',
					'terms'    => [ $term_id ],
				],
			],
		];

		if ( function_exists( 'pll_get_term_language' ) ) {
			$lang = pll_get_term_language( $term_id );
			if ( is_string( $lang ) && '' !== $lang ) {
				$query_args['lang'] = $lang;
			}
		}

		$query = new \WP_Query( $query_args );

		$titles = [];
		foreach ( $query->posts as $post_id ) {
			$title = get_the_title( (int) $post_id );
			if ( is_string( $title ) && '' !== $title ) {
				$titles[] = wp_strip_all_tags( $title );
			}
		}

		return $titles;
	}
}
