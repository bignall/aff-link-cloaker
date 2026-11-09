<?php
/**
 * Main plugin bootstrap.
 *
 * @package AffLinkCloaker
 */

defined( 'ABSPATH' ) || exit;

final class ALC_Plugin {

	/** @var ALC_Plugin|null */
	private static $instance = null;

	/** @var ALC_Source_Registry */
	public $sources;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		load_plugin_textdomain( 'aff-link-cloaker', false, dirname( plugin_basename( ALC_FILE ) ) . '/languages' );

		$this->sources = new ALC_Source_Registry();
		$this->sources->register( new ALC_WarriorPlus_Source() );

		/**
		 * Register additional affiliate sources (JVZoo, ClickBank, ...).
		 *
		 * @param ALC_Source_Registry $registry
		 */
		do_action( 'alc_register_sources', $this->sources );

		ALC_Clicks::maybe_upgrade();
		ALC_Post_Type::init();
		ALC_Redirector::init();
		ALC_Shortcode::init();

		if ( is_admin() ) {
			ALC_Settings::init();
			ALC_Admin::init();
		}
	}

	public static function activate() {
		ALC_Clicks::install();
		ALC_Post_Type::register();
		ALC_Redirector::add_rewrite_rules();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
