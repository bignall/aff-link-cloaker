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
		if ( '' !== $offer->source && '' !== $offer->offerId ) {
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
