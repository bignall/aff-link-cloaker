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

	/** Whether offers can be listed from the network (e.g. an API key is set). */
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
	 * @return Offer|null Null if the URL isn't one of this network's links.
	 */
	public function offerFromUrl( string $url, string $name = '' ): ?Offer;
}
