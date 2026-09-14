<?php
/**
 * FAQ AI feature: shared field panel + category Description case.
 *
 * @package ForWP\FAQ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'FORWP_AI_LAYER_LOADED' ) ) {
	define( 'FORWP_AI_LAYER_LOADED', true );
	define( 'FORWP_AI_LAYER_DIR', __DIR__ . '/' );
	define( 'FORWP_AI_LAYER_URL', trailingslashit( FORWP_FAQ_PLUGIN_URL . 'ai' ) );

	require_once __DIR__ . '/class-client.php';
	require_once __DIR__ . '/class-panel.php';
}

require_once __DIR__ . '/class-term-description.php';
