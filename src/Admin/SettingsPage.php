<?php
/**
 * Affiliate Links → Settings.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker\Admin;

use AffLinkCloaker\Links;
use AffLinkCloaker\Settings;
use AffLinkCloaker\Sources\WarriorPlus\ApiClient;
use AffLinkCloaker\Vendor\Bignall\WPFoundation\Plugin;

final class SettingsPage {

	public const SLUG = 'alc-settings';

	private static Plugin $plugin;

	public static function init( Plugin $plugin ): void {
		self::$plugin = $plugin;
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_alc_test_api', array( self::class, 'handleTest' ) );
		add_filter( 'plugin_action_links_' . $plugin->basename(), array( self::class, 'pluginLinks' ) );
	}

	private static function url(): string {
		return admin_url( 'edit.php?post_type=' . Links::POST_TYPE . '&page=' . self::SLUG );
	}

	public static function menu(): void {
		add_submenu_page( 'edit.php?post_type=' . Links::POST_TYPE, __( 'Link Cloaker Settings', 'aff-link-cloaker' ), __( 'Settings', 'aff-link-cloaker' ), 'manage_options', self::SLUG, array( self::class, 'render' ) );
	}

	/**
	 * @param array<string, string> $links Action links.
	 * @return array<string, string>
	 */
	public static function pluginLinks( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'aff-link-cloaker' ) . '</a>' );
		return $links;
	}

	public static function handleTest(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'aff-link-cloaker' ), 403 );
		}
		check_admin_referer( 'alc_test_api' );

		$r = ( new ApiClient( Settings::warriorPlusApiKey() ) )->get( '/affiliate_requests', array( 'as' => 'affiliate', 'limit' => 1 ) );
		if ( is_wp_error( $r ) ) {
			Notices::flash( 'error', $r->get_error_message() );
		} else {
			Notices::flash( 'success', __( 'Connected to WarriorPlus. Your API key works.', 'aff-link-cloaker' ) );
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	public static function render(): void {
		$s         = Settings::all();
		$name      = Settings::OPTION;
		$key_const = defined( Settings::API_KEY_CONSTANT );
		$has_key   = '' !== Settings::warriorPlusApiKey();
		$flag      = static function ( string $key, string $label ) use ( $s, $name ): void {
			printf(
				'<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label><br>',
				esc_attr( $name ),
				esc_attr( $key ),
				checked( (int) $s[ $key ], 1, false ),
				esc_html( $label )
			);
		};
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Link Cloaker Settings', 'aff-link-cloaker' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( Settings::GROUP ); ?>

				<h2>WarriorPlus</h2>
				<table class="form-table">
					<tr>
						<th><label for="alc_wp_api_key"><?php esc_html_e( 'API key', 'aff-link-cloaker' ); ?></label></th>
						<td>
							<?php if ( $key_const ) : ?>
								<p><?php echo wp_kses_post( sprintf( /* translators: %s: constant name */ __( 'Set in <code>wp-config.php</code> with <code>%s</code>.', 'aff-link-cloaker' ), Settings::API_KEY_CONSTANT ) ); ?></p>
							<?php else : ?>
								<input type="password" id="alc_wp_api_key" name="<?php echo esc_attr( $name ); ?>[wp_api_key]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $has_key ? esc_attr__( 'Saved – leave blank to keep', 'aff-link-cloaker' ) : ''; ?>" />
								<?php if ( $has_key ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[wp_api_key_clear]" value="1" /> <?php esc_html_e( 'Remove saved key', 'aff-link-cloaker' ); ?></label>
								<?php endif; ?>
								<p class="description">
									<?php
									echo wp_kses_post(
										sprintf(
											/* translators: 1: URL, 2: constant name */
											__( 'Get it from <a href="%1$s" target="_blank" rel="noopener">WarriorPlus → My Account → API Key</a>. To keep it out of the database, define <code>%2$s</code> in wp-config.php instead.', 'aff-link-cloaker' ),
											'https://warriorplus.com/account/api',
											Settings::API_KEY_CONSTANT
										)
									);
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Sync', 'aff-link-cloaker' ); ?></th>
						<td>
							<?php
							$flag( 'auto_sync', __( 'Check my offers twice a day and update links (new affiliate URLs, renamed offers, paused or ended offers)', 'aff-link-cloaker' ) );
							$flag( 'auto_create', __( 'Also create links automatically for newly approved offers', 'aff-link-cloaker' ) );
							?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Links', 'aff-link-cloaker' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="alc_prefix"><?php esc_html_e( 'Link prefix', 'aff-link-cloaker' ); ?></label></th>
						<td>
							<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code><input type="text" id="alc_prefix" name="<?php echo esc_attr( $name ); ?>[prefix]" value="<?php echo esc_attr( (string) $s['prefix'] ); ?>" class="small-text" /><code>/your-slug/</code>
							<p class="description"><?php esc_html_e( 'Changing this breaks links you have already shared elsewhere.', 'aff-link-cloaker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="alc_inactive_url"><?php esc_html_e( 'Fallback URL', 'aff-link-cloaker' ); ?></label></th>
						<td>
							<input type="url" id="alc_inactive_url" name="<?php echo esc_attr( $name ); ?>[inactive_url]" value="<?php echo esc_attr( (string) $s['inactive_url'] ); ?>" class="large-text" placeholder="https://" />
							<p class="description"><?php esc_html_e( 'Where to send clicks on links to offers you can no longer earn from (you were paused or banned, or the offer ended). Leave blank to keep sending them to the offer.', 'aff-link-cloaker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="alc_redirect"><?php esc_html_e( 'Default redirect', 'aff-link-cloaker' ); ?></label></th>
						<td>
							<select id="alc_redirect" name="<?php echo esc_attr( $name ); ?>[redirect_type]">
								<?php foreach ( Settings::redirectTypes() as $code => $label ) : ?>
									<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( (int) $s['redirect_type'], $code ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Shortcode links', 'aff-link-cloaker' ); ?></th>
						<td>
							<?php
							$flag( 'nofollow', __( 'Add rel="nofollow"', 'aff-link-cloaker' ) );
							$flag( 'sponsored', __( 'Add rel="sponsored" (Google’s recommendation for affiliate links)', 'aff-link-cloaker' ) );
							$flag( 'new_tab', __( 'Open in a new tab', 'aff-link-cloaker' ) );
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Tracking', 'aff-link-cloaker' ); ?></th>
						<td>
							<?php
							$flag( 'track_clicks', __( 'Count clicks', 'aff-link-cloaker' ) );
							$flag( 'ignore_bots', __( 'Ignore bots and link previews', 'aff-link-cloaker' ) );
							$flag( 'pass_query', __( 'Pass query-string parameters through to the destination', 'aff-link-cloaker' ) );
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Uninstall', 'aff-link-cloaker' ); ?></th>
						<td><?php $flag( 'remove_data', __( 'Delete all links, click history and settings when the plugin is deleted', 'aff-link-cloaker' ) ); ?></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<?php if ( $has_key ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="alc_test_api" />
					<?php wp_nonce_field( 'alc_test_api' ); ?>
					<?php submit_button( __( 'Test WarriorPlus connection', 'aff-link-cloaker' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
