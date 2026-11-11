<?php
/**
 * Creates and updates Affiliate Link posts from offers.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker;

use AffLinkCloaker\Sources\Offer;
use WP_Error;

final class Importer {

	/** Name last received from the network, so syncs only rename links you haven't renamed. */
	private const NETWORK_NAME_META = '_alc_network_name';

	/**
	 * Create or update the link for an offer.
	 *
	 * @param array{status?: string, slug?: string, category?: int, update_only?: bool, custom_name?: bool} $args
	 *        custom_name: the offer's name was typed by the user, so syncs must never replace it.
	 * @return array{post_id: int, created: bool}|WP_Error
	 */
	public static function save( Offer $offer, array $args = array() ): array|WP_Error {
		$args = array_merge(
			array(
				'status'      => 'publish',
				'slug'        => '',
				'category'    => 0,
				'update_only' => false,
				'custom_name' => false,
			),
			$args
		);

		$existing = Links::findExisting( $offer->source, $offer->offerId, $offer->affiliateUrl );

		if ( ! $existing ) {
			if ( $args['update_only'] ) {
				return new WP_Error( 'alc_not_found', __( 'No existing link for this offer.', 'aff-link-cloaker' ) );
			}
			if ( ! Links::isValidDestination( $offer->affiliateUrl ) ) {
				return new WP_Error(
					'alc_no_link',
					'' !== $offer->requestStatus && 'ACTIVE' !== $offer->requestStatus
						/* translators: %s: affiliate status such as REQUEST or PAUSED */
						? sprintf( __( 'No affiliate link yet: your status for this offer is %s.', 'aff-link-cloaker' ), $offer->requestStatus )
						: __( 'Not a valid http(s) affiliate link.', 'aff-link-cloaker' )
				);
			}

			$postarr = array(
				'post_type'   => Links::POST_TYPE,
				'post_title'  => '' !== $offer->name ? $offer->name : $offer->affiliateUrl,
				'post_status' => $args['status'],
			);
			if ( '' !== $args['slug'] ) {
				$postarr['post_name'] = sanitize_title( $args['slug'] );
			}
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}
		} else {
			$post_id = $existing;
			$post    = get_post( $post_id );
			$old     = (string) get_post_meta( $post_id, self::NETWORK_NAME_META, true );
			// Rename only if the current title is one we set (not one the user typed or edited).
			if ( $post && '' !== $offer->name && '' !== $old && $offer->name !== $old && $post->post_title === $old && ! $args['custom_name'] ) {
				wp_update_post( wp_slash( array( 'ID' => $post_id, 'post_title' => $offer->name ) ) );
			}
		}

		// Keep the last good URL when the network stops issuing one (e.g. you were paused):
		// the status meta below lets the redirector send those clicks to the fallback instead.
		if ( Links::isValidDestination( $offer->affiliateUrl ) ) {
			Links::setMeta( $post_id, 'target_url', $offer->affiliateUrl );
		}
		Links::setMeta( $post_id, 'source', $offer->source );
		foreach ( array( 'offer_id' => $offer->offerId, 'request_id' => $offer->requestId, 'vendor' => $offer->vendor ) as $key => $value ) {
			if ( '' !== $value ) {
				Links::setMeta( $post_id, $key, $value );
			}
		}
		if ( '' !== $offer->source && '' !== $offer->offerId && self::fromOfferApi( $offer ) ) {
			Links::setMeta( $post_id, 'request_status', $offer->requestStatus );
			Links::setMeta( $post_id, 'offer_status', $offer->offerStatus );
			Links::setMeta( $post_id, 'synced_at', time() );
		}
		if ( '' !== $offer->name && ! $args['custom_name'] ) {
			update_post_meta( $post_id, self::NETWORK_NAME_META, $offer->name );
		}
		if ( $args['category'] ) {
			wp_set_object_terms( $post_id, (int) $args['category'], Links::TAXONOMY, true );
		}

		return array(
			'post_id' => (int) $post_id,
			'created' => ! $existing,
		);
	}

	/** Whether the offer's network lists offers through an API (so its status and sync time mean something). */
	private static function fromOfferApi( Offer $offer ): bool {
		$source = App::instance()->sources->get( $offer->source );
		return null !== $source && $source->hasOfferApi();
	}

	/**
	 * Import a list of affiliate links (from the Paste tab or the REST API).
	 *
	 * @param list<array{url: string, name: string}>          $rows    Links with optional names.
	 * @param array{status?: string, category?: int}           $options Post status and category.
	 * @return list<array{offer: Offer, status: string, post_id: int, error: ?WP_Error}>
	 *         status is "created", "exists" (post_id is the existing link) or "error".
	 */
	public static function importLinks( array $rows, array $options = array() ): array {
		$results = array();
		foreach ( $rows as $row ) {
			$raw  = trim( (string) ( $row['url'] ?? '' ) );
			$name = trim( (string) ( $row['name'] ?? '' ) );
			$url  = preg_match( '#^https?://#i', $raw ) ? esc_url_raw( $raw, array( 'http', 'https' ) ) : '';

			if ( ! Links::isValidDestination( $url ) ) {
				$results[] = self::result( new Offer( '', '', $name, $raw ), 'error', 0, new WP_Error( 'alc_bad_url', __( 'Not a valid http(s) link.', 'aff-link-cloaker' ) ) );
				continue;
			}
			$offer = App::instance()->sources->offerFromUrl( $url, $name );
			if ( is_wp_error( $offer ) ) {
				$results[] = self::result( new Offer( '', '', $name, $url ), 'error', 0, $offer );
				continue;
			}
			$existing = Links::findExisting( $offer->source, $offer->offerId, $offer->affiliateUrl );
			if ( $existing ) {
				$results[] = self::result( $offer, 'exists', $existing );
				continue;
			}
			$saved = self::save( $offer, $options + array( 'custom_name' => '' !== $name ) );
			$results[] = is_wp_error( $saved )
				? self::result( $offer, 'error', 0, $saved )
				: self::result( $offer, 'created', $saved['post_id'] );
		}
		return $results;
	}

	/** @return array{offer: Offer, status: string, post_id: int, error: ?WP_Error} */
	private static function result( Offer $offer, string $status, int $post_id = 0, ?WP_Error $error = null ): array {
		return compact( 'offer', 'status', 'post_id', 'error' );
	}

	/**
	 * Parse pasted links, one per line: `URL` or `Name | URL` (either order).
	 *
	 * @return list<array{url: string, name: string}>
	 */
	public static function parsePasted( string $text ): array {
		$rows = array();
		foreach ( preg_split( '/\R/', $text ) ?: array() as $line ) {
			$line = trim( $line );
			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}
			$bits = array_map( 'trim', explode( '|', $line, 2 ) );
			$url  = $bits[0];
			$name = $bits[1] ?? '';
			if ( isset( $bits[1] ) && ! preg_match( '#^https?://#i', $bits[0] ) && preg_match( '#^https?://#i', $bits[1] ) ) {
				[ $name, $url ] = $bits;
			}
			$rows[] = array(
				'url'  => $url,
				'name' => $name,
			);
		}
		return $rows;
	}
}
