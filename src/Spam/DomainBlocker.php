<?php

namespace BBHubspotForms\Spam;

final class DomainBlocker {
	public static function is_blocked( string $email, array $allow_list = array(), array $deny_list = array() ): bool {
		$domain = self::extract_domain( $email );
		if ( ! $domain ) {
			return false;
		}

		$domain = strtolower( trim( $domain ) );

		if ( in_array( $domain, array_map( 'strtolower', $deny_list ), true ) ) {
			return true;
		}
		if ( in_array( $domain, array_map( 'strtolower', $allow_list ), true ) ) {
			return false;
		}

		$blocked = array_merge( self::load_list( 'free-email-domains.json' ), self::load_list( 'disposable-email-domains.json' ) );
		$blocked = array_unique( array_map( 'strtolower', $blocked ) );

		return in_array( $domain, $blocked, true ) || self::matches_subdomain( $domain, $blocked );
	}

	private static function extract_domain( string $email ): string {
		$parts = explode( '@', $email );
		return count( $parts ) === 2 ? $parts[1] : '';
	}

	private static function load_list( string $filename ): array {
		$cache_key = 'bb_hubspot_forms_domains_' . md5( $filename );
		$cached    = wp_cache_get( $cache_key );
		if ( $cached !== false ) {
			return is_array( $cached ) ? $cached : array();
		}

		$path = BBHUBSPOT_FORMS_PLUGIN_DIR . 'data/' . $filename;
		if ( ! file_exists( $path ) ) {
			wp_cache_set( $cache_key, array(), '', HOUR_IN_SECONDS );
			return array();
		}
		$contents = file_get_contents( $path );
		if ( ! $contents ) {
			wp_cache_set( $cache_key, array(), '', HOUR_IN_SECONDS );
			return array();
		}
		$data = json_decode( $contents, true );
		$result = is_array( $data ) ? $data : array();
		wp_cache_set( $cache_key, $result, '', HOUR_IN_SECONDS );
		return $result;
	}

	private static function matches_subdomain( string $domain, array $blocked ): bool {
		foreach ( $blocked as $blocked_domain ) {
			if ( $blocked_domain && str_ends_with( $domain, '.' . $blocked_domain ) ) {
				return true;
			}
		}
		return false;
	}
}

