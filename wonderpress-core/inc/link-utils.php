<?php
/**
 * Link primitive helpers (URL building from ACF payloads).
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_link_append_query_params' ) ) {
	/**
	 * Append a query string to a URL (supports leading ? or bare key=value pairs).
	 *
	 * @param string $url          Base URL.
	 * @param string $query_params Query string fragment.
	 * @return string
	 */
	function wonder_link_append_query_params( $url, $query_params ) {
		$url          = (string) $url;
		$query_params = trim( (string) $query_params );
		if ( '' === $url || '' === $query_params ) {
			return $url;
		}

		if ( str_starts_with( $query_params, '?' ) ) {
			$query_params = substr( $query_params, 1 );
		}

		parse_str( $query_params, $parsed );
		if ( ! is_array( $parsed ) || ! $parsed ) {
			return $url;
		}

		if ( function_exists( 'add_query_arg' ) ) {
			return add_query_arg( $parsed, $url );
		}

		$fragment = '';
		if ( str_contains( $url, '#' ) ) {
			list( $url, $fragment ) = explode( '#', $url, 2 );
			$fragment               = '#' . $fragment;
		}

		$separator = str_contains( $url, '?' ) ? '&' : '?';
		return $url . $separator . http_build_query( $parsed ) . $fragment;
	}
}

if ( ! function_exists( 'wonder_link_merge_rel' ) ) {
	/**
	 * Merge rel tokens so a new-tab link keeps noopener/noreferrer when the
	 * caller also passes rel (e.g. nofollow).
	 *
	 * @param Boolean $open_in_new_tab Whether the link opens in a new tab.
	 * @param String  $existing_rel    Caller-supplied rel attribute.
	 * @return String
	 */
	function wonder_link_merge_rel( $open_in_new_tab, $existing_rel = '' ) {
		$tokens = array();
		if ( $open_in_new_tab ) {
			$tokens = array( 'noopener', 'noreferrer' );
		}

		$existing_rel = trim( (string) $existing_rel );
		if ( '' !== $existing_rel ) {
			$tokens = array_merge( $tokens, preg_split( '/\s+/', $existing_rel ) );
		}

		$tokens = array_values(
			array_unique(
				array_filter(
					$tokens,
					static function ( $token ) {
						return is_string( $token ) && '' !== $token;
					}
				)
			)
		);

		return implode( ' ', $tokens );
	}
}

if ( ! function_exists( 'wonder_link_url_from_acf' ) ) {
	/**
	 * Build an href from a rich Link ACF payload when url is empty.
	 *
	 * @param Array $acf The ACF array for the link primitive.
	 * @return String
	 */
	function wonder_link_url_from_acf( array $acf ) {
		$type = isset( $acf['type'] ) ? (string) $acf['type'] : '';

		switch ( $type ) {
			case 'email':
				if ( empty( $acf['email'] ) ) {
					break;
				}
				return 'mailto:' . $acf['email'];
			case 'file':
				if ( empty( $acf['file'] ) ) {
					break;
				}
				return function_exists( 'get_permalink' ) ? (string) get_permalink( $acf['file'] ) : '';
			case 'internal':
				if ( empty( $acf['internal_target_obj'] ) ) {
					break;
				}
				return function_exists( 'get_permalink' ) ? (string) get_permalink( $acf['internal_target_obj'] ) : '';
			case 'telephone':
				if ( empty( $acf['telephone'] ) ) {
					break;
				}
				$tel = preg_replace( '/\s+/', '', (string) $acf['telephone'] );
				$tel = ltrim( $tel, '+' );
				return 'tel:+' . $tel;
			case 'url':
				if ( ! empty( $acf['url'] ) ) {
					return (string) $acf['url'];
				}
				break;
		}

		return isset( $acf['url'] ) ? (string) $acf['url'] : '';
	}
}
