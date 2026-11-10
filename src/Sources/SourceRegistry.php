<?php
/**
 * Available affiliate networks.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker\Sources;

final class SourceRegistry {

	/** @var array<string, Source> */
	private array $sources = array();

	public function register( Source $source ): void {
		$this->sources[ $source->slug() ] = $source;
	}

	public function get( string $slug ): ?Source {
		return $this->sources[ $slug ] ?? null;
	}

	/** @return array<string, Source> */
	public function all(): array {
		return $this->sources;
	}

	/**
	 * The network a pasted URL belongs to, as an Offer; a manual offer if none matches; a WP_Error
	 * if it's a network's page rather than an affiliate link.
	 */
	public function offerFromUrl( string $url, string $name = '' ): Offer|\WP_Error {
		foreach ( $this->sources as $source ) {
			$offer = $source->offerFromUrl( $url, $name );
			if ( null !== $offer ) {
				return $offer;
			}
		}
		return new Offer( '', '', $name, $url );
	}
}
