<?php
/**
 * JVZoo.
 *
 * JVZoo's API (https://api.jvzoo.com/docs/) is vendor-side only: it lists a vendor's own
 * products and their affiliates, but nothing an affiliate is approved to promote and no
 * affiliate links. So JVZoo links are imported by pasting them:
 *
 *     https://www.jvzoo.com/c/<affiliate id>/<product id>   (also jvz1.com … jvz9.com)
 *
 * The product name comes from JVZoo's public Affiliate Information page for the product, which
 * doesn't count as a click on anyone's link. Sub-IDs are passed as JVZoo's tracking ID, `?tid=`,
 * so they show up in JVZoo's affiliate stats.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker\Sources\JVZoo;

use AffLinkCloaker\Sources\Offer;
use AffLinkCloaker\Sources\Source;

final class JVZooSource implements Source {

	public const SLUG = 'jvzoo';

	private const INFO_URL        = 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/%d';
	private const NAME_CACHE_TTL  = WEEK_IN_SECONDS;
	private const MAX_TID_LENGTH  = 100;
	private const MAX_LOOKUPS     = 25; // Per request, so a long paste can't stall the page.

	private int $lookups = 0;

	public function slug(): string {
		return self::SLUG;
	}

	public function label(): string {
		return 'JVZoo';
	}

	public function hasOfferApi(): bool {
		return false;
	}

	public function canListOffers(): bool {
		return false;
	}

	public function listOffers( bool $fresh = false ): array|\WP_Error {
		return new \WP_Error( 'alc_jvzoo_no_listing', __( "JVZoo's API can't list the products you promote. Paste your JVZoo links instead.", 'aff-link-cloaker' ) );
	}

	/**
	 * Parse a JVZoo affiliate link.
	 *
	 * @return array{affiliate: string, product: string}|null
	 */
	public static function parseLink( string $url ): ?array {
		$parts = wp_parse_url( $url );
		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path  = trim( (string) ( $parts['path'] ?? '' ), '/' );

		if ( ! preg_match( '/^(?:www\.)?(?:jvzoo\.com|jvz[1-9]\.com)$/', $host ) ) {
			return null;
		}
		if ( ! preg_match( '#^c/(\d+)/(\d+)$#', $path, $m ) ) {
			return null;
		}
		return array(
			'affiliate' => $m[1],
			'product'   => $m[2],
		);
	}

	public function offerFromUrl( string $url, string $name = '' ): Offer|\WP_Error|null {
		$link = self::parseLink( $url );
		if ( null === $link ) {
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			if ( preg_match( '/^(?:www\.)?(?:jvzoo\.com|jvz[1-9]\.com)$/', $host ) ) {
				return new \WP_Error( 'alc_not_affiliate_link', __( 'This is a JVZoo page, not your affiliate link. JVZoo affiliate links look like https://www.jvzoo.com/c/<your affiliate ID>/<product ID>.', 'aff-link-cloaker' ) );
			}
			return null;
		}

		if ( '' === $name ) {
			$name = $this->productName( (int) $link['product'] );
		}

		return new Offer(
			source: self::SLUG,
			offerId: $link['product'],
			name: '' !== $name ? $name : sprintf( 'JVZoo product %s', $link['product'] ),
			affiliateUrl: esc_url_raw( $url ),
		);
	}

	public function applySubId( string $url, string $subid ): string {
		$tid = substr( (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $subid ), 0, self::MAX_TID_LENGTH );
		if ( '' === $tid || null === self::parseLink( $url ) ) {
			return $url;
		}
		return add_query_arg( 'tid', $tid, remove_query_arg( 'tid', $url ) );
	}

	/**
	 * Product name from JVZoo's public Affiliate Information page, or '' if it can't be read.
	 */
	public function productName( int $product_id ): string {
		if ( $product_id <= 0 ) {
			return '';
		}

		$key    = 'alc_jvz_name_' . $product_id;
		$cached = get_transient( $key );
		if ( is_string( $cached ) ) {
			return $cached;
		}
		if ( $this->lookups >= self::MAX_LOOKUPS ) {
			return '';
		}
		++$this->lookups;

		// wp_remote_get, not wp_safe_remote_get: the host is always JVZoo's, never user input.
		$response = wp_remote_get(
			sprintf( self::INFO_URL, $product_id ),
			array(
				'timeout'             => 8,
				'redirection'         => 2,
				'limit_response_size' => 256 * KB_IN_BYTES,
			)
		);
		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return ''; // Network or server trouble: don't cache, so the next paste tries again.
		}

		$name = self::nameFromInfoPage( (string) wp_remote_retrieve_body( $response ) );
		// A page without a product name (unknown ID) is cached briefly, so it isn't refetched on every paste.
		set_transient( $key, $name, '' !== $name ? self::NAME_CACHE_TTL : HOUR_IN_SECONDS );
		return $name;
	}

	/** "<Product> - Affiliate Information - JVZoo" → "<Product>". */
	public static function nameFromInfoPage( string $html ): string {
		if ( ! preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
			return '';
		}
		$title = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		if ( ! preg_match( '/^(.+?)\s+-\s+Affiliate Information\s+-\s+JVZoo$/i', $title, $n ) ) {
			return ''; // Not a product page (e.g. a login or "not found" page).
		}
		return sanitize_text_field( $n[1] );
	}
}
