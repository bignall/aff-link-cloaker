<?php
/**
 * Holds the available affiliate sources.
 *
 * @package AffLinkCloaker
 */

defined( 'ABSPATH' ) || exit;

class ALC_Source_Registry {

	/** @var ALC_Source[] */
	private $sources = array();

	public function register( ALC_Source $source ) {
		$this->sources[ $source->get_slug() ] = $source;
	}

	/** @return ALC_Source|null */
	public function get( $slug ) {
		return isset( $this->sources[ $slug ] ) ? $this->sources[ $slug ] : null;
	}

	/** @return ALC_Source[] */
	public function all() {
		return $this->sources;
	}
}
