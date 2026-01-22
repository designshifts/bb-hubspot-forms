<?php

namespace BBHubspotForms;

final class Settings {
	public const OPTION_KEY = 'bb_hubspot_forms_settings';

	public static function get( string $key, $default = null ) {
		$constants = self::get_constants();
		if ( array_key_exists( $key, $constants ) && $constants[ $key ] !== null && $constants[ $key ] !== '' ) {
			return $constants[ $key ];
		}

		$options = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $options ) ) {
			return $default;
		}

		return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
	}

	public static function get_form_ids(): array {
		$value = self::get( 'form_ids', array() );
		if ( ! is_array( $value ) ) {
			return array();
		}

		$form_ids = array();
		foreach ( $value as $entry ) {
			if ( is_array( $entry ) ) {
				$id    = isset( $entry['id'] ) ? sanitize_text_field( $entry['id'] ) : '';
				$label = isset( $entry['label'] ) ? sanitize_text_field( $entry['label'] ) : '';
			} else {
				$id    = is_string( $entry ) ? sanitize_text_field( $entry ) : '';
				$label = $id;
			}

			if ( $id === '' ) {
				continue;
			}

			if ( $label === '' ) {
				$label = $id;
			}

			$form_ids[] = array(
				'id'    => $id,
				'label' => $label,
			);
		}

		return $form_ids;
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
}

