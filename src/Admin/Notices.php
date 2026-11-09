<?php
/**
 * One-shot admin notices that survive a redirect (post/redirect/get).
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker\Admin;

use AffLinkCloaker\Links;
use AffLinkCloaker\Settings;
use AffLinkCloaker\Sync;

final class Notices {

	public static function init(): void {
		add_action( 'admin_notices', array( self::class, 'render' ) );
	}

	private static function key(): string {
		return 'alc_flash_' . get_current_user_id();
	}

	/**
	 * Queue a notice for the next admin page load.
	 *
	 * @param string       $type    success, warning, error or info.
	 * @param string       $message Plain text.
	 * @param list<string> $items   Optional list items (may contain safe HTML from esc_* calls).
	 */
	public static function flash( string $type, string $message, array $items = array() ): void {
		$queue   = get_transient( self::key() );
		$queue   = is_array( $queue ) ? $queue : array();
		$queue[] = compact( 'type', 'message', 'items' );
		set_transient( self::key(), $queue, 5 * MINUTE_IN_SECONDS );
	}

	public static function render(): void {
		$queue = get_transient( self::key() );
		if ( is_array( $queue ) ) {
			delete_transient( self::key() );
			foreach ( $queue as $n ) {
				printf( '<div class="notice notice-%s is-dismissible"><p>%s</p>', esc_attr( $n['type'] ), esc_html( $n['message'] ) );
				if ( $n['items'] ) {
					echo '<ul class="alc-results">';
					foreach ( $n['items'] as $item ) {
						echo '<li>' . wp_kses_post( $item ) . '</li>';
					}
					echo '</ul>';
				}
				echo '</div>';
			}
		}

		self::renderHealth();
	}

	/** Standing warnings on the plugin's own screens. */
	private static function renderHealth(): void {
		$screen = get_current_screen();
		if ( ! $screen || Links::POST_TYPE !== $screen->post_type || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$last = get_option( Sync::LAST_RUN_OPTION );
		if ( is_array( $last ) && ! empty( $last['errors'] ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p><ul class="alc-results"><li>%s</li></ul></div>',
				esc_html__( 'The last offer sync failed:', 'aff-link-cloaker' ),
				implode( '</li><li>', array_map( 'esc_html', $last['errors'] ) )
			);
		}

		if ( '' === Settings::get( 'inactive_url' ) ) {
			$dead = count( self::deadLinkIds() );
			if ( $dead ) {
				printf(
					'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
					esc_html(
						sprintf(
							/* translators: %d: number of links */
							_n( '%d link points to an offer you can no longer earn from (paused, banned or the offer ended).', '%d links point to offers you can no longer earn from (paused, banned or the offer ended).', $dead, 'aff-link-cloaker' ),
							$dead
						)
					),
					esc_url( admin_url( 'edit.php?post_type=' . Links::POST_TYPE . '&page=' . SettingsPage::SLUG ) ),
					esc_html__( 'Set a fallback URL for them', 'aff-link-cloaker' )
				);
			}
		}
	}

	/** @return list<int> */
	private static function deadLinkIds(): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'   => Links::POST_TYPE,
					'post_status' => 'publish',
					'numberposts' => -1,
					'fields'      => 'ids',
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						'relation' => 'OR',
						array(
							'key'     => Links::META['request_status'],
							'value'   => Links::DEAD_STATUSES,
							'compare' => 'IN',
						),
						array(
							'key'   => Links::META['offer_status'],
							'value' => 'inactive',
						),
					),
				)
			)
		);
	}
}
