<?php

namespace BBHubspotForms\Spam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Captcha {
	public static function verify( string $provider, string $secret_key, string $token, string $remote_ip = '', array $options = array() ): bool {
		if ( empty( $provider ) ) {
			return true;
		}
		if ( empty( $token ) || empty( $secret_key ) ) {
			return false;
		}

		$verifiers = array(
			'recaptcha_v3' => array( __CLASS__, 'verify_recaptcha' ),
		);
		$verifiers = apply_filters( 'bbhubspot_forms_captcha_verifiers_map', $verifiers );
		// Back-compat legacy hook.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$verifiers = apply_filters( 'bb_hubspot_forms_captcha_verifiers', $verifiers );

		if ( isset( $verifiers[ $provider ] ) && is_callable( $verifiers[ $provider ] ) ) {
			return (bool) call_user_func( $verifiers[ $provider ], $secret_key, $token, $remote_ip, $options );
		}

		return false;
	}

	private static function verify_recaptcha( string $secret, string $token, string $remote_ip, array $options ): bool {
		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'body' => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => $remote_ip,
				),
				'timeout'   => 10,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log_failure( 'reCAPTCHA connection error: ' . $response->get_error_message() );
			return false;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			self::log_failure( 'reCAPTCHA API returned status: ' . $status );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			self::log_failure( 'reCAPTCHA invalid response body' );
			return false;
		}

		if ( empty( $data['success'] ) ) {
			$errors = isset( $data['error-codes'] ) ? implode( ', ', $data['error-codes'] ) : 'unknown';
			self::log_failure( 'reCAPTCHA failed: ' . $errors );
			return false;
		}

		$min_score = isset( $options['min_score'] ) ? (float) $options['min_score'] : 0.5;
		if ( isset( $data['score'] ) && (float) $data['score'] < $min_score ) {
			self::log_failure( 'reCAPTCHA score too low: ' . $data['score'] . ' < ' . $min_score );
			return false;
		}

		if ( ! empty( $options['expected_action'] ) ) {
			$expected_action = (string) $options['expected_action'];
			if ( empty( $data['action'] ) || $data['action'] !== $expected_action ) {
				self::log_failure( 'reCAPTCHA action mismatch: expected ' . $expected_action . ', got ' . ( $data['action'] ?? 'none' ) );
				return false;
			}
		}

		// Skip hostname verification for local development domains.
		if ( ! empty( $options['expected_hostname'] ) && ! empty( $data['hostname'] ) ) {
			$response_hostname = $data['hostname'];
			// Allow local dev domains (localhost, .local, .test, .dev).
			$is_local = preg_match( '/^(localhost|127\.0\.0\.1|.*\.(local|test|dev|localhost))$/i', $response_hostname );
			if ( ! $is_local ) {
			$allowed = is_array( $options['expected_hostname'] ) ? $options['expected_hostname'] : array( $options['expected_hostname'] );
			$allowed = array_filter( array_map( 'strval', $allowed ) );
				if ( ! in_array( $response_hostname, $allowed, true ) ) {
					self::log_failure( 'reCAPTCHA hostname mismatch: ' . $response_hostname . ' not in allowed list' );
				return false;
				}
			}
		}

		return true;
	}

	/**
	 * Log captcha failure for debugging.
	 *
	 * @param string $message Failure message.
	 */
	private static function log_failure( string $message ): void {
		\BBHubspotForms\Logger::log( 'Captcha failure.', array( 'message' => $message ) );
	}
}

