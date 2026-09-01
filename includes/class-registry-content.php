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

		if ( ! is_array( $used_in_posts ) || empty( $used_in_posts ) ) {
			return [];
		}

		$sources = [];
		$seen    = [];

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
	 * Posts grouped by visible FAQ categories.
	 *
	 * Include limits groups; exclude hides groups. A post may appear in several groups.
	 *
	 * @param int[] $include_term_ids Include filter (empty = all categories).
	 * @param int[] $exclude_term_ids Exclude filter.
	 * @return list<array{term: \WP_Term|null, posts: \WP_Post[]}>
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

			$terms = taxonomy_exists( $taxonomy ) ? get_the_terms( $post->ID, $taxonomy ) : [];

			if ( ! is_array( $terms ) || empty( $terms ) ) {
				if ( empty( $include_term_ids ) ) {
					$uncategorized[] = $post;
				}
				continue;
			}

			foreach ( $terms as $term ) {
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}

				$term_id = (int) $term->term_id;

				if ( ! empty( $include_term_ids ) && ! in_array( $term_id, $include_term_ids, true ) ) {
					continue;
				}

				if ( in_array( $term_id, $exclude_term_ids, true ) ) {
					continue;
				}

				if ( ! isset( $groups[ $term_id ] ) ) {
					$groups[ $term_id ] = [
						'term'  => $term,
						'posts' => [],
					];
				}

				$groups[ $term_id ]['posts'][] = $post;
			}
		}

		uasort(
			$groups,
			static function ( $a, $b ) {
				$name_a = $a['term'] instanceof \WP_Term ? $a['term']->name : '';
				$name_b = $b['term'] instanceof \WP_Term ? $b['term']->name : '';
				return strcasecmp( $name_a, $name_b );
			}
		);

		$grouped = array_values( $groups );

		if ( ! empty( $uncategorized ) && empty( $include_term_ids ) ) {
			$grouped[] = [
				'term'  => null,
				'posts' => $uncategorized,
			];
		}

		return $grouped;
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
	 * Category terms for the sidebar nav (include/exclude).
	 *
	 * @param int[] $include_term_ids Include filter (empty = all).
	 * @param int[] $exclude_term_ids Exclude filter.
	 * @return \WP_Term[]
	 */
	public static function get_nav_terms( $include_term_ids, $exclude_term_ids ) {
		$include_term_ids = self::sanitize_term_ids( $include_term_ids );
		$exclude_term_ids = self::sanitize_term_ids( $exclude_term_ids );
		$taxonomy         = Settings::get_taxonomy();

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		$visible = [];
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$term_id = (int) $term->term_id;

			if ( ! empty( $include_term_ids ) && ! in_array( $term_id, $include_term_ids, true ) ) {
				continue;
			}

			if ( in_array( $term_id, $exclude_term_ids, true ) ) {
				continue;
			}

			$visible[] = $term;
		}

		return $visible;
	}
}
