<?php
/**
 * WordPress AI Client (Connectors) wrapper.
 *
 * @package ForWP\AI
 */

namespace ForWP\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate text. Does not save.
 */
class Client {
	/**
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'wp_ai_client_prompt' )
			&& function_exists( 'wp_supports_ai' )
			&& wp_supports_ai();
	}

	/**
	 * @param string $prompt      User prompt.
	 * @param string $system      System instruction.
	 * @param int    $max_tokens  Max tokens.
	 * @return string|\WP_Error
	 */
	public static function generate( $prompt, $system = '', $max_tokens = 180 ) {
		if ( ! self::is_available() ) {
			return new \WP_Error(
				'forwp_ai_unavailable',
				__( 'AI is not available. Configure a connector under Settings → Connectors.', '4wp-ai' ),
				[ 'status' => 400 ]
			);
		}

		$prompt = trim( (string) $prompt );
		if ( '' === $prompt ) {
			return new \WP_Error(
				'forwp_ai_prompt_empty',
				__( 'The prompt is empty.', '4wp-ai' ),
				[ 'status' => 400 ]
			);
		}

		$request = wp_ai_client_prompt( $prompt );
		if ( is_string( $system ) && '' !== $system ) {
			$request = $request->using_system_instruction( $system );
		}

		$result = $request
			->using_max_tokens( (int) $max_tokens )
			->generate_text();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$text = self::sanitize_text( (string) $result );
		if ( '' === $text ) {
			return new \WP_Error(
				'forwp_ai_empty',
				__( 'The model returned empty text.', '4wp-ai' ),
				[ 'status' => 502 ]
			);
		}

		return $text;
	}

	/**
	 * @param string $text Raw model output.
	 * @return string
	 */
	public static function sanitize_text( $text ) {
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = trim( $text );

		return trim( $text, "\"'`" );
	}
}
