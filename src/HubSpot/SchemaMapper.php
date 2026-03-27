<?php

namespace BBHubspotForms\HubSpot;

final class SchemaMapper {
	public static function map( array $response ): array {
		$form_guid  = isset( $response['id'] ) ? sanitize_text_field( (string) $response['id'] ) : '';
		$portal_id  = isset( $response['portalId'] ) ? sanitize_text_field( (string) $response['portalId'] ) : '';
		$form_name  = isset( $response['name'] ) ? sanitize_text_field( (string) $response['name'] ) : '';

		$fields = array();

		// HubSpot Marketing Forms v3 API uses 'fieldGroups' with nested 'fields'.
		if ( isset( $response['fieldGroups'] ) && is_array( $response['fieldGroups'] ) ) {
			foreach ( $response['fieldGroups'] as $group ) {
				if ( ! isset( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
					continue;
				}
				foreach ( $group['fields'] as $field ) {
					$parsed = self::parse_field( $field );
					if ( $parsed ) {
						$fields[] = $parsed;
					}
				}
			}
		}
		// Legacy API uses 'formFieldGroups'.
		elseif ( isset( $response['formFieldGroups'] ) && is_array( $response['formFieldGroups'] ) ) {
			foreach ( $response['formFieldGroups'] as $group ) {
				if ( ! isset( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
					continue;
				}
				foreach ( $group['fields'] as $field ) {
					$parsed = self::parse_field( $field );
					if ( $parsed ) {
						$fields[] = $parsed;
					}
				}
			}
		}
		// Flat 'fields' array fallback.
		elseif ( isset( $response['fields'] ) && is_array( $response['fields'] ) ) {
			foreach ( $response['fields'] as $field ) {
				$parsed = self::parse_field( $field );
				if ( $parsed ) {
					$fields[] = $parsed;
				}
			}
		}

		return array(
			'portalId'  => $portal_id,
			'formGuid'  => $form_guid,
			'name'      => $form_name,
			'fields'    => $fields,
			'fetchedAt' => time(),
		);
	}

	/**
	 * Parse a single field definition.
	 *
	 * @param array $field Field data from HubSpot.
	 * @return array|null
	 */
	private static function parse_field( array $field ): ?array {
		// v3 API uses 'name' directly or 'property' for the field name.
		$name = '';
		if ( ! empty( $field['name'] ) ) {
			$name = sanitize_key( (string) $field['name'] );
		} elseif ( ! empty( $field['property'] ) ) {
			$name = sanitize_key( (string) $field['property'] );
		}

		if ( empty( $name ) ) {
			return null;
		}

		$label    = isset( $field['label'] ) ? sanitize_text_field( (string) $field['label'] ) : $name;
		$type_raw = isset( $field['fieldType'] ) ? (string) $field['fieldType'] : ( isset( $field['type'] ) ? (string) $field['type'] : 'text' );
		$type     = self::normalize_type( $type_raw );
		$required = ! empty( $field['required'] );
		$options  = self::normalize_options( $field['options'] ?? array() );

		return array(
			'name'     => $name,
			'label'    => $label,
			'type'     => $type,
			'required' => (bool) $required,
			'options'  => $options,
		);
	}

	private static function normalize_type( string $type ): string {
		$type = strtolower( $type );
		$map  = array(
			'email'          => 'email',
			'phone'          => 'tel',
			'tel'            => 'tel',
			'textarea'       => 'textarea',
			'text'           => 'text',
			'number'         => 'text',
			'dropdown'       => 'select',
			'select'         => 'select',
			'radio'          => 'radio',
			'checkbox'       => 'checkbox',
			'booleancheckbox'=> 'checkbox',
		);

		return $map[ $type ] ?? 'text';
	}

	private static function normalize_options( $options ): array {
		if ( ! is_array( $options ) ) {
			return array();
		}
		$normalized = array();
		foreach ( $options as $option ) {
			if ( is_array( $option ) ) {
				$value = $option['value'] ?? ( $option['label'] ?? '' );
			} else {
				$value = $option;
			}
			$value = sanitize_text_field( (string) $value );
			if ( $value !== '' ) {
				$normalized[] = $value;
			}
		}
		return $normalized;
	}
}
