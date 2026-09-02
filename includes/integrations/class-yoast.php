<?php
namespace ForWP\FAQ\Integrations;

use ForWP\FAQ\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merge 4WP FAQ JSON-LD into Yoast SEO schema graph when Yoast is active.
 */
class Yoast {
	/**
	 * Register Yoast schema filters.
	 */
	public static function init() {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			return;
		}

		add_filter( 'wpseo_schema_graph', [ __CLASS__, 'filter_schema_graph' ], 11, 2 );
	}

	/**
	 * Whether Yoast should output FAQ schema for the current request.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return defined( 'WPSEO_VERSION' );
	}

	/**
	 * Append FAQ Question nodes to Yoast's graph (same pattern as Yoast FAQ block).
	 *
	 * @param array<int, array<string, mixed>> $graph   Yoast schema graph pieces.
	 * @param object                             $context Yoast meta tags context.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_schema_graph( $graph, $context ) {
		if ( ! is_array( $graph ) ) {
			return $graph;
		}

		$post = ( isset( $context->post ) && $context->post instanceof \WP_Post ) ? $context->post : null;
		if ( ! $post instanceof \WP_Post || ! is_singular() ) {
			return $graph;
		}

		$entities = Plugin::collect_schema_entities_for_current_view( $post );
		if ( empty( $entities ) ) {
			return $graph;
		}

		$canonical = '';
		if ( isset( $context->canonical ) && is_string( $context->canonical ) ) {
			$canonical = $context->canonical;
		}

		if ( '' === $canonical ) {
			$permalink = get_permalink( $post );
			$canonical = is_string( $permalink ) ? $permalink : '';
		}

		if ( '' === $canonical ) {
			return $graph;
		}

		$main_entity_refs = [];
		$position         = 0;

		foreach ( $entities as $entity ) {
			if ( ! is_array( $entity ) || empty( $entity['name'] ) || empty( $entity['acceptedAnswer'] ) ) {
				continue;
			}

			++$position;
			$anchor      = ! empty( $entity['anchor'] ) ? sanitize_title( (string) $entity['anchor'] ) : 'faq-' . $position;
			$question_id = $canonical . '#' . rawurlencode( $anchor );

			$graph[] = [
				'@type'          => 'Question',
				'@id'            => $question_id,
				'position'       => $position,
				'url'            => $question_id,
				'name'           => $entity['name'],
				'answerCount'    => 1,
				'acceptedAnswer' => $entity['acceptedAnswer'],
			];

			$main_entity_refs[] = [
				'@id' => $question_id,
			];
		}

		if ( empty( $main_entity_refs ) ) {
			return $graph;
		}

		foreach ( $graph as $index => $piece ) {
			if ( ! is_array( $piece ) ) {
				continue;
			}

			$types = isset( $piece['@type'] ) ? (array) $piece['@type'] : [];
			if ( ! in_array( 'WebPage', $types, true ) ) {
				continue;
			}

			if ( ! in_array( 'FAQPage', $types, true ) ) {
				$types[]        = 'FAQPage';
				$piece['@type'] = array_values( array_unique( $types ) );
			}

			$piece['mainEntity'] = $main_entity_refs;
			$graph[ $index ]    = $piece;
			break;
		}

		return $graph;
	}
}
