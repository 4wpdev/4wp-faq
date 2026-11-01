<?php
/**
 * Admin graph view: assets + mount point. Domain-agnostic.
 *
 * The owning plugin registers the menu page, passes texts in its own text domain
 * and calls Admin_View::enqueue() / Admin_View::render().
 *
 * @package ForWP\Graph
 */

namespace ForWP\Graph;

use ForWP\Graph\Contracts\Relation_Source_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_View {

	public const SCRIPT_HANDLE = 'forwp-graph-view';
	public const STYLE_HANDLE  = 'forwp-graph-view';

	/**
	 * Enqueue the view for one source.
	 *
	 * @param Relation_Source_Interface $source Source.
	 * @param array<string, mixed>      $config i18n (texts), groupArg (REST arg that filters by a group id), extra keys.
	 * @return bool False when no renderer is available.
	 */
	public static function enqueue( Relation_Source_Interface $source, array $config = array() ): bool {
		$renderer = Renderer_Registry::active( $source->get_slug() );
		if ( ! $renderer ) {
			return false;
		}

		$renderer->register_assets();

		wp_enqueue_style(
			self::STYLE_HANDLE,
			FORWP_GRAPH_LAYER_URL . 'assets/graph-view.css',
			array(),
			FORWP_GRAPH_LAYER_VERSION
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			FORWP_GRAPH_LAYER_URL . 'assets/graph-view.js',
			array( $renderer->get_script_handle() ),
			FORWP_GRAPH_LAYER_VERSION,
			true
		);

		$data = array_merge(
			array(
				'source'   => $source->get_slug(),
				'renderer' => $renderer->get_slug(),
				'endpoint' => esc_url_raw( rest_url( trim( $source->get_rest_namespace(), '/' ) . '/graph' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'groupArg' => '',
				'i18n'     => array(),
			),
			$config
		);

		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.forwpGraphConfigs = window.forwpGraphConfigs || {}; window.forwpGraphConfigs[' . wp_json_encode( $source->get_slug() ) . '] = ' . wp_json_encode( $data ) . ';',
			'before'
		);

		return true;
	}

	/**
	 * Mount point. JS builds the UI inside it.
	 *
	 * @param string $source_slug Source slug.
	 * @param string $no_js       Message for browsers without JS (plugin text domain).
	 */
	public static function render( string $source_slug, string $no_js = '' ): void {
		echo '<div class="forwp-graph-app" data-forwp-graph-source="' . esc_attr( $source_slug ) . '">';
		if ( '' !== $no_js ) {
			echo '<noscript><p>' . esc_html( $no_js ) . '</p></noscript>';
		}
		echo '</div>';
	}
}
