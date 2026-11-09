<?php
/**
 * WarriorPlus source.
 *
 * WarriorPlus affiliate links have the form
 *   https://warriorplus.com/o2/a/{offer_code}/{affiliate_id}[/{subid}]
 * WarriorPlus does not publish a public offers API, so offer details are read
 * from the offer's public page (Open Graph / <title> / meta description).
 *
 * @package AffLinkCloaker
 */

defined( 'ABSPATH' ) || exit;

class ALC_WarriorPlus_Source implements ALC_Source {

	const BASE = 'https://warriorplus.com';

	public function get_slug() {
		return 'warriorplus';
	}

	public function get_label() {
		return 'WarriorPlus';
	}

	public function is_configured() {
		return '' !== $this->affiliate_id();
	}

	private function affiliate_id() {
		return (string) ALC_Settings::get( 'wp_affiliate_id' );
	}

	public function parse_input( $input ) {
		$input = trim( (string) $input );
		if ( '' === $input ) {
			return new WP_Error( 'alc_empty', __( 'No offer URL or code given.', 'aff-link-cloaker' ) );
		}

		// Bare offer code.
		if ( preg_match( '/^[a-z0-9]{3,20}$/i', $input ) ) {
			return array(
				'offer_id'      => strtolower( $input ),
				'affiliate_url' => '',
				'fetch_url'     => '',
			);
		}

		$parts = wp_parse_url( $input );
		$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		if ( ! preg_match( '/(^|\.)warriorplus\.com$/', $host ) ) {
			return new WP_Error( 'alc_not_wplus', __( 'That is not a warriorplus.com URL. Paste your WarriorPlus affiliate link or the offer code (put the vendor sales page in the "Sales page URL" field).', 'aff-link-cloaker' ) );
		}

		$path = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';

		// Affiliate link: o2/a/{code}/{affid}[/{subid}]
		if ( preg_match( '#^o2/a/([a-z0-9]+)(?:/([A-Za-z0-9_-]+))?#i', $path, $m ) ) {
			return array(
				'offer_id'      => strtolower( $m[1] ),
				'affiliate_url' => esc_url_raw( $input ),
				'fetch_url'     => esc_url_raw( $input ),
			);
		}

		// Other WarriorPlus offer URLs that carry the code as a path segment.
		if ( preg_match( '#(?:^|/)(?:o2?/)?(?:v|offer|approval|aff-offer|view)/([a-z0-9]+)#i', $path, $m ) ) {
			return array(
				'offer_id'      => strtolower( $m[1] ),
				'affiliate_url' => '',
				'fetch_url'     => esc_url_raw( $input ),
			);
		}

		return new WP_Error( 'alc_no_code', __( 'Could not find a WarriorPlus offer code in that URL.', 'aff-link-cloaker' ) );
	}

	public function fetch_offer( array $parsed, $sales_url = '' ) {
		$url = $sales_url ? $sales_url : $parsed['fetch_url'];
		if ( ! $url ) {
			// Only a code: go through the affiliate link (redirects to the sales page).
			$url = $this->build_affiliate_url( $parsed['offer_id'] );
		}

		$details = array(
			'title'       => '',
			'description' => '',
			'image'       => '',
			'vendor'      => '',
			'offer_url'   => '',
		);

		if ( ! $url ) {
			return new WP_Error( 'alc_no_fetch_url', __( 'Nothing to read: set your WarriorPlus affiliate ID or give a sales page URL.', 'aff-link-cloaker' ) );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 15,
				'redirection'         => 5,
				'limit_response_size' => 1024 * 1024,
				'user-agent'          => 'Mozilla/5.0 (compatible; AffiliateLinkCloaker/' . ALC_VERSION . '; +' . home_url( '/' ) . ')',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 400 ) {
			/* translators: %d: HTTP status code */
			return new WP_Error( 'alc_http', sprintf( __( 'The offer page returned HTTP %d.', 'aff-link-cloaker' ), $code ) );
		}

		$html = (string) wp_remote_retrieve_body( $response );
		$meta = self::extract_meta( $html );

		$details['title']       = $meta['og:title'] ? $meta['og:title'] : $meta['title'];
		$details['description'] = $meta['og:description'] ? $meta['og:description'] : $meta['description'];
		$details['image']       = $meta['og:image'];
		$details['vendor']      = $meta['og:site_name'];
		$details['offer_url']   = $meta['og:url'] ? $meta['og:url'] : $url;

		// Make a relative og:image absolute.
		if ( $details['image'] && ! wp_parse_url( $details['image'], PHP_URL_HOST ) ) {
			$details['image'] = WP_Http::make_absolute_url( $details['image'], $url );
		}

		// Don't store an affiliate link as the "offer page" – it'd leak/duplicate the ID.
		if ( false !== strpos( $details['offer_url'], '/o2/a/' ) ) {
			$details['offer_url'] = $sales_url;
		}

		return $details;
	}

	public function build_affiliate_url( $offer_id, $subid = '' ) {
		$aff = $this->affiliate_id();
		if ( '' === $aff || ! preg_match( '/^[a-z0-9]+$/i', $offer_id ) ) {
			return '';
		}
		$url = self::BASE . '/o2/a/' . rawurlencode( $offer_id ) . '/' . rawurlencode( $aff );
		if ( '' !== $subid ) {
			$url .= '/' . rawurlencode( sanitize_title( $subid ) );
		}
		return $url;
	}

	/**
	 * Pull <title>, meta description and Open Graph tags out of an HTML page.
	 *
	 * @return array<string,string>
	 */
	public static function extract_meta( $html ) {
		$out = array(
			'title'          => '',
			'description'    => '',
			'og:title'       => '',
			'og:description' => '',
			'og:image'       => '',
			'og:site_name'   => '',
			'og:url'         => '',
		);
		if ( '' === trim( $html ) ) {
			return $out;
		}

		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$titles = $doc->getElementsByTagName( 'title' );
		if ( $titles->length ) {
			$out['title'] = $titles->item( 0 )->textContent;
		}

		foreach ( $doc->getElementsByTagName( 'meta' ) as $tag ) {
			$key = strtolower( $tag->getAttribute( 'property' ) ? $tag->getAttribute( 'property' ) : $tag->getAttribute( 'name' ) );
			if ( 'twitter:image' === $key && '' === $out['og:image'] ) {
				$key = 'og:image';
			}
			if ( array_key_exists( $key, $out ) && '' === $out[ $key ] ) {
				$out[ $key ] = $tag->getAttribute( 'content' );
			}
		}

		return array_map(
			static function ( $v ) {
				return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $v, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
			},
			$out
		);
	}
}
