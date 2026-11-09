<?php
/**
 * WarriorPlus.
 *
 * Offers come from the V2 API's affiliate-side request list
 * (GET /affiliate_requests?as=affiliate): one record per offer you've applied to, with your
 * status (ACTIVE = approved) and `promote_url`, the same affiliate link the "Get Link" button
 * on warriorplus.com/affiliate/offers shows.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker\Sources\WarriorPlus;

use AffLinkCloaker\Settings;
use AffLinkCloaker\Sources\Offer;
use AffLinkCloaker\Sources\Source;

final class WarriorPlusSource implements Source {

	public const SLUG       = 'warriorplus';
	private const CACHE_KEY = 'alc_wplus_offers';
	private const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

	public function slug(): string {
		return self::SLUG;
	}

	public function label(): string {
		return 'WarriorPlus';
	}

	public function canListOffers(): bool {
		return '' !== Settings::warriorPlusApiKey();
	}

	public function listOffers( bool $fresh = false ): array|\WP_Error {
		if ( ! $fresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return array_map( array( Offer::class, 'fromArray' ), $cached );
			}
		}

		$client = new ApiClient( Settings::warriorPlusApiKey() );
		$rows   = $client->all( '/affiliate_requests', array( 'as' => 'affiliate' ) );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$offers = array();
		foreach ( $rows as $row ) {
			$offer = self::offerFromRequest( $row );
			if ( $offer ) {
				$offers[] = $offer;
			}
		}

		set_transient( self::CACHE_KEY, array_map( static fn ( Offer $o ) => $o->toArray(), $offers ), self::CACHE_TTL );
		return $offers;
	}

	public static function forgetCache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Map one affiliate request record to an Offer.
	 *
	 * @param array<string, mixed> $row API record.
	 */
	public static function offerFromRequest( array $row ): ?Offer {
		$offer = is_array( $row['offer'] ?? null ) ? $row['offer'] : array();
		if ( empty( $offer['id'] ) ) {
			return null;
		}
		$vendor = (string) ( $offer['vendor_name'] ?? '' );
		return new Offer(
			source: self::SLUG,
			offerId: (string) $offer['id'],
			name: (string) ( $offer['name'] ?? $offer['id'] ),
			affiliateUrl: (string) ( $row['promote_url'] ?? '' ),
			vendor: '' !== $vendor ? $vendor : (string) ( $offer['vendor_username'] ?? '' ),
			requestStatus: strtoupper( (string) ( $row['status'] ?? '' ) ),
			offerStatus: strtolower( (string) ( $offer['status'] ?? '' ) ),
			requestId: (string) ( $row['id'] ?? '' ),
			startDate: (string) ( $offer['start_date'] ?? '' ),
		);
	}

	public function offerFromUrl( string $url, string $name = '' ): ?Offer {
		$parts = wp_parse_url( $url );
		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path  = trim( (string) ( $parts['path'] ?? '' ), '/' );

		if ( ! preg_match( '/(^|\.)warriorplus\.com$/', $host ) || ! preg_match( '#^o2/a/([a-z0-9]+)#i', $path, $m ) ) {
			return null;
		}

		return new Offer(
			source: self::SLUG,
			offerId: '', // A pasted link doesn't say which offer it is; the API sync fills it in.
			name: '' !== $name ? $name : sprintf( 'WarriorPlus offer %s', strtolower( $m[1] ) ),
			affiliateUrl: esc_url_raw( $url ),
		);
	}
}
