<?php
/**
 * Admin prompt panel assets.
 *
 * @package ForWP\AI
 */

namespace ForWP\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue the shared field panel.
 */
class Panel {
	/**
	 * @param array $config {
	 *     @type string $field          CSS selector for the target field.
	 *     @type string $prompt_path    REST GET path that returns { prompt }.
	 *     @type string $generate_path  REST POST path that accepts { prompt } and returns { text }.
	 *     @type array  $i18n           UI strings.
	 *     @type string $storage_key    sessionStorage key for panel position.
	 * }
	 * @return bool
	 */
	public static function enqueue( $config ) {
		if ( ! defined( 'FORWP_AI_LAYER_DIR' ) || ! defined( 'FORWP_AI_LAYER_URL' ) || '' === FORWP_AI_LAYER_URL ) {
			return false;
		}

		$script = FORWP_AI_LAYER_DIR . 'assets/panel.js';
		$style  = FORWP_AI_LAYER_DIR . 'assets/panel.css';
		if ( ! is_readable( $script ) ) {
			return false;
		}

		$field         = isset( $config['field'] ) ? (string) $config['field'] : '';
		$prompt_path   = isset( $config['prompt_path'] ) ? (string) $config['prompt_path'] : '';
		$generate_path = isset( $config['generate_path'] ) ? (string) $config['generate_path'] : '';
		if ( '' === $field || '' === $prompt_path || '' === $generate_path ) {
			return false;
		}

		if ( is_readable( $style ) ) {
			wp_enqueue_style(
				'forwp-ai-field-panel',
				FORWP_AI_LAYER_URL . 'assets/panel.css',
				[ 'dashicons' ],
				(string) filemtime( $style )
			);
		}

		wp_enqueue_script(
			'forwp-ai-field-panel',
			FORWP_AI_LAYER_URL . 'assets/panel.js',
			[ 'wp-api-fetch' ],
			(string) filemtime( $script ),
			true
		);

		wp_localize_script(
			'forwp-ai-field-panel',
			'forwpAiField',
			[
				'field'         => $field,
				'promptPath'    => $prompt_path,
				'generatePath'  => $generate_path,
				'storageKey'    => isset( $config['storage_key'] ) ? (string) $config['storage_key'] : 'forwpAiPanelPos',
				'i18n'          => isset( $config['i18n'] ) && is_array( $config['i18n'] ) ? $config['i18n'] : [],
			]
		);

		return true;
	}
}
