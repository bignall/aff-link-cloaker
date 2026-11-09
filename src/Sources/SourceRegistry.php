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

	/** The network a pasted URL belongs to, as an Offer; a manual offer if none matches. */
	public function offerFromUrl( string $url, string $name = '' ): Offer {
		foreach ( $this->sources as $source ) {
			$offer = $source->offerFromUrl( $url, $name );
			if ( $offer ) {
				return $offer;
			}
		}
		return new Offer( '', '', $name, $url );
	}
}
