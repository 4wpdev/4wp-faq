<?php
/**
 * Read FAQ registry posts, answers, sources, and category grouping.
 *
 * @package ForWP\FAQ
 */

namespace ForWP\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry content helpers for display blocks.
 */
class Registry_Content {
	/**
	 * Sanitize a list of term IDs.
	 *
	 * @param mixed $value Raw attribute value.
	 * @return int[]
	 */
	public static function sanitize_term_ids( $value ) {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$ids = array_map( 'intval', $value );
		$ids = array_filter( $ids, static function ( $id ) {
			return $id > 0;
		} );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Plain-text / HTML answer for a registry FAQ post.
	 *
	 * @param int $post_id Registry post ID.
	 * @return array{text: string, html: string}
	 */
	public static function get_answer( $post_id ) {
		$post_id   = (int) $post_id;
		$html_list = get_post_meta( $post_id, 'answers_html', true );
		$text_list = get_post_meta( $post_id, 'answers', true );

		$html = '';
		$text = '';

		if ( is_array( $html_list ) && ! empty( $html_list[0] ) && is_string( $html_list[0] ) ) {
			$html = $html_list[0];
		}

		if ( is_array( $text_list ) && ! empty( $text_list[0] ) && is_string( $text_list[0] ) ) {
			$text = $text_list[0];
		}

		return [
			'text' => $text,
			'html' => $html,
		];
	}

	/**
	 * Public pages/posts where this FAQ registry entry appears.
	 *
	 * @param int $post_id Registry post ID.
	 * @return list<array{id: int, title: string, url: string, post_type: string, post_type_label: string}>
	 */
	public static function get_sources( $post_id ) {
		$used_in_posts = get_post_meta( (int) $post_id, 'used_in_posts', true );
		if ( ! is_array( $used_in_posts ) ) {
			$used_in_posts = [];
		}

		$sources = [];
		$seen    = [];
		$seen_url = [];

		foreach ( array_values( $used_in_posts ) as $source_id ) {
			$source_id = (int) $source_id;

			if ( $source_id <= 0 || isset( $seen[ $source_id ] ) ) {
				continue;
			}

			$seen[ $source_id ] = true;

			$source_post = get_post( $source_id );

			if ( ! $source_post instanceof \WP_Post || ! is_post_publicly_viewable( $source_post ) ) {
				continue;
			}

			$url = get_permalink( $source_post );

			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$title = $source_post->post_title;

			if ( '' === $title ) {
				/* translators: %d: post ID */
				$title = sprintf( __( 'Post #%d', '4wp-faq' ), $source_id );
			}

			$type_object = get_post_type_object( $source_post->post_type );
			$type_label  = $type_object && ! empty( $type_object->labels->singular_name )
				? (string) $type_object->labels->singular_name
				: $source_post->post_type;

			$sources[] = [
				'id'              => $source_id,
				'title'           => $title,
				'url'             => $url,
				'post_type'       => $source_post->post_type,
				'post_type_label' => $type_label,
			];
			$seen_url[ $url ] = true;
		}

		$extra = get_post_meta( (int) $post_id, 'source_urls', true );
		if ( is_array( $extra ) ) {
			foreach ( $extra as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$url = isset( $row['url'] ) ? (string) $row['url'] : '';
				if ( '' === $url || isset( $seen_url[ $url ] ) ) {
					continue;
				}
				$seen_url[ $url ] = true;
				$title            = trim( (string) ( $row['title'] ?? '' ) );
				if ( '' === $title ) {
					$title = $url;
				}
				$sources[] = [
					'id'              => 0,
					'title'           => $title,
					'url'             => $url,
					'post_type'       => (string) ( $row['post_type'] ?? '' ),
					'post_type_label' => (string) ( $row['post_type_label'] ?? '' ),
				];
			}
		}

		return $sources;
	}

	/**
	 * Published registry posts, ordered by title.
	 *
	 * @return \WP_Post[]
	 */
	public static function get_all_posts() {
		if ( ! Settings::is_setup_complete() ) {
			return [];
		}

		$post_type = Settings::get_post_type();

		if ( ! post_type_exists( $post_type ) ) {
			return [];
		}

		$posts = get_posts(
			[
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			]
		);

		return is_array( $posts ) ? $posts : [];
	}

	/**
	 * Term IDs assigned to a registry post.
	 *
	 * @param int $post_id Registry post ID.
	 * @return int[]
	 */
	public static function get_post_term_ids( $post_id ) {
		$taxonomy = Settings::get_taxonomy();

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = get_the_terms( (int) $post_id, $taxonomy );

		if ( ! is_array( $terms ) ) {
			return [];
		}

		$ids = [];
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return $ids;
	}

	/**
	 * Whether a post belongs in a flat list given include/exclude term IDs.
	 *
	 * Empty include = all categories. Exclude removes posts that have any of those terms.
	 *
	 * @param int[] $term_ids         Post term IDs.
	 * @param int[] $include_term_ids Include filter (empty = all).
	 * @param int[] $exclude_term_ids Exclude filter.
	 * @return bool
	 */
	public static function post_matches_flat_filter( $term_ids, $include_term_ids, $exclude_term_ids ) {
		$term_ids         = array_map( 'intval', (array) $term_ids );
		$include_term_ids = array_map( 'intval', (array) $include_term_ids );
		$exclude_term_ids = array_map( 'intval', (array) $exclude_term_ids );

		if ( ! empty( $include_term_ids ) && empty( array_intersect( $term_ids, $include_term_ids ) ) ) {
			return false;
		}

		if ( ! empty( $exclude_term_ids ) && ! empty( array_intersect( $term_ids, $exclude_term_ids ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Flat list of matching registry posts.
	 *
	 * @param int[] $include_term_ids Include filter.
	 * @param int[] $exclude_term_ids Exclude filter.
	 * @return \WP_Post[]
	 */
	public static function get_flat_posts( $include_term_ids, $exclude_term_ids ) {
		$include_term_ids = self::sanitize_term_ids( $include_term_ids );
		$exclude_term_ids = self::sanitize_term_ids( $exclude_term_ids );
		$matched          = [];

		foreach ( self::get_all_posts() as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$term_ids = self::get_post_term_ids( $post->ID );

			if ( self::post_matches_flat_filter( $term_ids, $include_term_ids, $exclude_term_ids ) ) {
				$matched[] = $post;
			}
		}

		return $matched;
	}

	/**
	 * Posts grouped by primary FAQ category, in taxonomy hierarchy / term_order.
	 *
	 * Include limits groups; exclude hides groups. Each post appears once (primary term).
	 *
	 * @param int[] $include_term_ids Include filter (empty = all categories).
	 * @param int[] $exclude_term_ids Exclude filter.
	 * @return list<array{term: \WP_Term|null, posts: \WP_Post[], truncated: bool, total: int}>
	 */
	public static function get_grouped_posts( $include_term_ids, $exclude_term_ids ) {
		$include_term_ids = self::sanitize_term_ids( $include_term_ids );
		$exclude_term_ids = self::sanitize_term_ids( $exclude_term_ids );
		$taxonomy         = Settings::get_taxonomy();
		$groups           = [];
		$uncategorized    = [];

		foreach ( self::get_all_posts() as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$term_ids   = self::get_post_term_ids( $post->ID );
			$primary_id = Faq_Terms::get_primary_term_id( $post->ID );

			if ( $primary_id <= 0 || empty( $term_ids ) ) {
				if ( empty( $include_term_ids ) ) {
					$uncategorized[] = $post;
				}
				continue;
			}

			if ( ! empty( $include_term_ids ) && ! in_array( $primary_id, $include_term_ids, true ) ) {
				continue;
			}

			if ( in_array( $primary_id, $exclude_term_ids, true ) ) {
				continue;
			}

			if ( ! isset( $groups[ $primary_id ] ) ) {
				$term = taxonomy_exists( $taxonomy ) ? get_term( $primary_id, $taxonomy ) : null;
				if ( ! $term instanceof \WP_Term ) {
					$uncategorized[] = $post;
					continue;
				}

				$groups[ $primary_id ] = [
					'term'  => $term,
					'posts' => [],
				];
			}

			$groups[ $primary_id ]['posts'][] = $post;
		}

		$ordered = [];
		foreach ( self::get_terms_in_tree_order() as $term ) {
			$id = (int) $term->term_id;
			if ( isset( $groups[ $id ] ) ) {
				$ordered[] = [
					'term'      => $groups[ $id ]['term'],
					'posts'     => $groups[ $id ]['posts'],
					'truncated' => false,
					'total'     => count( $groups[ $id ]['posts'] ),
				];
				unset( $groups[ $id ] );
			}
		}

		foreach ( $groups as $group ) {
			$ordered[] = [
				'term'      => $group['term'],
				'posts'     => $group['posts'],
				'truncated' => false,
				'total'     => count( $group['posts'] ),
			];
		}

		if ( ! empty( $uncategorized ) && empty( $include_term_ids ) ) {
			$ordered[] = [
				'term'      => null,
				'posts'     => $uncategorized,
				'truncated' => false,
				'total'     => count( $uncategorized ),
			];
		}

		return $ordered;
	}

	/**
	 * Grouped posts for the current request: one category (full) or All (preview limit).
	 *
	 * @param int[]  $include_term_ids Include filter.
	 * @param int[]  $exclude_term_ids Exclude filter.
	 * @param string $active_slug      Active category slug (empty = All).
	 * @param int    $preview_limit    Per-category cap on All view (0 = no cap).
	 * @return list<array{term: \WP_Term|null, posts: \WP_Post[], truncated: bool, total: int}>
	 */
	public static function get_visible_groups( $include_term_ids, $exclude_term_ids, $active_slug = '', $preview_limit = 0 ) {
		$flat = [];
		self::flatten_group_tree(
			self::get_visible_group_tree( $include_term_ids, $exclude_term_ids, $active_slug, $preview_limit ),
			$flat
		);

		return $flat;
	}

	/**
	 * Nested groups: parent wraps child categories. Parent URL includes descendants.
	 *
	 * @param int[]  $include_term_ids Include filter.
	 * @param int[]  $exclude_term_ids Exclude filter.
	 * @param string $active_slug      Active category slug (empty = All).
	 * @param int    $preview_limit    Per-leaf cap on All view.
	 * @return list<array{term: \WP_Term|null, posts: \WP_Post[], truncated: bool, total: int, children: array}>
	 */
	public static function get_visible_group_tree( $include_term_ids, $exclude_term_ids, $active_slug = '', $preview_limit = 0 ) {
		$include_term_ids = self::sanitize_term_ids( $include_term_ids );
		$exclude_term_ids = self::sanitize_term_ids( $exclude_term_ids );
		$active_slug      = sanitize_title( (string) $active_slug );
		$preview_limit    = (int) $preview_limit;
		$by_id            = [];
		$uncategorized    = null;

		foreach ( self::get_grouped_posts( $include_term_ids, $exclude_term_ids ) as $group ) {
			$term = $group['term'] ?? null;
			if ( ! $term instanceof \WP_Term ) {
				$uncategorized = $group;
				continue;
			}
			$by_id[ (int) $term->term_id ] = $group;
		}

		$attach = static function ( $nodes ) use ( &$attach, $by_id ) {
			$out = [];
			foreach ( $nodes as $node ) {
				$term = $node['term'] ?? null;
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}

				$id    = (int) $term->term_id;
				$group = $by_id[ $id ] ?? [
					'term'      => $term,
					'posts'     => [],
					'truncated' => false,
					'total'     => 0,
				];
				$group['children'] = $attach( isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : [] );

				if ( empty( $group['posts'] ) && empty( $group['children'] ) ) {
					continue;
				}

				$out[] = $group;
			}

			return $out;
		};

		$tree = $attach( self::get_nav_tree( $include_term_ids, $exclude_term_ids ) );

		if ( is_array( $uncategorized ) ) {
			$uncategorized['children'] = [];
			$tree[]                    = $uncategorized;
		}

		if ( '' !== $active_slug ) {
			$tree = self::filter_group_tree_to_slug( $tree, $active_slug );
		} elseif ( $preview_limit > 0 ) {
			$tree = self::apply_preview_limit_to_tree( $tree, $preview_limit );
		}

		return $tree;
	}

	/**
	 * Keep the matching node and its descendants (parent URL lists child groups).
	 *
	 * @param array  $nodes Nested groups.
	 * @param string $slug  Active slug.
	 * @return array
	 */
	private static function filter_group_tree_to_slug( $nodes, $slug ) {
		foreach ( $nodes as $node ) {
			$term = $node['term'] ?? null;
			if ( $term instanceof \WP_Term && $term->slug === $slug ) {
				return [ $node ];
			}

			$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : [];
			$found    = self::filter_group_tree_to_slug( $children, $slug );
			if ( ! empty( $found ) ) {
				return $found;
			}
		}

		return [];
	}

	/**
	 * @param array $nodes Nested groups.
	 * @param int   $limit Per-group cap.
	 * @return array
	 */
	private static function apply_preview_limit_to_tree( $nodes, $limit ) {
		$out = [];
		foreach ( $nodes as $node ) {
			$posts = isset( $node['posts'] ) && is_array( $node['posts'] ) ? $node['posts'] : [];
			$total = count( $posts );
			if ( $limit > 0 && $total > $limit ) {
				$node['posts']     = array_slice( $posts, 0, $limit );
				$node['truncated'] = true;
				$node['total']     = $total;
			}
			$node['children'] = self::apply_preview_limit_to_tree(
				isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : [],
				$limit
			);
			$out[]            = $node;
		}

		return $out;
	}

	/**
	 * @param array $nodes Nested groups.
	 * @param array $flat  Output flat list.
	 */
	private static function flatten_group_tree( $nodes, array &$flat ) {
		foreach ( $nodes as $node ) {
			$flat[] = $node;
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				self::flatten_group_tree( $node['children'], $flat );
			}
		}
	}

	/**
	 * Count questions that the list will actually render.
	 *
	 * @param int[]  $include_term_ids Include filter.
	 * @param int[]  $exclude_term_ids Exclude filter.
	 * @param string $active_slug      Active category slug.
	 * @param int    $preview_limit    Per-category cap on All view.
	 * @param string $layout           grouped|flat.
	 * @return int
	 */
	public static function count_visible_posts( $include_term_ids, $exclude_term_ids, $active_slug = '', $preview_limit = 0, $layout = 'grouped' ) {
		if ( 'flat' === $layout ) {
			$posts = self::get_flat_posts( $include_term_ids, $exclude_term_ids );
			if ( '' !== $active_slug ) {
				$posts = array_values(
					array_filter(
						$posts,
						static function ( $post ) use ( $active_slug ) {
							return $post instanceof \WP_Post && in_array( $active_slug, self::get_post_term_slugs( $post->ID ), true );
						}
					)
				);
			} elseif ( $preview_limit > 0 ) {
				$posts = array_slice( $posts, 0, $preview_limit );
			}

			return count( $posts );
		}

		$count = 0;
		foreach ( self::get_visible_groups( $include_term_ids, $exclude_term_ids, $active_slug, $preview_limit ) as $group ) {
			$count += isset( $group['posts'] ) ? count( $group['posts'] ) : 0;
		}

		return $count;
	}

	/**
	 * Registry posts that appear on the current list view (for JSON-LD).
	 *
	 * @param int[]  $include_term_ids Include filter.
	 * @param int[]  $exclude_term_ids Exclude filter.
	 * @param string $active_slug      Active category slug.
	 * @param int    $preview_limit    Per-category cap on All view.
	 * @param string $layout           grouped|flat.
	 * @return \WP_Post[]
	 */
	public static function get_visible_posts( $include_term_ids, $exclude_term_ids, $active_slug = '', $preview_limit = 0, $layout = 'grouped' ) {
		if ( 'flat' === $layout ) {
			$posts = self::get_flat_posts( $include_term_ids, $exclude_term_ids );
			if ( '' !== $active_slug ) {
				$posts = array_values(
					array_filter(
						$posts,
						static function ( $post ) use ( $active_slug ) {
							return $post instanceof \WP_Post && in_array( $active_slug, self::get_post_term_slugs( $post->ID ), true );
						}
					)
				);
			} elseif ( $preview_limit > 0 ) {
				$posts = array_slice( $posts, 0, $preview_limit );
			}

			return $posts;
		}

		$posts = [];
		$seen  = [];
		foreach ( self::get_visible_groups( $include_term_ids, $exclude_term_ids, $active_slug, $preview_limit ) as $group ) {
			foreach ( $group['posts'] as $post ) {
				if ( ! $post instanceof \WP_Post || isset( $seen[ $post->ID ] ) ) {
					continue;
				}
				$seen[ $post->ID ] = true;
				$posts[]           = $post;
			}
		}

		return $posts;
	}

	/**
	 * Category terms for the sidebar nav (include/exclude), tree order.
	 *
	 * @param int[] $include_term_ids Include filter (empty = all).
	 * @param int[] $exclude_term_ids Exclude filter.
	 * @return \WP_Term[]
	 */
	public static function get_nav_terms( $include_term_ids, $exclude_term_ids ) {
		$tree  = self::get_nav_tree( $include_term_ids, $exclude_term_ids );
		$flat  = [];
		self::flatten_nav_tree( $tree, $flat );

		return $flat;
	}

	/**
	 * Nested nav nodes: parent → children in term_order.
	 *
	 * @param int[] $include_term_ids Include filter.
	 * @param int[] $exclude_term_ids Exclude filter.
	 * @return list<array{term: \WP_Term, children: array}>
	 */
	public static function get_nav_tree( $include_term_ids, $exclude_term_ids ) {
		$include_term_ids = self::sanitize_term_ids( $include_term_ids );
		$exclude_term_ids = self::sanitize_term_ids( $exclude_term_ids );
		$visible_ids      = [];

		foreach ( self::get_terms_in_tree_order() as $term ) {
			$term_id = (int) $term->term_id;
			if ( ! empty( $include_term_ids ) && ! in_array( $term_id, $include_term_ids, true ) ) {
				continue;
			}
			if ( in_array( $term_id, $exclude_term_ids, true ) ) {
				continue;
			}
			$visible_ids[ $term_id ] = $term;
		}

		return self::annotate_nav_counts( self::prune_empty_nav_nodes( self::build_nav_tree( $visible_ids ) ) );
	}

	/**
	 * Full category tree in saved parent → child / term_order.
	 *
	 * @return list<array{term: \WP_Term, children: array}>
	 */
	public static function get_category_tree() {
		$by_id = [];
		foreach ( self::get_terms_in_tree_order() as $term ) {
			$by_id[ (int) $term->term_id ] = $term;
		}

		if ( empty( $by_id ) ) {
			return [];
		}

		return self::build_nav_tree( $by_id );
	}

	/**
	 * Full category tree with parent counts including all descendants.
	 *
	 * @return list<array{term: \WP_Term, children: array, inclusive_count: int}>
	 */
	public static function get_category_tree_with_counts() {
		return self::annotate_nav_counts( self::get_category_tree() );
	}

	/**
	 * Parent count = own questions + all descendants (unique via primary grouping).
	 *
	 * @param list<array{term: \WP_Term, children: array}> $nodes Tree.
	 * @return list<array{term: \WP_Term, children: array, inclusive_count: int}>
	 */
	private static function annotate_nav_counts( $nodes ) {
		$out = [];
		foreach ( $nodes as $node ) {
			$children = [];
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$children = self::annotate_nav_counts( $node['children'] );
			}

			$own = 0;
			$term = $node['term'] ?? null;
			if ( $term instanceof \WP_Term ) {
				$own = (int) $term->count;
			}

			$child_sum = 0;
			foreach ( $children as $child ) {
				$child_sum += (int) ( $child['inclusive_count'] ?? 0 );
			}

			$node['children']         = $children;
			$node['inclusive_count']  = $own + $child_sum;
			$out[]                    = $node;
		}

		return $out;
	}

	/**
	 * FAQ terms sorted for tree walk (term_order, then name).
	 *
	 * @return \WP_Term[]
	 */
	public static function get_terms_in_tree_order() {
		$taxonomy = Settings::get_taxonomy();
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'term_order',
				'order'      => 'ASC',
			]
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		$valid = [];
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$valid[] = $term;
			}
		}

		usort(
			$valid,
			static function ( $a, $b ) {
				$order_a = (int) $a->term_order;
				$order_b = (int) $b->term_order;
				if ( $order_a !== $order_b ) {
					return $order_a <=> $order_b;
				}

				return strcasecmp( $a->name, $b->name );
			}
		);

		return $valid;
	}

	/**
	 * @param array<int, \WP_Term> $by_id Visible terms keyed by ID.
	 * @return list<array{term: \WP_Term, children: array}>
	 */
	private static function build_nav_tree( array $by_id ) {
		$children = [];
		foreach ( $by_id as $term ) {
			$parent = (int) $term->parent;
			if ( ! isset( $children[ $parent ] ) ) {
				$children[ $parent ] = [];
			}
			$children[ $parent ][] = $term;
		}

		$build = static function ( $parent_id ) use ( &$build, $children ) {
			$nodes = [];
			if ( empty( $children[ $parent_id ] ) ) {
				return $nodes;
			}

			foreach ( $children[ $parent_id ] as $term ) {
				$nodes[] = [
					'term'     => $term,
					'children' => $build( (int) $term->term_id ),
				];
			}

			return $nodes;
		};

		$roots = $build( 0 );

		foreach ( $by_id as $term ) {
			$parent = (int) $term->parent;
			if ( $parent > 0 && ! isset( $by_id[ $parent ] ) ) {
				$already = false;
				foreach ( $roots as $root ) {
					if ( (int) $root['term']->term_id === (int) $term->term_id ) {
						$already = true;
						break;
					}
				}
				if ( ! $already ) {
					$roots[] = [
						'term'     => $term,
						'children' => $build( (int) $term->term_id ),
					];
				}
			}
		}

		return $roots;
	}

	/**
	 * Drop categories with no questions and no remaining children.
	 *
	 * @param list<array{term: \WP_Term, children: array}> $nodes Tree.
	 * @return list<array{term: \WP_Term, children: array}>
	 */
	private static function prune_empty_nav_nodes( $nodes ) {
		$kept = [];

		foreach ( $nodes as $node ) {
			$children = [];
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$children = self::prune_empty_nav_nodes( $node['children'] );
			}

			$term  = $node['term'] ?? null;
			$count = $term instanceof \WP_Term ? (int) $term->count : 0;
			if ( $count < 1 && empty( $children ) ) {
				continue;
			}

			$node['children'] = $children;
			$kept[]           = $node;
		}

		return $kept;
	}

	/**
	 * @param array $nodes Tree nodes.
	 * @param \WP_Term[] $flat Output.
	 */
	private static function flatten_nav_tree( $nodes, array &$flat ) {
		foreach ( $nodes as $node ) {
			if ( isset( $node['term'] ) && $node['term'] instanceof \WP_Term ) {
				$flat[] = $node['term'];
			}
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				self::flatten_nav_tree( $node['children'], $flat );
			}
		}
	}

	/**
	 * Term slugs assigned to a registry post.
	 *
	 * @param int $post_id Registry post ID.
	 * @return string[]
	 */
	public static function get_post_term_slugs( $post_id ) {
		$taxonomy = Settings::get_taxonomy();

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = get_the_terms( (int) $post_id, $taxonomy );

		if ( ! is_array( $terms ) ) {
			return [];
		}

		$slugs = [];
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term && '' !== $term->slug ) {
				$slugs[] = $term->slug;
			}
		}

		return $slugs;
	}

	/**
	 * Term slugs on a post plus ancestor slugs (parent URL matches child FAQs).
	 *
	 * @param int $post_id Registry post ID.
	 * @return string[]
	 */
	public static function get_post_term_slugs_with_ancestors( $post_id ) {
		$taxonomy = Settings::get_taxonomy();
		$slugs    = self::get_post_term_slugs( $post_id );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return $slugs;
		}

		$extra = [];
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			foreach ( get_ancestors( (int) $term->term_id, $taxonomy, 'taxonomy' ) as $ancestor_id ) {
				$ancestor = get_term( (int) $ancestor_id, $taxonomy );
				if ( $ancestor instanceof \WP_Term && '' !== $ancestor->slug ) {
					$extra[] = $ancestor->slug;
				}
			}
		}

		return array_values( array_unique( array_merge( $slugs, $extra ) ) );
	}
}
