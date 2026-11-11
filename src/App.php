<?php
/**
 * Wires the plugin's runtime services once WP Foundation says it's ready.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker;

use AffLinkCloaker\Sources\SourceRegistry;
use AffLinkCloaker\Sources\JVZoo\JVZooSource;
use AffLinkCloaker\Sources\WarriorPlus\WarriorPlusSource;
use AffLinkCloaker\Vendor\Bignall\WPFoundation\Plugin;

final class App {

	private static ?self $instance = null;

	private function __construct( public readonly Plugin $plugin, public readonly SourceRegistry $sources ) {}

	public static function boot( Plugin $plugin ): void {
		$sources = new SourceRegistry();
		$sources->register( new WarriorPlusSource() );
		$sources->register( new JVZooSource() );

		/**
		 * Register more affiliate networks.
		 *
		 * @param SourceRegistry $sources
		 */
		do_action( 'alc_register_sources', $sources );

		self::$instance = new self( $plugin, $sources );

		load_plugin_textdomain( 'aff-link-cloaker', false, dirname( $plugin->basename() ) . '/languages' );

		Links::init();
		Redirector::init();
		Shortcode::init();
		Sync::init();
		Rest::init();
		Settings::init( $plugin );

		if ( is_admin() ) {
			Admin\Notices::init();
			Admin\LinkEditor::init();
			Admin\ImportPage::init();
			Admin\SettingsPage::init( $plugin );
		}
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			throw new \LogicException( 'Affiliate Link Cloaker is not ready yet.' );
		}
		return self::$instance;
	}

	public static function url( string $path = '' ): string {
		return plugins_url( $path, ALC_FILE );
	}

	public static function version(): string {
		return self::instance()->plugin->currentVersion();
	}
}
