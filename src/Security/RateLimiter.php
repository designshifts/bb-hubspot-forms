<?php

namespace BBHubspotForms\Security;

final class RateLimiter {
	public static function check( string $ip, int $limit_per_minute = 50, int $burst = 10, int $burst_window = 15 ): bool {
		$rate_key  = 'bb-hubspot-forms_rate_' . md5( $ip );
		$burst_key = 'bb-hubspot-forms_burst_' . md5( $ip );

		$burst_count = get_transient( $burst_key );
		if ( $burst_count === false ) {
			set_transient( $burst_key, 1, $burst_window );
		} elseif ( $burst_count >= $burst ) {
			return false;
		} else {
			set_transient( $burst_key, $burst_count + 1, $burst_window );
		}

		$current = get_transient( $rate_key );
		if ( $current === false ) {
			set_transient( $rate_key, 1, 60 );
			return true;
		}

		if ( $current >= $limit_per_minute ) {
			return false;
		}

		set_transient( $rate_key, $current + 1, 60 );
		return true;
	}
}

