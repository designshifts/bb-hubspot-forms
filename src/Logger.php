<?php

namespace BBHubspotForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Logger {
	public static function log( string $message, array $context = array() ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		if ( ! Settings::get( 'debug_enabled', false ) ) {
			return;
		}

		$payload = $context ? wp_json_encode( self::redact( $context ) ) : '';
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( trim( '[BetterBuilds Forms for HubSpot] ' . $message . ' ' . $payload ) );
	}

	private static function redact( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				$clean[ $key ] = self::redact( $item );
			}
			return $clean;
		}
		if ( is_string( $value ) ) {
			return preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted]', $value );
		}
		return $value;
	}
}
