<?php
/**
 * Removes the plugin's posts, terms and leftover options on uninstall (when the site owner
 * opted in). WP Foundation handles options it registered, the clicks table and cron.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker\Tasks;

use AffLinkCloaker\Links;
use AffLinkCloaker\Sync;
use AffLinkCloaker\Vendor\Bignall\WPFoundation\Plugin;
use AffLinkCloaker\Vendor\Bignall\WPFoundation\Tasks\AbstractTask;

final class ContentTask extends AbstractTask {

	public function upgrade( Plugin $plugin, string $from, string $to ): void {
		// Nothing to create on upgrade.
	}

	public function uninstall( Plugin $plugin ): void {
		$ids = get_posts(
			array(
				'post_type'   => Links::POST_TYPE,
				'post_status' => array_keys( get_post_stati() ), // 'any' skips trash and auto-drafts.
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}

		// The taxonomy isn't registered while uninstalling; register it so terms can be read.
		if ( ! taxonomy_exists( Links::TAXONOMY ) ) {
			register_taxonomy( Links::TAXONOMY, Links::POST_TYPE );
		}
		$terms = get_terms(
			array(
				'taxonomy'   => Links::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				wp_delete_term( (int) $term, Links::TAXONOMY );
			}
		}

		delete_option( Sync::LAST_RUN_OPTION );
		delete_transient( 'alc_wplus_offers' );
	}
}
