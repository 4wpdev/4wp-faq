<?php
namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	public const BLOCK_NAME = 'forwp/faq';
	public const POST_TYPE  = 'forwp_faq';
	public const SCAN_EVENT = 'forwp_faq_scan';

	/**
	 * Bootstrap plugin hooks.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );
		add_action( 'init', [ __CLASS__, 'register_post_meta' ] );
		add_action( 'init', [ __CLASS__, 'register_block' ] );
		add_action( 'wp_footer', [ __CLASS__, 'render_schema' ], 99 );

		add_action( 'save_post', [ __CLASS__, 'handle_post_save' ], 20, 3 );
		add_action( 'deleted_post', [ __CLASS__, 'schedule_scan' ] );
		add_action( self::SCAN_EVENT, [ __CLASS__, 'scan_all_posts' ] );

		if ( is_admin() ) {
			add_action( 'admin_post_forwp_faq_scan', [ __CLASS__, 'handle_manual_scan' ] );
			add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ __CLASS__, 'add_admin_columns' ] );
			add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ __CLASS__, 'render_admin_column' ], 10, 2 );
			add_action( 'add_meta_boxes_' . self::POST_TYPE, [ __CLASS__, 'register_meta_boxes' ] );
		}
	}

	/**
	 * Plugin activation tasks.
	 */
	public static function on_activation() {
		self::register_post_type();
		self::register_post_meta();
		flush_rewrite_rules();
		self::schedule_scan();
	}

	/**
	 * Plugin deactivation tasks.
	 */
	public static function on_deactivation() {
		wp_clear_scheduled_hook( self::SCAN_EVENT );
		flush_rewrite_rules();
	}

	/**
	 * Register the FAQ wrapper block.
	 */
	public static function register_block() {
		register_block_type(
			FORWP_FAQ_PLUGIN_DIR . 'block.json',
			[
				'render_callback' => [ __CLASS__, 'render_block' ],
			]
		);
	}

	/**
	 * Render the FAQ wrapper block output.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Inner blocks content.
	 * @return string
	 */
	public static function render_block( $attributes, $content ) {
		$class_name = isset( $attributes['className'] ) ? trim( $attributes['className'] ) : '';
		$classes    = trim( 'wp-block-4wp-faq ' . $class_name );

		return sprintf( '<div class="%s">%s</div>', esc_attr( $classes ), $content );
	}

	/**
	 * Register the aggregated FAQ CPT.
	 */
	public static function register_post_type() {
		$labels = [
			'name'               => __( '4WP FAQ', 'forwp-faq' ),
			'singular_name'      => __( 'FAQ', 'forwp-faq' ),
			'menu_name'          => __( '4WP FAQ', 'forwp-faq' ),
			'all_items'          => __( '4WP FAQ', 'forwp-faq' ),
			'add_new_item'       => __( 'Add New FAQ', 'forwp-faq' ),
			'edit_item'          => __( 'Edit FAQ', 'forwp-faq' ),
			'new_item'           => __( 'New FAQ', 'forwp-faq' ),
			'view_item'          => __( 'View FAQ', 'forwp-faq' ),
			'search_items'       => __( 'Search FAQs', 'forwp-faq' ),
			'not_found'          => __( 'No FAQs found', 'forwp-faq' ),
			'not_found_in_trash' => __( 'No FAQs found in Trash', 'forwp-faq' ),
		];

		register_post_type(
			self::POST_TYPE,
			[
				'labels'       => $labels,
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-editor-help',
				'supports'     => [ 'title' ],
			]
		);
	}

	/**
	 * Register FAQ meta fields.
	 */
	public static function register_post_meta() {
		register_post_meta(
			self::POST_TYPE,
			'answers',
			[
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type' => 'string',
						],
					],
				],
				'auth_callback' => '__return_true',
			]
		);

		register_post_meta(
			self::POST_TYPE,
			'answers_html',
			[
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type' => 'string',
						],
					],
				],
				'auth_callback' => '__return_true',
			]
		);

		register_post_meta(
			self::POST_TYPE,
			'source_titles',
			[
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type' => 'string',
						],
					],
				],
				'auth_callback' => '__return_true',
			]
		);

		register_post_meta(
			self::POST_TYPE,
			'original_question',
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'auth_callback' => '__return_true',
			]
		);

		register_post_meta(
			self::POST_TYPE,
			'used_in_posts',
			[
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type' => 'integer',
						],
					],
				],
				'auth_callback' => '__return_true',
			]
		);

		register_post_meta(
			self::POST_TYPE,
			'used_in_post_types',
			[
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type' => 'string',
						],
					],
				],
				'auth_callback' => '__return_true',
			]
		);

		register_post_meta(
			self::POST_TYPE,
			'count_usage',
			[
				'type'         => 'integer',
				'single'       => true,
				'show_in_rest' => true,
				'auth_callback' => '__return_true',
			]
		);
	}

	/**
	 * Output JSON-LD schema in the footer for posts containing the FAQ block.
	 */
	public static function render_schema() {
		if ( ! is_singular() ) {
			return;
		}

		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ! has_block( self::BLOCK_NAME, $post ) ) {
			return;
		}

		$items = self::extract_faq_items_from_content( $post->post_content, $post );
		if ( empty( $items ) ) {
			return;
		}

		$entities = self::build_schema_entities( $items );
		if ( empty( $entities ) ) {
			return;
		}

		$schema = [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		];

		printf(
			'<script type="application/ld+json">%s</script>',
			wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
	}

	/**
	 * Schedule a full scan when content changes.
	 *
	 * @param int $post_id Post ID.
	 * @param \WP_Post|null $post Post object.
	 * @param bool $update Whether this is an update.
	 */
	public static function handle_post_save( $post_id, $post, $update ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( $post->post_type === self::POST_TYPE ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		self::schedule_scan();
	}

	/**
	 * Schedule a scan if one is not already queued.
	 */
	public static function schedule_scan() {
		if ( ! wp_next_scheduled( self::SCAN_EVENT ) ) {
			wp_schedule_single_event( time() + 60, self::SCAN_EVENT );
		}
	}

	/**
	 * Scan all posts to aggregate FAQ items into the CPT.
	 */
	public static function scan_all_posts() {
		$post_ids = get_posts(
			[
				'post_type'      => 'any',
				'post_status'    => [ 'publish', 'draft', 'pending', 'future', 'private' ],
				'fields'         => 'ids',
				'posts_per_page' => -1,
			]
		);

		$items = [];
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if ( ! has_block( self::BLOCK_NAME, $post ) ) {
				continue;
			}

			$items = array_merge( $items, self::extract_faq_items_from_content( $post->post_content, $post ) );
		}

		$aggregated = self::aggregate_items( $items );
		self::sync_faq_posts( $aggregated );
	}

	/**
	 * Handle manual scan requests.
	 */
	public static function handle_manual_scan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'forwp-faq' ) );
		}

		check_admin_referer( 'forwp_faq_scan' );
		self::scan_all_posts();

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=' . self::POST_TYPE );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Extract FAQ items from a post's content.
	 *
	 * @param string   $content Post content.
	 * @param \WP_Post $post    Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private static function extract_faq_items_from_content( $content, $post ) {
		if ( ! has_blocks( $content ) ) {
			return [];
		}

		$blocks = parse_blocks( $content );
		return self::collect_faq_items_from_blocks( $blocks, $post );
	}

	/**
	 * Traverse blocks to find FAQ wrappers and items.
	 *
	 * @param array    $blocks Parsed blocks.
	 * @param \WP_Post $post   Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_faq_items_from_blocks( $blocks, $post ) {
		$items = [];
		foreach ( $blocks as $block ) {
			$block_name = $block['blockName'] ?? '';

			if ( self::BLOCK_NAME === $block_name ) {
				$items = array_merge( $items, self::extract_items_from_blocks( $block['innerBlocks'] ?? [], $post ) );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$items = array_merge( $items, self::collect_faq_items_from_blocks( $block['innerBlocks'], $post ) );
			}
		}

		return $items;
	}

	/**
	 * Extract FAQ items from accordion-style blocks.
	 *
	 * @param array    $blocks Parsed blocks.
	 * @param \WP_Post $post   Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private static function extract_items_from_blocks( $blocks, $post ) {
		$items = [];
		foreach ( $blocks as $block ) {
			$block_name = $block['blockName'] ?? '';

			if ( self::is_faq_item_block( $block_name ) ) {
				$item = self::build_item_from_block( $block, $post );
				if ( $item ) {
					$items[] = $item;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$items = array_merge( $items, self::extract_items_from_blocks( $block['innerBlocks'], $post ) );
			}
		}

		return $items;
	}

	/**
	 * Determine if a block can be treated as a FAQ item.
	 *
	 * @param string $block_name Block name.
	 * @return bool
	 */
	private static function is_faq_item_block( $block_name ) {
		if ( ! is_string( $block_name ) || '' === $block_name ) {
			return false;
		}

		if ( 'core/accordion-item' === $block_name || false !== strpos( $block_name, 'accordion-item' ) ) {
			return true;
		}

		return 'core/details' === $block_name || false !== strpos( $block_name, '/details' );
	}

	/**
	 * Build a FAQ item from a block.
	 *
	 * @param array    $block Block data.
	 * @param \WP_Post $post  Post object.
	 * @return array<string, mixed>|null
	 */
	private static function build_item_from_block( $block, $post ) {
		$texts        = self::extract_item_texts( $block );
		$question     = $texts['question'];
		$answer       = $texts['answer'];
		$answer_html  = $texts['answer_html'];

		if ( '' === $question || '' === $answer ) {
			return null;
		}

		return [
			'title'       => $question,
			'question'    => $question,
			'answer'      => $answer,
			'answer_html' => $answer_html,
			'post_id'     => $post->ID,
			'post_type'   => $post->post_type,
			'permalink'   => get_permalink( $post ),
			'post_title'  => get_the_title( $post ),
		];
	}

	/**
	 * Extract the question text from a block.
	 *
	 * @param array $block Block data.
	 * @return string
	 */
	private static function extract_question( $block ) {
		$attrs = $block['attrs'] ?? [];
		foreach ( [ 'title', 'summary', 'question', 'heading' ] as $key ) {
			if ( ! empty( $attrs[ $key ] ) ) {
				$question = trim( wp_strip_all_tags( (string) $attrs[ $key ] ) );
				return self::normalize_question_text( $question );
			}
		}

		return '';
	}

	/**
	 * Extract question/answer using accordion child blocks when needed.
	 *
	 * @param array $block Block data.
	 * @return array{question:string,answer:string,answer_html:string}
	 */
	private static function extract_item_texts( $block ) {
		$question = self::extract_question( $block );
		$answer_text = '';
		$answer_html = '';
		$inner       = $block['innerBlocks'] ?? [];

		if ( '' === $question && ! empty( $inner ) ) {
			$question = self::normalize_question_text( self::render_blocks_to_text( [ $inner[0] ] ) );
			if ( count( $inner ) > 1 ) {
				$answer_text = self::render_blocks_to_text( array_slice( $inner, 1 ) );
				$answer_html = self::render_blocks_to_html( array_slice( $inner, 1 ) );
			}
		}

		if ( '' === $answer_text && ! empty( $inner ) ) {
			$answer_text = self::render_blocks_to_text( $inner );
		}

		if ( '' === $answer_html && ! empty( $inner ) ) {
			$answer_html = self::render_blocks_to_html( $inner );
		}

		if ( '' === $answer_text && ! empty( $block['innerHTML'] ) ) {
			$answer_text = trim( wp_strip_all_tags( $block['innerHTML'] ) );
		}

		if ( '' === $answer_html && ! empty( $block['innerHTML'] ) ) {
			$answer_html = trim( (string) $block['innerHTML'] );
		}

		return [
			'question'    => trim( $question ),
			'answer'      => trim( $answer_text ),
			'answer_html' => trim( $answer_html ),
		];
	}

	/**
	 * Extract the answer text from a block.
	 *
	 * @param array $block Block data.
	 * @return string
	 */
	private static function extract_answer( $block ) {
		$answer = '';
		if ( ! empty( $block['innerBlocks'] ) ) {
			$answer = self::render_blocks_to_text( $block['innerBlocks'] );
		}

		if ( '' === $answer && ! empty( $block['innerHTML'] ) ) {
			$answer = trim( wp_strip_all_tags( $block['innerHTML'] ) );
		}

		return trim( $answer );
	}

	/**
	 * Render blocks to plain text.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return string
	 */
	private static function render_blocks_to_text( $blocks ) {
		$text = '';
		foreach ( $blocks as $block ) {
			$html = render_block( $block );
			$text .= ' ' . wp_strip_all_tags( $html );
		}

		return trim( $text );
	}

	/**
	 * Render blocks to HTML.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return string
	 */
	private static function render_blocks_to_html( $blocks ) {
		$html = '';
		foreach ( $blocks as $block ) {
			$html .= render_block( $block );
		}

		return trim( $html );
	}

	/**
	 * Build schema entities from FAQ items for the current post.
	 *
	 * @param array<int, array<string, mixed>> $items FAQ items.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_schema_entities( $items ) {
		$entities = [];
		$seen     = [];

		foreach ( $items as $item ) {
			$key = self::normalize_question_key( $item['question'] . '|' . $item['answer'] );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$entities[] = [
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $item['answer'],
				],
			];

			$seen[ $key ] = true;
		}

		return $entities;
	}

	/**
	 * Aggregate FAQ items by question for CPT storage.
	 *
	 * @param array<int, array<string, mixed>> $items FAQ items.
	 * @return array<string, array<string, mixed>>
	 */
	private static function aggregate_items( $items ) {
		$aggregated = [];

		foreach ( $items as $item ) {
			$key = self::normalize_question_key( $item['question'] );
			if ( '' === $key ) {
				continue;
			}

			if ( ! isset( $aggregated[ $key ] ) ) {
				$aggregated[ $key ] = [
					'question'           => $item['question'],
					'answers'            => [],
					'answers_html'       => [],
					'source_titles'      => [],
					'used_in_posts'      => [],
					'used_in_post_types' => [],
				];
			}

			$aggregated[ $key ]['answers'][]            = $item['answer'];
			$aggregated[ $key ]['answers_html'][]       = $item['answer_html'];
			$aggregated[ $key ]['source_titles'][]      = $item['post_title'];
			$aggregated[ $key ]['used_in_posts'][]      = $item['post_id'];
			$aggregated[ $key ]['used_in_post_types'][] = $item['post_type'];
		}

		foreach ( $aggregated as $key => $data ) {
			$aggregated[ $key ]['answers']            = self::unique_values( $data['answers'] );
			$aggregated[ $key ]['answers_html']       = self::unique_values( $data['answers_html'] );
			$aggregated[ $key ]['source_titles']      = self::unique_values( $data['source_titles'] );
			$aggregated[ $key ]['used_in_posts']      = self::unique_values( $data['used_in_posts'] );
			$aggregated[ $key ]['used_in_post_types'] = self::unique_values( $data['used_in_post_types'] );
			$aggregated[ $key ]['count_usage']        = count( $aggregated[ $key ]['used_in_posts'] );
		}

		return $aggregated;
	}

	/**
	 * Sync aggregated data into the FAQ CPT.
	 *
	 * @param array<string, array<string, mixed>> $aggregated Aggregated FAQ data.
	 */
	private static function sync_faq_posts( $aggregated ) {
		$existing_ids = get_posts(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
			]
		);

		$existing_map = [];
		foreach ( $existing_ids as $faq_id ) {
			$faq = get_post( $faq_id );
			if ( $faq instanceof \WP_Post ) {
				$existing_map[ self::normalize_question_key( $faq->post_title ) ] = $faq_id;
			}
		}

		$handled_ids = [];
		foreach ( $aggregated as $key => $data ) {
			$post_id = $existing_map[ $key ] ?? 0;

			$post_args = [
				'post_title'  => $data['question'],
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
			];

			if ( $post_id ) {
				$post_args['ID'] = $post_id;
				$result          = wp_update_post( $post_args, true );
			} else {
				$result = wp_insert_post( $post_args, true );
			}

			if ( is_wp_error( $result ) ) {
				continue;
			}

			$post_id = (int) $result;
			update_post_meta( $post_id, 'answers', $data['answers'] );
			update_post_meta( $post_id, 'answers_html', $data['answers_html'] );
			update_post_meta( $post_id, 'source_titles', $data['source_titles'] );
			update_post_meta( $post_id, 'original_question', $data['question'] );
			update_post_meta( $post_id, 'used_in_posts', $data['used_in_posts'] );
			update_post_meta( $post_id, 'used_in_post_types', $data['used_in_post_types'] );
			update_post_meta( $post_id, 'count_usage', $data['count_usage'] );

			$handled_ids[] = $post_id;
		}

		foreach ( $existing_ids as $faq_id ) {
			if ( ! in_array( $faq_id, $handled_ids, true ) ) {
				wp_delete_post( $faq_id, true );
			}
		}
	}

	/**
	 * Add admin columns for FAQ CPT list table.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function add_admin_columns( $columns ) {
		$columns['answers']      = __( 'Answers', 'forwp-faq' );
		$columns['answers_html'] = __( 'Answers (HTML)', 'forwp-faq' );
		$columns['usage']        = __( 'Usage', 'forwp-faq' );

		return $columns;
	}

	/**
	 * Render admin column values.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function render_admin_column( $column, $post_id ) {
		if ( 'answers' === $column ) {
			$answers = get_post_meta( $post_id, 'answers', true );
			$answers = is_array( $answers ) ? $answers : [];
			if ( empty( $answers ) ) {
				echo '&mdash;';
				return;
			}
			$sample = wp_trim_words( (string) $answers[0], 16, '…' );
			echo esc_html( count( $answers ) . ' • ' . $sample );
			return;
		}

		if ( 'answers_html' === $column ) {
			$answers_html = get_post_meta( $post_id, 'answers_html', true );
			$answers_html = is_array( $answers_html ) ? $answers_html : [];
			if ( empty( $answers_html ) ) {
				echo '&mdash;';
				return;
			}
			$sample = wp_trim_words( wp_strip_all_tags( (string) $answers_html[0] ), 16, '…' );
			echo esc_html( count( $answers_html ) . ' • ' . $sample );
			return;
		}

		if ( 'usage' === $column ) {
			$count = (int) get_post_meta( $post_id, 'count_usage', true );
			echo esc_html( $count );
		}
	}

	/**
	 * Register meta boxes for FAQ CPT edit screen.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function register_meta_boxes( $post ) {
		add_meta_box(
			'forwp_faq_details',
			__( 'FAQ Details', 'forwp-faq' ),
			[ __CLASS__, 'render_meta_box' ],
			self::POST_TYPE,
			'normal',
			'default'
		);
		add_meta_box(
			'forwp_faq_usage',
			__( 'Used In', 'forwp-faq' ),
			[ __CLASS__, 'render_usage_meta_box' ],
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render FAQ meta box content.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_meta_box( $post ) {
		$answers = get_post_meta( $post->ID, 'answers', true );
		$answers = is_array( $answers ) ? $answers : [];
		$answers_html = get_post_meta( $post->ID, 'answers_html', true );
		$answers_html = is_array( $answers_html ) ? $answers_html : [];
		$original_question = get_post_meta( $post->ID, 'original_question', true );
		$source_titles = get_post_meta( $post->ID, 'source_titles', true );
		$source_titles = is_array( $source_titles ) ? $source_titles : [];
		?>
		<div class="forwp-faq-meta">
			<p><strong><?php esc_html_e( 'FAQ title (original):', 'forwp-faq' ); ?></strong></p>
			<?php if ( $original_question ) : ?>
				<p><?php echo esc_html( $original_question ); ?></p>
			<?php else : ?>
				<p>&mdash;</p>
			<?php endif; ?>

			<p><strong><?php esc_html_e( 'Original titles:', 'forwp-faq' ); ?></strong></p>
			<?php if ( empty( $source_titles ) ) : ?>
				<p>&mdash;</p>
			<?php else : ?>
				<ul>
					<?php foreach ( $source_titles as $source_title ) : ?>
						<li><?php echo esc_html( $source_title ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<p><strong><?php esc_html_e( 'Answers (text):', 'forwp-faq' ); ?></strong></p>
			<?php if ( empty( $answers ) ) : ?>
				<p>&mdash;</p>
			<?php else : ?>
				<ol>
					<?php foreach ( $answers as $answer ) : ?>
						<li><?php echo esc_html( $answer ); ?></li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<p><strong><?php esc_html_e( 'Answers (HTML):', 'forwp-faq' ); ?></strong></p>
			<?php if ( empty( $answers_html ) ) : ?>
				<p>&mdash;</p>
			<?php else : ?>
				<ol>
					<?php foreach ( $answers_html as $answer_html ) : ?>
						<li>
							<textarea class="widefat" rows="4" readonly><?php echo esc_textarea( $answer_html ); ?></textarea>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<p><strong><?php esc_html_e( 'Answers (rendered):', 'forwp-faq' ); ?></strong></p>
			<?php if ( empty( $answers_html ) ) : ?>
				<p>&mdash;</p>
			<?php else : ?>
				<?php foreach ( $answers_html as $answer_html ) : ?>
					<div class="forwp-faq-rendered" style="margin: 8px 0 16px; padding: 12px; border: 1px solid #dcdcde; background: #fff;">
						<?php
						$rendered = do_blocks( $answer_html );
						echo wp_kses_post( $rendered );
						?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php
			$schema = '';
			$question = $original_question ? trim( (string) $original_question ) : trim( (string) $post->post_title );
			$answer = isset( $answers[0] ) ? trim( (string) $answers[0] ) : '';
			if ( '' !== $question && '' !== $answer ) {
				$schema = wp_json_encode(
					[
						'@context'   => 'https://schema.org',
						'@type'      => 'FAQPage',
						'mainEntity' => [
							[
								'@type'          => 'Question',
								'name'           => $question,
								'acceptedAnswer' => [
									'@type' => 'Answer',
									'text'  => $answer,
								],
							],
						],
					],
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
				);
			}
			$schema_id = 'forwp-faq-schema-' . (int) $post->ID;
			$scan_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=forwp_faq_scan' ),
				'forwp_faq_scan'
			);
			?>

			<p><strong><?php esc_html_e( 'Schema JSON-LD (preview):', 'forwp-faq' ); ?></strong></p>
			<?php if ( '' === $schema ) : ?>
				<p>&mdash;</p>
			<?php else : ?>
				<textarea id="<?php echo esc_attr( $schema_id ); ?>" class="widefat" rows="8" readonly><?php echo esc_textarea( $schema ); ?></textarea>
				<p>
					<button type="button" class="button" data-copy-target="<?php echo esc_attr( $schema_id ); ?>">
						<?php esc_html_e( 'Copy JSON', 'forwp-faq' ); ?>
					</button>
					<a class="button button-secondary" href="<?php echo esc_url( $scan_url ); ?>">
						<?php esc_html_e( 'Run scan now', 'forwp-faq' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<script>
			(function () {
				var root = document.currentScript && document.currentScript.parentNode;
				if (!root) return;
				var button = root.querySelector('button[data-copy-target]');
				if (!button) return;
				button.addEventListener('click', function () {
					var targetId = button.getAttribute('data-copy-target');
					var textarea = document.getElementById(targetId);
					if (!textarea) return;
					textarea.focus();
					textarea.select();
					try {
						document.execCommand('copy');
					} catch (e) {
						if (navigator.clipboard && navigator.clipboard.writeText) {
							navigator.clipboard.writeText(textarea.value);
						}
					}
				});
			})();
		</script>
		<?php
	}

	/**
	 * Render usage info in the sidebar.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_usage_meta_box( $post ) {
		$used_in_posts = get_post_meta( $post->ID, 'used_in_posts', true );
		$used_in_posts = is_array( $used_in_posts ) ? array_values( $used_in_posts ) : [];
		$groups = [];
		foreach ( $used_in_posts as $post_id ) {
			$source_post = get_post( (int) $post_id );
			if ( ! $source_post instanceof \WP_Post ) {
				continue;
			}
			$post_type = $source_post->post_type;
			if ( ! isset( $groups[ $post_type ] ) ) {
				$groups[ $post_type ] = [];
			}
			$groups[ $post_type ][] = $source_post;
		}
		$count_usage = (int) get_post_meta( $post->ID, 'count_usage', true );
		?>
		<p>
			<strong><?php esc_html_e( 'Usage count:', 'forwp-faq' ); ?></strong>
			<?php echo esc_html( $count_usage ); ?>
		</p>
		<?php if ( empty( $groups ) ) : ?>
			<p>&mdash;</p>
		<?php else : ?>
			<?php foreach ( $groups as $post_type => $items ) : ?>
				<?php
				$type_object = get_post_type_object( $post_type );
				$type_label = $type_object && ! empty( $type_object->labels->name )
					? $type_object->labels->name
					: $post_type;
				?>
				<p><strong><?php echo esc_html( $type_label ); ?>:</strong></p>
				<ul>
					<?php foreach ( $items as $source_post ) : ?>
						<?php
						$edit_link = get_edit_post_link( $source_post->ID );
						$status = get_post_status( $source_post );
						$status_obj = $status ? get_post_status_object( $status ) : null;
						$status_label = $status_obj && ! empty( $status_obj->label ) ? $status_obj->label : $status;
						$title = $source_post->post_title ? $source_post->post_title : $source_post->ID;
						?>
						<li>
							<?php if ( $edit_link ) : ?>
								<a href="<?php echo esc_url( $edit_link ); ?>">
									<?php echo esc_html( $title ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $title ); ?>
							<?php endif; ?>
							<?php if ( $status_label ) : ?>
								<code><?php echo esc_html( $status_label ); ?></code>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Normalize question keys.
	 *
	 * @param string $value Question text.
	 * @return string
	 */
	private static function normalize_question_key( $value ) {
		$normalized = trim( wp_strip_all_tags( (string) $value ) );
		if ( function_exists( 'mb_strtolower' ) ) {
			$normalized = mb_strtolower( $normalized );
		} else {
			$normalized = strtolower( $normalized );
		}

		return $normalized;
	}

	/**
	 * Return unique array values while preserving ordering.
	 *
	 * @param array $values Values array.
	 * @return array
	 */
	private static function unique_values( $values ) {
		$unique = [];
		foreach ( $values as $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}
			if ( ! in_array( $value, $unique, true ) ) {
				$unique[] = $value;
			}
		}

		return array_values( $unique );
	}

	/**
	 * Normalize question text (remove UI glyphs like trailing plus icon).
	 *
	 * @param string $value Question text.
	 * @return string
	 */
	private static function normalize_question_text( $value ) {
		$normalized = trim( (string) $value );
		$normalized = preg_replace( '/\s*\+\s*$/', '', $normalized );

		return trim( $normalized );
	}
}

