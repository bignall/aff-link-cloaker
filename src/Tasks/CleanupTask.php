<?php
/**
 * Removes the plugin's leftovers that WP Foundation doesn't track, on uninstall (when the site
 * owner opted in). Posts, terms, registered options, the clicks table and cron are handled by
 * WP Foundation itself.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker\Tasks;

use AffLinkCloaker\Sources\WarriorPlus\WarriorPlusSource;
use AffLinkCloaker\Sync;
use AffLinkCloaker\Vendor\Bignall\WPFoundation\Plugin;
use AffLinkCloaker\Vendor\Bignall\WPFoundation\Tasks\AbstractTask;

final class CleanupTask extends AbstractTask {

	public function upgrade( Plugin $plugin, string $from, string $to ): void {
		// Nothing to create on upgrade.
	}

	public function uninstall( Plugin $plugin ): void {
		delete_option( Sync::LAST_RUN_OPTION );
		WarriorPlusSource::forgetCache();

		// Cached JVZoo product names: one transient per product, so match them by prefix.
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_alc_jvz_name_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_alc_jvz_name_' ) . '%'
			)
		);
	}
}
