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
			$fragment = '#' . $fragment;
		}

		$separator = str_contains( $url, '?' ) ? '&' : '?';
		return $url . $separator . http_build_query( $parsed ) . $fragment;
	}
}
