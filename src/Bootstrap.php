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
		return Plugin::create( $file, self::PREFIX )
			->requires( php: '8.1', wp: '6.4', extensions: array( 'json' ) )
			// v0.1.0 predates WP Foundation and stored no plugin version; its clicks table
			// version option ("1") marks an existing install, so upgrade it from 0.1.0.
			->legacyVersion( static fn (): ?string => false !== get_option( 'alc_db_version' ) ? '0.1.0' : null )
			->options( array( Settings::OPTION => Settings::defaults() ), merge: true )
			->tables( new ClicksTable() )
			->postTypes( Links::POST_TYPE )
			->taxonomies( Links::TAXONOMY )
			->cron( self::SYNC_HOOK, 'twicedaily' )
			->flushRewriteRules()
			->task( new Tasks\CleanupTask() )
			->migration( '0.2.0', array( Migrations::class, 'storeBuiltDestinations' ) )
			->deactivationSurvey()
			->deleteDataOnUninstall( static fn (): bool => (bool) Settings::get( 'remove_data' ) )
			->onReady( static fn ( Plugin $plugin ) => App::boot( $plugin ) );
	}
}
