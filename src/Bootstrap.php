<?php
/**
 * Plugin definition: lifecycle handled by WP Foundation.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker;

use AffLinkCloaker\Vendor\Bignall\WPFoundation\Plugin;

final class Bootstrap {

	public const PREFIX    = 'alc';
	public const SYNC_HOOK = 'alc_sync_offers';

	public static function plugin( string $file ): Plugin {
		self::adoptLegacyInstall();

		return Plugin::create( $file, self::PREFIX )
			->requires( php: '8.1', wp: '6.4', extensions: array( 'json' ) )
			->options( array( Settings::OPTION => Settings::defaults() ) )
			->tables( new ClicksTable() )
			->cron( self::SYNC_HOOK, 'twicedaily' )
			->flushRewriteRules()
			->task( new Tasks\ContentTask() )
			->migration( '0.2.0', array( Migrations::class, 'storeBuiltDestinations' ) )
			->deactivationSurvey()
			->deleteDataOnUninstall( static fn (): bool => (bool) Settings::get( 'remove_data' ) )
			->onReady( static fn ( Plugin $plugin ) => App::boot( $plugin ) );
	}

	/**
	 * v0.1.0 predates WP Foundation and stored no version, so the foundation would treat an
	 * existing install as brand new and skip the 0.2.0 migration. Seed the version it was.
	 *
	 * @todo Replace with a WP Foundation API for adopting pre-existing installs.
	 */
	private static function adoptLegacyInstall(): void {
		if ( false === get_option( self::PREFIX . '_version' ) && false !== get_option( 'alc_db_version' ) ) {
			update_option( self::PREFIX . '_version', '0.1.0', false );
		}
	}
}
