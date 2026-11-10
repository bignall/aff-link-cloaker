<?php
/**
 * Contract for an affiliate network.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker\Sources;

interface Source {

	/** Machine name stored on links, e.g. "warriorplus". */
	public function slug(): string;

	/** Human-readable name. */
	public function label(): string;

	/** Whether the network has an API that lists your offers (gets its own Import tab). */
	public function hasOfferApi(): bool;

	/** Whether offers can be listed right now (the API exists and e.g. an API key is set). */
	public function canListOffers(): bool;

	/**
	 * Every offer you're an affiliate for, in any status.
	 *
	 * @param bool $fresh Bypass any cache.
	 * @return list<Offer>|\WP_Error
	 */
	public function listOffers( bool $fresh = false ): array|\WP_Error;

	/**
	 * Recognise a pasted affiliate link as belonging to this network.
	 *
	 * @return Offer|\WP_Error|null An Offer for this network's affiliate links; a WP_Error for a
	 *                               URL on the network's site that isn't an affiliate link (it would
	 *                               earn nothing); null if the URL has nothing to do with the network.
	 */
	public function offerFromUrl( string $url, string $name = '' ): Offer|\WP_Error|null;

	/**
	 * Pass a sub-ID from the cloaked URL (/go/slug/<subid>/) to the network, if it supports one.
	 *
	 * @param string $url   The link's affiliate URL.
	 * @param string $subid Sub-ID, already sanitized; never empty.
	 * @return string The URL to redirect to.
	 */
	public function applySubId( string $url, string $subid ): string;
}
