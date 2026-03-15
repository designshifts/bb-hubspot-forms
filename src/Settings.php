<?php

namespace BBHubspotForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {
	public const OPTION_KEY = 'bb_hubspot_forms_settings';
	private const ENCRYPTION_PREFIX = 'enc_v1:';
	private const ENCRYPTION_KEY_ENV = 'BB_HUBSPOT_ENCRYPTION_KEY';

	public static function get( string $key, $default = null ) {
		$constants = self::get_constants();
		if ( array_key_exists( $key, $constants ) && $constants[ $key ] !== null && $constants[ $key ] !== '' ) {
			return $constants[ $key ];
		}

		$options = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $options ) ) {
			return $default;
		}

		if ( ! array_key_exists( $key, $options ) ) {
			return $default;
		}

		if ( $key === 'private_token' ) {
			$stored = is_string( $options[ $key ] ) ? $options[ $key ] : '';
			if ( self::is_encrypted_value( $stored ) ) {
				$decrypted = self::decrypt_from_storage( $stored );
				return $decrypted !== '' ? $decrypted : $default;
			}
			return $stored;
		}

		return $options[ $key ];
	}

	private static function get_constants(): array {
		return array(
			'portal_id'           => defined( 'BBHUBSPOT_FORMS_PORTAL_ID' ) ? BBHUBSPOT_FORMS_PORTAL_ID : null,
			'private_token'       => defined( 'BBHUBSPOT_FORMS_PRIVATE_TOKEN' ) ? BBHUBSPOT_FORMS_PRIVATE_TOKEN : null,
			'captcha_provider'    => defined( 'BBHUBSPOT_FORMS_CAPTCHA_PROVIDER' ) ? BBHUBSPOT_FORMS_CAPTCHA_PROVIDER : null,
			'captcha_site_key'    => defined( 'BBHUBSPOT_FORMS_CAPTCHA_SITE_KEY' ) ? BBHUBSPOT_FORMS_CAPTCHA_SITE_KEY : null,
			'captcha_secret_key'  => defined( 'BBHUBSPOT_FORMS_CAPTCHA_SECRET_KEY' ) ? BBHUBSPOT_FORMS_CAPTCHA_SECRET_KEY : null,
			'block_email_domains' => defined( 'BBHUBSPOT_FORMS_BLOCK_EMAIL_DOMAINS' ) ? BBHUBSPOT_FORMS_BLOCK_EMAIL_DOMAINS : null,
		);
	}

	public static function has_encryption_key(): bool {
		return self::get_encryption_key() !== '';
	}

	public static function get_raw( string $key ): string {
		$options = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $options ) ) {
			return '';
		}
		return isset( $options[ $key ] ) && is_string( $options[ $key ] ) ? $options[ $key ] : '';
	}

	public static function is_encrypted_value( string $value ): bool {
		return $value !== '' && str_starts_with( $value, self::ENCRYPTION_PREFIX );
	}

	public static function encrypt_for_storage( string $plaintext ): string {
		$plaintext = trim( $plaintext );
		if ( $plaintext === '' ) {
			return '';
		}

		$key = self::get_encryption_key();
		if ( $key === '' ) {
			return '';
		}

		return self::encrypt( $plaintext, $key );
	}

	public static function decrypt_from_storage( string $stored ): string {
		if ( ! self::is_encrypted_value( $stored ) ) {
			return $stored;
		}

		$key = self::get_encryption_key();
		if ( $key === '' ) {
			return '';
		}

		return self::decrypt( $stored, $key );
	}

	private static function get_encryption_key(): string {
		$raw_key = '';
		if ( defined( self::ENCRYPTION_KEY_ENV ) && is_string( constant( self::ENCRYPTION_KEY_ENV ) ) ) {
			$raw_key = constant( self::ENCRYPTION_KEY_ENV );
		} elseif ( getenv( self::ENCRYPTION_KEY_ENV ) ) {
			$raw_key = (string) getenv( self::ENCRYPTION_KEY_ENV );
		}
		return trim( $raw_key );
	}

	private static function encrypt( string $plaintext, string $raw_key ): string {
		$key = hash( 'sha256', $raw_key, true );

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			return self::ENCRYPTION_PREFIX . base64_encode( $nonce . $cipher );
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv         = random_bytes( 12 );
			$tag        = '';
			$ciphertext = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( $ciphertext === false ) {
				return '';
			}
			return self::ENCRYPTION_PREFIX . base64_encode( $iv . $tag . $ciphertext );
		}

		return '';
	}

	private static function decrypt( string $stored, string $raw_key ): string {
		if ( ! self::is_encrypted_value( $stored ) ) {
			return '';
		}

		$payload = substr( $stored, strlen( self::ENCRYPTION_PREFIX ) );
		$decoded = base64_decode( $payload, true );
		if ( $decoded === false ) {
			self::maybe_log_decrypt_failure();
			return '';
		}

		$key = hash( 'sha256', $raw_key, true );

		if ( function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$nonce_size = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
			if ( strlen( $decoded ) <= $nonce_size ) {
				self::maybe_log_decrypt_failure();
				return '';
			}
			$nonce  = substr( $decoded, 0, $nonce_size );
			$cipher = substr( $decoded, $nonce_size );
			$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
			if ( $plain === false ) {
				self::maybe_log_decrypt_failure();
				return '';
			}
			return $plain;
		}

		if ( function_exists( 'openssl_decrypt' ) ) {
			$iv_length  = 12;
			$tag_length = 16;
			if ( strlen( $decoded ) <= ( $iv_length + $tag_length ) ) {
				self::maybe_log_decrypt_failure();
				return '';
			}
			$iv         = substr( $decoded, 0, $iv_length );
			$tag        = substr( $decoded, $iv_length, $tag_length );
			$ciphertext = substr( $decoded, $iv_length + $tag_length );
			$plain      = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( $plain === false ) {
				self::maybe_log_decrypt_failure();
				return '';
			}
			return $plain;
		}

		return '';
	}

	private static function maybe_log_decrypt_failure(): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[bb-hubspot-forms] Token decrypt failed.' );
		}
	}
}

