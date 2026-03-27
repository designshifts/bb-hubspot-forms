<?php

namespace BBHubspotForms\Security;

final class Signer {
	private const TOKEN_ALGO = 'sha256';

	public static function issue_token( int $form_id, string $schema_version, int $ttl ): string {
		$issued_at  = time();
		$expires_at = $issued_at + $ttl;
		$payload    = array(
			'form_id'        => $form_id,
			'schema_version' => $schema_version,
			'issued_at'      => $issued_at,
			'expires_at'     => $expires_at,
		);

		$encoded   = wp_json_encode( $payload );
		$signature = self::sign( $encoded );

		return base64_encode( $encoded ) . '.' . $signature;
	}

	public static function verify_token( string $token, int $form_id, string $schema_version ): bool {
		$parts = explode( '.', $token );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		$payload_json = base64_decode( $parts[0], true );
		if ( ! $payload_json ) {
			return false;
		}

		$signature = $parts[1];
		if ( ! hash_equals( self::sign( $payload_json ), $signature ) ) {
			return false;
		}

		$payload = json_decode( $payload_json, true );
		if ( ! is_array( $payload ) ) {
			return false;
		}

		if ( (int) ( $payload['form_id'] ?? 0 ) !== $form_id ) {
			return false;
		}
		if ( (string) ( $payload['schema_version'] ?? '' ) !== $schema_version ) {
			return false;
		}
		if ( time() > (int) ( $payload['expires_at'] ?? 0 ) ) {
			return false;
		}

		return true;
	}

	private static function sign( string $data ): string {
		$secret = wp_salt( 'bb-forms-for-hubspot_form_token' );
		return hash_hmac( self::TOKEN_ALGO, $data, $secret );
	}
}

