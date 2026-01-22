<?php

namespace BBHubspotForms\Spam;

final class Captcha {
	public static function verify( string $provider, string $secret_key, string $token, string $remote_ip = '', array $options = array() ): bool {
		if ( empty( $provider ) ) {
			return true;
		}
		if ( empty( $token ) || empty( $secret_key ) ) {
			return false;
		}

		switch ( $provider ) {
			case 'recaptcha_v3':
				return self::verify_recaptcha( $secret_key, $token, $remote_ip, $options );
			case 'turnstile':
				return self::verify_turnstile( $secret_key, $token, $remote_ip );
			case 'hcaptcha':
				return self::verify_hcaptcha( $secret_key, $token, $remote_ip );
			default:
				return false;
		}
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
			return false;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( empty( $data['success'] ) ) {
			return false;
		}

		$min_score = isset( $options['min_score'] ) ? (float) $options['min_score'] : 0.5;
		if ( isset( $data['score'] ) && (float) $data['score'] < $min_score ) {
			return false;
		}

		if ( ! empty( $options['expected_action'] ) ) {
			$expected_action = (string) $options['expected_action'];
			if ( empty( $data['action'] ) || $data['action'] !== $expected_action ) {
				return false;
			}
		}

		if ( ! empty( $options['expected_hostname'] ) ) {
			$allowed = is_array( $options['expected_hostname'] ) ? $options['expected_hostname'] : array( $options['expected_hostname'] );
			$allowed = array_filter( array_map( 'strval', $allowed ) );
			if ( empty( $data['hostname'] ) || ! in_array( $data['hostname'], $allowed, true ) ) {
				return false;
			}
		}

		return true;
	}

	private static function verify_turnstile( string $secret, string $token, string $remote_ip ): bool {
		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
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

		return self::is_success_response( $response );
	}

	private static function verify_hcaptcha( string $secret, string $token, string $remote_ip ): bool {
		$response = wp_remote_post(
			'https://hcaptcha.com/siteverify',
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

		return self::is_success_response( $response );
	}

	private static function is_success_response( $response ): bool {
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return false;
		}

		return ! empty( $data['success'] );
	}
}

