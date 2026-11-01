<?php
/**
 * Graph layer files. Required only by forwp_graph_layer_boot() for the winning copy.
 *
 * @package ForWP\Graph
 */

namespace ForWP\Graph;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/contracts/interface-relation-source.php';
require_once __DIR__ . '/contracts/interface-renderer.php';
require_once __DIR__ . '/class-contract.php';
require_once __DIR__ . '/class-analyzer.php';
require_once __DIR__ . '/class-cache.php';
require_once __DIR__ . '/class-source-registry.php';
require_once __DIR__ . '/renderers/class-placeholder-renderer.php';
require_once __DIR__ . '/renderers/class-cytoscape-renderer.php';
require_once __DIR__ . '/class-renderer-registry.php';
require_once __DIR__ . '/class-rest.php';
require_once __DIR__ . '/class-admin-view.php';

Analyzer::init();
Rest::init();
