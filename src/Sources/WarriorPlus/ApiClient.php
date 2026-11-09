<?php
/**
 * Minimal client for the WarriorPlus V2 API (https://warriorplus.com/api/docs).
 *
 * The API is GET-only, authenticates with an `apiKey` header, pages with `starting_after`
 * cursors (max 100 per page) and returns `has_more` as the string "true"/"false".
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker\Sources\WarriorPlus;

use WP_Error;

final class ApiClient {

	public const BASE      = 'https://warriorplus.com/api/v2';
	private const MAX_PAGES = 50;

	public function __construct( private string $apiKey ) {}

	/**
	 * GET one endpoint.
	 *
	 * @param array<string, scalar> $query Query args.
	 * @return array<string, mixed>|WP_Error Decoded body. A 402 (no matching data) is returned as an empty list.
	 */
	public function get( string $path, array $query = array() ): array|WP_Error {
		if ( '' === $this->apiKey ) {
			return new WP_Error( 'alc_wplus_no_key', __( 'No WarriorPlus API key is set.', 'aff-link-cloaker' ) );
		}

		$url      = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $query ) ), self::BASE . $path );
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'apiKey' => $this->apiKey,
					'Accept' => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : array();

		if ( 200 === $code && ! empty( $body['success'] ) ) {
			return $body;
		}
		if ( 402 === $code ) {
			return array( 'data' => array(), 'has_more' => 'false' );
		}

		$message = (string) ( $body['errors']['message'] ?? $body['error'] ?? '' );
		return match ( $code ) {
			401     => new WP_Error( 'alc_wplus_auth', __( 'WarriorPlus rejected the API key. Check it under Affiliate Links → Settings.', 'aff-link-cloaker' ) . ( $message ? " ({$message})" : '' ) ),
			429     => new WP_Error( 'alc_wplus_rate_limited', __( 'WarriorPlus is rate-limiting requests. Try again in a few minutes.', 'aff-link-cloaker' ) ),
			default => new WP_Error(
				'alc_wplus_http',
				/* translators: 1: HTTP status, 2: error message */
				sprintf( __( 'WarriorPlus API error (HTTP %1$d): %2$s', 'aff-link-cloaker' ), $code, $message ? $message : __( 'unexpected response', 'aff-link-cloaker' ) )
			),
		};
	}

	/**
	 * Fetch every page of a list endpoint.
	 *
	 * @param array<string, scalar> $query Query args.
	 * @return list<array<string, mixed>>|WP_Error
	 */
	public function all( string $path, array $query = array() ): array|WP_Error {
		$items          = array();
		$query['limit'] = 100;

		for ( $page = 0; $page < self::MAX_PAGES; $page++ ) {
			$body = $this->get( $path, $query );
			if ( is_wp_error( $body ) ) {
				return $body;
			}
			$data = is_array( $body['data'] ?? null ) ? $body['data'] : array();
			array_push( $items, ...array_values( $data ) );

			$last = end( $data );
			if ( 'true' !== (string) ( $body['has_more'] ?? 'false' ) || ! is_array( $last ) || empty( $last['id'] ) ) {
				break;
			}
			$query['starting_after'] = (string) $last['id'];
		}

		return $items;
	}
}
