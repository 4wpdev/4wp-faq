<?php
namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Push registry CPT edits back into forwp/faq items on source posts.
 * Site content stays the source of truth; scan rebuilds the CPT.
 */
class Registry_Push {
	const NOTICE_TRANSIENT = 'forwp_faq_push_notice_';
	const NONCE_ACTION     = 'forwp_faq_push';
	const NONCE_FIELD      = 'forwp_faq_push_nonce';

	/**
	 * @var bool
	 */
	private static $running = false;

	/**
	 * @var array<int, string>
	 */
	private static $title_before = [];

	public static function init() {
		add_filter( 'wp_insert_post_data', [ __CLASS__, 'capture_title_before' ], 10, 2 );
		add_action( 'save_post', [ __CLASS__, 'handle_save' ], 40, 3 );
		add_action( 'admin_notices', [ __CLASS__, 'render_notice' ] );
	}

	/**
	 * Remember the question title as stored before this save.
	 *
	 * @param array $data    Post data to insert.
	 * @param array $postarr Raw post array.
	 * @return array
	 */
	public static function capture_title_before( $data, $postarr ) {
		$post_type = Settings::get_post_type();
		if ( ( $data['post_type'] ?? '' ) !== $post_type ) {
			return $data;
		}

		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $id < 1 ) {
			return $data;
		}

		$existing = get_post( $id );
		if ( $existing instanceof \WP_Post ) {
			self::$title_before[ $id ] = (string) $existing->post_title;
		}

		return $data;
	}

	/**
	 * On registry save: push edits to Used In pages, or append a new item.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public static function handle_save( $post_id, $post, $update ) {
		unset( $update );

		if ( self::$running || ! $post instanceof \WP_Post ) {
			return;
		}

		if ( $post->post_type !== Settings::get_post_type() ) {
			return;
		}

		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$new_question = Plugin::normalize_question_text( $post->post_title );
		$answer_html  = isset( $_POST['forwp_faq_answer_html'] )
			? wp_kses_post( wp_unslash( $_POST['forwp_faq_answer_html'] ) )
			: '';
		$answer_text  = trim( wp_strip_all_tags( $answer_html ) );

		if ( '' === $new_question || '' === $answer_text ) {
			self::store_notice(
				[
					'updated' => 0,
					'missed'  => [],
					'error'   => __( 'Question and answer are required.', '4wp-faq' ),
				]
			);
			return;
		}

		update_post_meta( $post_id, 'answers', [ $answer_text ] );
		update_post_meta( $post_id, 'answers_html', [ $answer_html ] );

		$used = get_post_meta( $post_id, 'used_in_posts', true );
		$used = is_array( $used ) ? array_values( array_unique( array_map( 'intval', $used ) ) ) : [];

		if ( ! empty( $used ) ) {
			self::push_replace( $post_id, $new_question, $answer_html, $used );
			return;
		}

		if ( ! in_array( $post->post_status, [ 'publish', 'private' ], true ) ) {
			self::store_notice(
				[
					'updated' => 0,
					'missed'  => [],
					'error'   => '',
					'orphan'  => true,
				]
			);
			return;
		}

		self::push_append( $post_id, $new_question, $answer_html );
	}

	/**
	 * Posts that already contain a forwp/faq block.
	 *
	 * @return \WP_Post[]
	 */
	public static function list_faq_pages() {
		$types = get_post_types( [ 'show_ui' => true ], 'names' );
		unset( $types[ Settings::get_post_type() ] );
		$types = array_values( $types );
		if ( empty( $types ) ) {
			return [];
		}

		$ids = get_posts(
			[
				'post_type'              => $types,
				'post_status'            => [ 'publish', 'draft', 'pending', 'future', 'private' ],
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'suppress_filters'       => false,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		$pages = [];
		foreach ( $ids as $id ) {
			$source = get_post( (int) $id );
			if ( ! $source instanceof \WP_Post ) {
				continue;
			}
			if ( ! has_block( Plugin::BLOCK_NAME, $source->post_content ) ) {
				continue;
			}
			$pages[] = $source;
		}

		return $pages;
	}

	/**
	 * Replace an existing item on Used In posts.
	 *
	 * @param int      $post_id      Registry ID.
	 * @param string   $new_question Question.
	 * @param string   $answer_html  Answer HTML.
	 * @param int[]    $used         Source post IDs.
	 */
	private static function push_replace( $post_id, $new_question, $answer_html, $used ) {
		$find_keys     = self::find_keys( $post_id, $new_question );
		$updated_posts = 0;
		$replaced      = 0;
		$missed        = [];

		self::$running = true;

		foreach ( $used as $source_id ) {
			if ( $source_id < 1 ) {
				continue;
			}

			$source = get_post( $source_id );
			if ( ! $source instanceof \WP_Post ) {
				$missed[] = $source_id;
				continue;
			}

			if ( ! current_user_can( 'edit_post', $source_id ) ) {
				$missed[] = $source_id;
				continue;
			}

			$result = self::replace_in_post( $source, $find_keys, $new_question, $answer_html );
			if ( $result < 1 ) {
				$missed[] = $source_id;
				continue;
			}

			$replaced += $result;
			++$updated_posts;
		}

		self::$running = false;

		if ( $replaced > 0 && empty( $missed ) ) {
			update_post_meta( $post_id, 'original_question', $new_question );
		}

		self::store_notice(
			[
				'updated'  => $updated_posts,
				'replaced' => $replaced,
				'missed'   => $missed,
				'error'    => '',
			]
		);

		if ( $updated_posts > 0 ) {
			Plugin::schedule_scan();
		}
	}

	/**
	 * Append a new item to category pages and/or one chosen page.
	 *
	 * @param int    $post_id      Registry ID.
	 * @param string $new_question Question.
	 * @param string $answer_html  Answer HTML.
	 */
	private static function push_append( $post_id, $new_question, $answer_html ) {
		$pin_id   = isset( $_POST['forwp_faq_add_to_post'] ) ? (int) $_POST['forwp_faq_add_to_post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$term_ids = wp_get_post_terms( $post_id, Settings::get_taxonomy(), [ 'fields' => 'ids' ] );
		$term_ids = is_array( $term_ids ) ? array_values( array_filter( array_map( 'intval', $term_ids ) ) ) : [];

		$targets    = [];
		$term_filter = $term_ids;

		if ( $pin_id > 0 ) {
			$targets     = [ $pin_id ];
			$term_filter = [];
		} elseif ( ! empty( $term_ids ) ) {
			$targets = self::find_posts_with_faq_terms( $term_ids );
		}

		if ( empty( $targets ) ) {
			self::store_notice(
				[
					'updated' => 0,
					'missed'  => [],
					'error'   => '',
					'orphan'  => true,
				]
			);
			return;
		}

		self::$running = true;

		$updated_posts = 0;
		$added         = 0;
		$missed        = [];
		$placed        = [];

		foreach ( $targets as $source_id ) {
			$source = get_post( (int) $source_id );
			if ( ! $source instanceof \WP_Post ) {
				$missed[] = (int) $source_id;
				continue;
			}

			if ( ! current_user_can( 'edit_post', $source->ID ) ) {
				$missed[] = $source->ID;
				continue;
			}

			$result = self::append_in_post( $source, $new_question, $answer_html, $term_filter, $pin_id > 0 );
			if ( $result < 1 ) {
				$missed[] = $source->ID;
				continue;
			}

			$added += $result;
			$placed[] = $source->ID;
			++$updated_posts;
		}

		self::$running = false;

		if ( ! empty( $placed ) ) {
			update_post_meta( $post_id, 'used_in_posts', array_values( array_unique( $placed ) ) );
			update_post_meta( $post_id, 'count_usage', count( array_unique( $placed ) ) );
			update_post_meta( $post_id, 'original_question', $new_question );
			$wrapped = self::item_answer_html_from_post( (int) $placed[0], $new_question );
			if ( '' !== $wrapped ) {
				update_post_meta( $post_id, 'answers_html', [ $wrapped ] );
				update_post_meta( $post_id, 'answers', [ trim( wp_strip_all_tags( $wrapped ) ) ] );
			}
		}

		self::store_notice(
			[
				'updated' => $updated_posts,
				'added'   => $added,
				'missed'  => $missed,
				'error'   => '',
				'orphan'  => $updated_posts < 1,
			]
		);

		if ( $updated_posts > 0 ) {
			Plugin::schedule_scan();
		}
	}

	/**
	 * @param int[] $term_ids FAQ category term IDs.
	 * @return int[]
	 */
	private static function find_posts_with_faq_terms( $term_ids ) {
		$found = [];
		foreach ( self::list_faq_pages() as $source ) {
			if ( self::post_has_faq_terms( $source, $term_ids ) ) {
				$found[] = (int) $source->ID;
			}
		}

		return $found;
	}

	/**
	 * @param \WP_Post $source   Source post.
	 * @param int[]    $term_ids Term IDs.
	 * @return bool
	 */
	private static function post_has_faq_terms( $source, $term_ids ) {
		$blocks = parse_blocks( $source->post_content );
		return self::tree_has_faq_terms( $blocks, (int) $source->ID, $term_ids );
	}

	/**
	 * @param array $blocks   Parsed blocks.
	 * @param int   $post_id  Source post ID.
	 * @param int[] $term_ids Term IDs.
	 * @return bool
	 */
	private static function tree_has_faq_terms( $blocks, $post_id, $term_ids ) {
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';
			if ( Plugin::BLOCK_NAME === $name ) {
				$resolved = Category_Resolver::resolve_from_block_attrs( $block['attrs'] ?? [], $post_id );
				if ( array_intersect( $term_ids, $resolved ) ) {
					return true;
				}
			}
			if ( ! empty( $block['innerBlocks'] ) && self::tree_has_faq_terms( $block['innerBlocks'], $post_id, $term_ids ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Keys used to find the item on pages (old title + current).
	 *
	 * @param int    $post_id      Registry post ID.
	 * @param string $new_question New question text.
	 * @return string[]
	 */
	private static function find_keys( $post_id, $new_question ) {
		$candidates = [
			$new_question,
			(string) get_post_meta( $post_id, 'original_question', true ),
			self::$title_before[ $post_id ] ?? '',
		];

		$revision = self::previous_revision_title( $post_id );
		if ( '' !== $revision ) {
			$candidates[] = $revision;
		}

		$keys = [];
		foreach ( $candidates as $candidate ) {
			$key = Plugin::normalize_question_key( Plugin::normalize_question_text( $candidate ) );
			if ( '' === $key || isset( $keys[ $key ] ) ) {
				continue;
			}
			$keys[ $key ] = $key;
		}

		return array_values( $keys );
	}

	/**
	 * Previous revision title (state before this save, if already stored).
	 *
	 * @param int $post_id Registry post ID.
	 * @return string
	 */
	private static function previous_revision_title( $post_id ) {
		$revisions = wp_get_post_revisions(
			$post_id,
			[
				'numberposts' => 1,
				'check_enabled' => false,
			]
		);

		if ( empty( $revisions ) ) {
			return '';
		}

		$revision = reset( $revisions );
		return $revision instanceof \WP_Post ? (string) $revision->post_title : '';
	}

	/**
	 * Replace matching FAQ items in one source post.
	 *
	 * @param \WP_Post $source       Source post.
	 * @param string[] $find_keys    Normalized question keys to match.
	 * @param string   $new_question New question.
	 * @param string   $answer_html  New answer HTML.
	 * @return int Replaced item count.
	 */
	private static function replace_in_post( $source, $find_keys, $new_question, $answer_html ) {
		if ( ! has_blocks( $source->post_content ) ) {
			return 0;
		}

		$blocks   = parse_blocks( $source->post_content );
		$replaced = 0;
		$blocks   = self::walk_blocks( $blocks, $find_keys, $new_question, $answer_html, $replaced );

		if ( $replaced < 1 ) {
			return 0;
		}

		$new_content = serialize_blocks( $blocks );
		if ( $new_content === $source->post_content ) {
			return 0;
		}

		$saved = wp_update_post(
			[
				'ID'           => $source->ID,
				'post_content' => $new_content,
			],
			true
		);

		return is_wp_error( $saved ) ? 0 : $replaced;
	}

	/**
	 * Append (or update if the question already exists) on one source post.
	 *
	 * @param \WP_Post $source          Source post.
	 * @param string   $question        Question.
	 * @param string   $answer_html     Answer HTML.
	 * @param int[]    $term_ids        Category filter (empty = any wrapper).
	 * @param bool     $fallback_first  If no matching category wrapper, use first FAQ block.
	 * @return int
	 */
	private static function append_in_post( $source, $question, $answer_html, $term_ids, $fallback_first ) {
		if ( ! has_blocks( $source->post_content ) ) {
			return 0;
		}

		$blocks   = parse_blocks( $source->post_content );
		$appended = 0;
		$blocks   = self::walk_append( $blocks, (int) $source->ID, $question, $answer_html, $term_ids, $appended );

		if ( $appended < 1 && $fallback_first ) {
			$blocks = self::walk_append( $blocks, (int) $source->ID, $question, $answer_html, [], $appended );
		}

		if ( $appended < 1 ) {
			return 0;
		}

		$new_content = serialize_blocks( $blocks );
		if ( $new_content === $source->post_content ) {
			return 0;
		}

		$saved = wp_update_post(
			[
				'ID'           => $source->ID,
				'post_content' => $new_content,
			],
			true
		);

		return is_wp_error( $saved ) ? 0 : $appended;
	}

	/**
	 * @param array  $blocks       Parsed blocks.
	 * @param int    $post_id      Source post ID.
	 * @param string $question     Question.
	 * @param string $answer_html  Answer HTML.
	 * @param int[]  $term_ids     Term filter.
	 * @param int    $appended     Count.
	 * @return array
	 */
	private static function walk_append( $blocks, $post_id, $question, $answer_html, $term_ids, &$appended ) {
		foreach ( $blocks as &$block ) {
			$name = $block['blockName'] ?? '';

			if ( Plugin::BLOCK_NAME === $name ) {
				$resolved = Category_Resolver::resolve_from_block_attrs( $block['attrs'] ?? [], $post_id );
				$match    = empty( $term_ids ) || (bool) array_intersect( $term_ids, $resolved );
				if ( $match ) {
					$block = self::append_to_wrapper( $block, $question, $answer_html, $appended );
				}
				continue;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::walk_append( $block['innerBlocks'], $post_id, $question, $answer_html, $term_ids, $appended );
			}
		}
		unset( $block );

		return $blocks;
	}

	/**
	 * @param array  $wrapper      forwp/faq block.
	 * @param string $question     Question.
	 * @param string $answer_html  Answer HTML.
	 * @param int    $appended     Count.
	 * @return array
	 */
	private static function append_to_wrapper( $wrapper, $question, $answer_html, &$appended ) {
		$key      = Plugin::normalize_question_key( $question );
		$replaced = 0;
		$wrapper['innerBlocks'] = self::walk_items(
			$wrapper['innerBlocks'] ?? [],
			[ $key ],
			$question,
			$answer_html,
			$replaced
		);
		if ( $replaced > 0 ) {
			$appended += $replaced;
			return $wrapper;
		}

		$placed = false;
		$wrapper['innerBlocks'] = self::append_in_accordion( $wrapper['innerBlocks'] ?? [], $question, $answer_html, $placed );
		if ( $placed ) {
			++$appended;
			$wrapper = self::rebuild_inner_content( $wrapper );
			return $wrapper;
		}

		$item = self::build_accordion_item( $question, $answer_html );
		$wrapper['innerBlocks'][] = $item;
		$wrapper = self::rebuild_inner_content( $wrapper );
		++$appended;

		return $wrapper;
	}

	/**
	 * @param array  $blocks       Inner blocks.
	 * @param string $question     Question.
	 * @param string $answer_html  Answer HTML.
	 * @param bool   $placed       Whether an item was appended.
	 * @return array
	 */
	private static function append_in_accordion( $blocks, $question, $answer_html, &$placed ) {
		foreach ( $blocks as &$block ) {
			$name = $block['blockName'] ?? '';
			if ( self::is_accordion_container( $name ) ) {
				$item = null;
				foreach ( $block['innerBlocks'] ?? [] as $child ) {
					$child_name = $child['blockName'] ?? '';
					if ( Plugin::is_faq_item_block( $child_name ) ) {
						$item = self::apply_item( $child, $question, $answer_html );
						break;
					}
				}
				if ( ! $item ) {
					$item = self::build_accordion_item( $question, $answer_html );
				}
				$block['innerBlocks'][] = $item;
				$block = self::rebuild_inner_content( $block );
				$placed = true;
				continue;
			}

			if ( ! empty( $block['innerBlocks'] ) && ! $placed ) {
				$block['innerBlocks'] = self::append_in_accordion( $block['innerBlocks'], $question, $answer_html, $placed );
			}
		}
		unset( $block );

		return $blocks;
	}

	/**
	 * @param string $block_name Block name.
	 * @return bool
	 */
	private static function is_accordion_container( $block_name ) {
		if ( ! is_string( $block_name ) || '' === $block_name ) {
			return false;
		}

		if ( 'core/accordion' === $block_name ) {
			return true;
		}

		if ( false === strpos( $block_name, 'accordion' ) ) {
			return false;
		}

		return false === strpos( $block_name, 'accordion-item' )
			&& false === strpos( $block_name, 'accordion-heading' )
			&& false === strpos( $block_name, 'accordion-panel' );
	}

	/**
	 * @param string $question    Question.
	 * @param string $answer_html Answer HTML.
	 * @return array
	 */
	private static function build_accordion_item( $question, $answer_html ) {
		$safe           = esc_html( $question );
		$answer_blocks  = serialize_blocks( self::html_to_blocks( $answer_html ) );
		$title_attr     = wp_json_encode( $question );
		$markup         = '<!-- wp:accordion-item -->'
			. '<div class="wp-block-accordion-item">'
			. '<!-- wp:accordion-heading {"level":3,"title":' . $title_attr . '} -->'
			. '<h3 class="wp-block-accordion-heading"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">'
			. $safe
			. '</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>'
			. '<!-- /wp:accordion-heading -->'
			. '<!-- wp:accordion-panel -->'
			. '<div class="wp-block-accordion-panel">'
			. $answer_blocks
			. '</div>'
			. '<!-- /wp:accordion-panel -->'
			. '</div>'
			. '<!-- /wp:accordion-item -->';

		$parsed = parse_blocks( $markup );
		foreach ( $parsed as $block ) {
			if ( ! empty( $block['blockName'] ) ) {
				return $block;
			}
		}

		return [
			'blockName'    => 'core/accordion-item',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

	/**
	 * @param array    $blocks       Parsed blocks.
	 * @param string[] $find_keys    Keys.
	 * @param string   $new_question New question.
	 * @param string   $answer_html  Answer HTML.
	 * @param int      $replaced     Running count.
	 * @return array
	 */
	private static function walk_blocks( $blocks, $find_keys, $new_question, $answer_html, &$replaced ) {
		foreach ( $blocks as &$block ) {
			$name = $block['blockName'] ?? '';

			if ( Plugin::BLOCK_NAME === $name && ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::walk_items( $block['innerBlocks'], $find_keys, $new_question, $answer_html, $replaced );
				continue;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::walk_blocks( $block['innerBlocks'], $find_keys, $new_question, $answer_html, $replaced );
			}
		}
		unset( $block );

		return $blocks;
	}

	/**
	 * @param array    $blocks       Inner blocks.
	 * @param string[] $find_keys    Keys.
	 * @param string   $new_question New question.
	 * @param string   $answer_html  Answer HTML.
	 * @param int      $replaced     Running count.
	 * @return array
	 */
	private static function walk_items( $blocks, $find_keys, $new_question, $answer_html, &$replaced ) {
		foreach ( $blocks as &$block ) {
			$name = $block['blockName'] ?? '';

			if ( Plugin::is_faq_item_block( $name ) ) {
				$texts = Plugin::extract_item_texts( $block );
				$key   = Plugin::normalize_question_key( $texts['question'] );
				if ( '' !== $key && in_array( $key, $find_keys, true ) ) {
					$block = self::apply_item( $block, $new_question, $answer_html );
					++$replaced;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::walk_items( $block['innerBlocks'], $find_keys, $new_question, $answer_html, $replaced );
			}
		}
		unset( $block );

		return $blocks;
	}

	/**
	 * Write new question + answer into an accordion-item or details block.
	 *
	 * @param array  $block        Item block.
	 * @param string $new_question New question.
	 * @param string $answer_html  Answer HTML.
	 * @return array
	 */
	private static function apply_item( $block, $new_question, $answer_html ) {
		$block = json_decode( wp_json_encode( $block ), true );
		if ( ! is_array( $block ) ) {
			return [];
		}

		$name = $block['blockName'] ?? '';

		if ( Plugin::is_details_block( $name ) ) {
			$block = self::set_details_question( $block, $new_question );
			$block['innerBlocks'] = self::fill_answer_blocks( $block['innerBlocks'] ?? [], $answer_html );
			$block = self::rebuild_inner_content( $block );
			return $block;
		}

		$inner = $block['innerBlocks'] ?? [];
		$heading_i = null;
		$panel_i   = null;

		foreach ( $inner as $i => $child ) {
			$child_name = $child['blockName'] ?? '';
			if ( null === $heading_i && ( 'core/accordion-heading' === $child_name || false !== strpos( (string) $child_name, 'accordion-heading' ) ) ) {
				$heading_i = $i;
			}
			if ( null === $panel_i && ( 'core/accordion-panel' === $child_name || false !== strpos( (string) $child_name, 'accordion-panel' ) ) ) {
				$panel_i = $i;
			}
		}

		if ( null !== $heading_i ) {
			$inner[ $heading_i ] = self::set_heading_question( $inner[ $heading_i ], $new_question );
		} elseif ( isset( $inner[0] ) ) {
			$inner[0] = self::set_heading_question( $inner[0], $new_question );
		}

		if ( null !== $panel_i ) {
			$inner[ $panel_i ]['innerBlocks'] = self::fill_answer_blocks( $inner[ $panel_i ]['innerBlocks'] ?? [], $answer_html );
			$inner[ $panel_i ] = self::rebuild_inner_content( $inner[ $panel_i ] );
		} elseif ( count( $inner ) > 1 ) {
			$head  = $inner[0];
			$rest  = array_slice( $inner, 1 );
			$inner = array_merge( [ $head ], self::fill_answer_blocks( $rest, $answer_html ) );
		}

		$block['innerBlocks'] = $inner;
		$block = self::rebuild_inner_content( $block );

		return $block;
	}

	/**
	 * Put the new answer text into copies of the analog item's answer blocks.
	 *
	 * @param array  $template_blocks Analog panel innerBlocks.
	 * @param string $answer_html     Editor HTML / plain text.
	 * @return array
	 */
	private static function fill_answer_blocks( $template_blocks, $answer_html ) {
		$incoming = self::html_to_blocks( $answer_html );
		$proto    = null;
		foreach ( (array) $template_blocks as $block ) {
			if ( ! empty( $block['blockName'] ) ) {
				$proto = $block;
				break;
			}
		}

		if ( ! $proto ) {
			return $incoming;
		}

		$out = [];
		foreach ( $incoming as $chunk ) {
			$out[] = self::wrap_like( $proto, $chunk );
		}

		return $out;
	}

	/**
	 * Clone analog block markup, swap inner content.
	 *
	 * @param array $proto Analog block.
	 * @param array $chunk Incoming content block.
	 * @return array
	 */
	private static function wrap_like( $proto, $chunk ) {
		$clone = json_decode( wp_json_encode( $proto ), true );
		if ( ! is_array( $clone ) ) {
			return $chunk;
		}

		$payload = trim( (string) ( $chunk['innerHTML'] ?? '' ) );
		$inner   = $payload;
		if ( preg_match( '/<p\b[^>]*>(.*?)<\/p>/is', $payload, $matches ) ) {
			$inner = $matches[1];
		} elseif ( '' === $payload ) {
			$inner = esc_html( trim( wp_strip_all_tags( (string) ( $chunk['attrs']['content'] ?? '' ) ) ) );
		}

		$proto_html = trim( (string) ( $clone['innerHTML'] ?? '' ) );
		if ( preg_match( '/^(<([a-z0-9]+)(\s[^>]*)?>)(.*)(<\/\2>)$/is', $proto_html, $tag ) ) {
			$clone['innerHTML'] = $tag[1] . $inner . $tag[5];
		} elseif ( '' !== $proto_html && preg_match( '/^(<p\b[^>]*>)/i', $proto_html, $open ) ) {
			$clone['innerHTML'] = $open[1] . $inner . '</p>';
		} else {
			$clone['innerHTML'] = $payload ? $payload : '<p>' . $inner . '</p>';
		}

		$clone['innerBlocks']  = [];
		$clone['innerContent'] = [ $clone['innerHTML'] ];

		return $clone;
	}

	/**
	 * Answer HTML as stored on the page after place/push.
	 *
	 * @param int    $post_id  Source post ID.
	 * @param string $question Question.
	 * @return string
	 */
	private static function item_answer_html_from_post( $post_id, $question ) {
		$source = get_post( $post_id );
		if ( ! $source instanceof \WP_Post ) {
			return '';
		}

		$key    = Plugin::normalize_question_key( $question );
		$found  = '';
		$blocks = parse_blocks( $source->post_content );
		self::find_item_answer_html( $blocks, $key, $found );

		return $found;
	}

	/**
	 * @param array  $blocks Parsed blocks.
	 * @param string $key    Normalized question.
	 * @param string $found  Match.
	 */
	private static function find_item_answer_html( $blocks, $key, &$found ) {
		if ( '' !== $found ) {
			return;
		}

		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';
			if ( Plugin::is_faq_item_block( $name ) ) {
				$texts = Plugin::extract_item_texts( $block );
				if ( Plugin::normalize_question_key( $texts['question'] ) === $key ) {
					$found = (string) $texts['answer_html'];
					return;
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::find_item_answer_html( $block['innerBlocks'], $key, $found );
			}
		}
	}

	/**
	 * @param array  $block    Heading block.
	 * @param string $question Question text.
	 * @return array
	 */
	private static function set_heading_question( $block, $question ) {
		$safe = esc_html( $question );
		$block['attrs'] = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];
		$block['attrs']['title'] = $question;

		$html = (string) ( $block['innerHTML'] ?? '' );
		if ( '' !== $html ) {
			$replaced = preg_replace(
				'/(<span[^>]*class="[^"]*wp-block-accordion-heading__toggle-title[^"]*"[^>]*>)(.*?)(<\/span>)/is',
				'${1}' . $safe . '${3}',
				$html,
				1
			);
			if ( is_string( $replaced ) && $replaced !== $html ) {
				$html = $replaced;
			} else {
				$html = self::replace_summary_or_heading_text( $html, $safe );
			}
			$block['innerHTML'] = $html;
		}

		if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as $i => $chunk ) {
				if ( ! is_string( $chunk ) ) {
					continue;
				}
				$block['innerContent'][ $i ] = $html !== '' && 0 === $i ? $html : $chunk;
			}
			if ( isset( $block['innerContent'][0] ) && is_string( $block['innerContent'][0] ) && '' !== $html ) {
				$block['innerContent'][0] = $html;
			}
		}

		return $block;
	}

	/**
	 * @param array  $block    Details block.
	 * @param string $question Question text.
	 * @return array
	 */
	private static function set_details_question( $block, $question ) {
		$safe = esc_html( $question );
		$block['attrs'] = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];
		foreach ( [ 'title', 'summary', 'question' ] as $attr ) {
			if ( isset( $block['attrs'][ $attr ] ) ) {
				$block['attrs'][ $attr ] = $question;
			}
		}
		$block['attrs']['summary'] = $question;

		$html = (string) ( $block['innerHTML'] ?? '' );
		if ( '' !== $html ) {
			$block['innerHTML'] = self::replace_summary_or_heading_text( $html, $safe );
		}

		if ( ! empty( $block['innerContent'][0] ) && is_string( $block['innerContent'][0] ) ) {
			$block['innerContent'][0] = self::replace_summary_or_heading_text( $block['innerContent'][0], $safe );
		}

		return $block;
	}

	/**
	 * @param string $html  Markup.
	 * @param string $safe  Escaped question.
	 * @return string
	 */
	private static function replace_summary_or_heading_text( $html, $safe ) {
		$out = preg_replace( '/(<summary[^>]*>)(.*?)(<\/summary>)/is', '${1}' . $safe . '${3}', $html, 1 );
		if ( is_string( $out ) && $out !== $html ) {
			return $out;
		}

		$out = preg_replace( '/(<h[1-6][^>]*>)(.*?)(<\/h[1-6]>)/is', '${1}' . $safe . '${3}', $html, 1 );
		return is_string( $out ) ? $out : $html;
	}

	/**
	 * Keep wrapper markup, point innerContent slots at new innerBlocks.
	 *
	 * @param array $block Block.
	 * @return array
	 */
	private static function rebuild_inner_content( $block ) {
		$inner_blocks = $block['innerBlocks'] ?? [];
		$content      = $block['innerContent'] ?? [];
		$opener       = '';
		$closer       = '';

		if ( ! empty( $content ) && is_string( $content[0] ) ) {
			$opener = $content[0];
		}

		if ( count( $content ) > 1 ) {
			$last = $content[ count( $content ) - 1 ];
			if ( is_string( $last ) ) {
				$closer = $last;
			}
		}

		if ( '' === $opener && '' === $closer ) {
			$name = (string) ( $block['blockName'] ?? '' );
			if ( false !== strpos( $name, 'accordion-panel' ) ) {
				$opener = '<div class="wp-block-accordion-panel">';
				$closer = '</div>';
			} elseif ( false !== strpos( $name, 'accordion-item' ) ) {
				$opener = '<div class="wp-block-accordion-item">';
				$closer = '</div>';
			}
		}

		$inner_content = [];
		if ( '' !== $opener ) {
			$inner_content[] = $opener;
		}
		foreach ( $inner_blocks as $_unused ) {
			$inner_content[] = null;
		}
		if ( '' !== $closer ) {
			$inner_content[] = $closer;
		}

		$block['innerContent'] = $inner_content;
		$block['innerHTML']    = $opener . $closer;

		return $block;
	}

	/**
	 * @param string $html Answer HTML from the registry editor.
	 * @return array
	 */
	private static function html_to_blocks( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return [ self::paragraph_block( '<p></p>' ) ];
		}

		if ( has_blocks( $html ) ) {
			$blocks = [];
			foreach ( parse_blocks( $html ) as $block ) {
				if ( empty( $block['blockName'] ) ) {
					continue;
				}
				$blocks[] = $block;
			}
			if ( ! empty( $blocks ) ) {
				return $blocks;
			}
		}

		$blocks = [];
		if ( preg_match_all( '/<p\b[^>]*>.*?<\/p>/is', $html, $matches ) ) {
			foreach ( $matches[0] as $paragraph ) {
				$blocks[] = self::paragraph_block( $paragraph );
			}
			return $blocks;
		}

		return [ self::paragraph_block( '<p>' . $html . '</p>' ) ];
	}

	/**
	 * @param string $inner_html Paragraph markup.
	 * @return array
	 */
	private static function paragraph_block( $inner_html ) {
		return [
			'blockName'    => 'core/paragraph',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => $inner_html,
			'innerContent' => [ $inner_html ],
		];
	}

	/**
	 * @param array $payload Notice payload.
	 */
	private static function store_notice( $payload ) {
		set_transient( self::NOTICE_TRANSIENT . get_current_user_id(), $payload, MINUTE_IN_SECONDS );
	}

	public static function render_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->post_type !== Settings::get_post_type() ) {
			return;
		}

		$payload = get_transient( self::NOTICE_TRANSIENT . get_current_user_id() );
		if ( ! is_array( $payload ) ) {
			return;
		}

		delete_transient( self::NOTICE_TRANSIENT . get_current_user_id() );

		if ( ! empty( $payload['error'] ) ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( (string) $payload['error'] )
			);
			return;
		}

		if ( ! empty( $payload['orphan'] ) && (int) ( $payload['updated'] ?? 0 ) < 1 ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'This FAQ is only in the registry. Assign a category or a page to place it on the site. A scan will drop it, because it is not in page content.', '4wp-faq' )
			);
			return;
		}

		$updated = (int) ( $payload['updated'] ?? 0 );
		$missed  = isset( $payload['missed'] ) && is_array( $payload['missed'] ) ? $payload['missed'] : [];

		if ( $updated > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of posts updated */
						_n( 'Updated this FAQ on %d page.', 'Updated this FAQ on %d pages.', $updated, '4wp-faq' ),
						$updated
					)
				)
			);
		}

		if ( ! empty( $missed ) ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s %s</p></div>',
				esc_html__( 'Could not update:', '4wp-faq' ),
				esc_html( implode( ', ', array_map( 'intval', $missed ) ) )
			);
		}
	}
}
