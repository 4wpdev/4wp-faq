<?php
/**
 * FAQ case: generate core term Description.
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ\Ai;

use ForWP\FAQ\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field + prompt for FAQ category Description.
 */
class Term_Description {
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
			'/terms/(?P<id>\d+)/description-prompt',
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
			'/terms/(?P<id>\d+)/generate-description',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'generate_description' ],
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

		if ( ! \ForWP\AI\Client::is_available() ) {
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
				'field'         => '#description',
				'prompt_path'   => '/forwp-faq/v1/terms/' . $term_id . '/description-prompt',
				'generate_path' => '/forwp-faq/v1/terms/' . $term_id . '/generate-description',
				'i18n'          => [
					'generate' => __( 'Generate with AI', '4wp-faq' ),
					'fab'      => __( 'AI prompt', '4wp-faq' ),
					'title'    => __( 'Description prompt', '4wp-faq' ),
					'prompt'   => __( 'Prompt', '4wp-faq' ),
					'result'   => __( 'Result', '4wp-faq' ),
					'send'     => __( 'Send', '4wp-faq' ),
					'clear'    => __( 'Clear', '4wp-faq' ),
					'restore'  => __( 'Restore default', '4wp-faq' ),
					'apply'    => __( 'Apply to Description', '4wp-faq' ),
					'refine'   => __( 'Refine', '4wp-faq' ),
					'close'    => __( 'Close', '4wp-faq' ),
					'move'     => __( 'Drag to move', '4wp-faq' ),
					'working'  => __( 'Generating…', '4wp-faq' ),
					'loading'  => __( 'Loading prompt…', '4wp-faq' ),
					'done'     => __( 'Inserted into Description. Review, then Update.', '4wp-faq' ),
					'confirm'  => __( 'Replace the current Description?', '4wp-faq' ),
					'empty'    => __( 'Write or restore the prompt first.', '4wp-faq' ),
					'error'    => __( 'Could not generate a description.', '4wp-faq' ),
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
	public static function generate_description( $request ) {
		$term = get_term( (int) $request['id'], Settings::get_taxonomy() );

		if ( ! $term instanceof \WP_Term ) {
			return new \WP_Error(
				'forwp_faq_term_invalid',
				__( 'FAQ category not found.', '4wp-faq' ),
				[ 'status' => 404 ]
			);
		}

		$text = \ForWP\AI\Client::generate(
			(string) $request->get_param( 'prompt' ),
			self::system_instruction(),
			180
		);

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		return new \WP_REST_Response(
			[
				'text' => $text,
			],
			200
		);
	}

	/**
	 * @return string
	 */
	private static function system_instruction() {
		return 'You write short FAQ category descriptions for a public website. Output plain text only: 1 or 2 sentences. No title, quotes, markdown, or list.';
	}

	/**
	 * @param \WP_Term $term Term.
	 * @return string
	 */
	private static function build_prompt( $term ) {
		$language  = self::language_label( $term->term_id );
		$questions = self::question_titles( (int) $term->term_id );
		$site      = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$lines   = [];
		$lines[] = 'Write the Description field for this FAQ category.';
		$lines[] = 'Site: ' . $site;
		$lines[] = 'Language: ' . $language;
		$lines[] = 'Category name: ' . $term->name;

		if ( ! empty( $questions ) ) {
			$lines[] = 'Questions in this category:';
			foreach ( $questions as $title ) {
				$lines[] = '- ' . $title;
			}
		}

		$lines[] = 'Describe what this category covers. Do not invent facts beyond the name and questions.';

		return implode( "\n", $lines );
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
