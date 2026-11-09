<?php
/**
 * An affiliate offer as read from a source, normalised across networks.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker\Sources;

final class Offer {

	/**
	 * @param string $source        Source slug, e.g. "warriorplus".
	 * @param string $offerId       Network offer ID ("" if unknown, e.g. a pasted link).
	 * @param string $name          Offer name.
	 * @param string $affiliateUrl  Your affiliate link ("" if the network hasn't issued one).
	 * @param string $vendor        Vendor name.
	 * @param string $requestStatus Your affiliate status for the offer (ACTIVE, PAUSED, ...), or "".
	 * @param string $offerStatus   Offer state (active / inactive), or "".
	 * @param string $requestId     Network ID of your affiliate request, or "".
	 * @param string $startDate     Launch date, "YYYY-MM-DD HH:MM:SS" or "".
	 */
	public function __construct(
		public readonly string $source,
		public readonly string $offerId,
		public readonly string $name,
		public readonly string $affiliateUrl,
		public readonly string $vendor = '',
		public readonly string $requestStatus = '',
		public readonly string $offerStatus = '',
		public readonly string $requestId = '',
		public readonly string $startDate = '',
	) {}

	/** Whether a cloaked link can be made right now. */
	public function isPromotable(): bool {
		return '' !== $this->affiliateUrl
			&& ( '' === $this->requestStatus || 'ACTIVE' === $this->requestStatus )
			&& 'inactive' !== $this->offerStatus;
	}

	/** Stable key for forms and caches. */
	public function key(): string {
		return '' !== $this->offerId ? $this->offerId : md5( $this->affiliateUrl );
	}

	/** @return array<string, string> */
	public function toArray(): array {
		return get_object_vars( $this );
	}

	/** @param array<string, mixed> $a */
	public static function fromArray( array $a ): self {
		return new self(
			(string) ( $a['source'] ?? '' ),
			(string) ( $a['offerId'] ?? '' ),
			(string) ( $a['name'] ?? '' ),
			(string) ( $a['affiliateUrl'] ?? '' ),
			(string) ( $a['vendor'] ?? '' ),
			(string) ( $a['requestStatus'] ?? '' ),
			(string) ( $a['offerStatus'] ?? '' ),
			(string) ( $a['requestId'] ?? '' ),
			(string) ( $a['startDate'] ?? '' ),
		);
	}
}
