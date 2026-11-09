<?php
/**
 * Contract every affiliate network source implements.
 *
 * @package AffLinkCloaker
 */

defined( 'ABSPATH' ) || exit;

interface ALC_Source {

	/** Machine name stored on links, e.g. "warriorplus". */
	public function get_slug();

	/** Human-readable name. */
	public function get_label();

	/** Whether the account settings needed to build links are present. */
	public function is_configured();

	/**
	 * Parse user input (an offer URL, affiliate link or bare offer code).
	 *
	 * @param string $input
	 * @return array|WP_Error {
	 *     @type string $offer_id      Network offer identifier.
	 *     @type string $affiliate_url Full affiliate link if the input was one, else ''.
	 *     @type string $fetch_url     URL to read offer details from, or ''.
	 * }
	 */
	public function parse_input( $input );

	/**
	 * Read offer details from the source site.
	 *
	 * @param array  $parsed    Result of parse_input().
	 * @param string $sales_url Optional sales/landing page URL to read instead.
	 * @return array|WP_Error {
	 *     @type string $title, $description, $image, $vendor, $offer_url
	 * }
	 */
	public function fetch_offer( array $parsed, $sales_url = '' );

	/**
	 * Build the affiliate destination for an offer using the site's account settings.
	 *
	 * @return string '' when it can't be built (e.g. no affiliate ID configured).
	 */
	public function build_affiliate_url( $offer_id, $subid = '' );
}
