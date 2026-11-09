<?php
/**
 * The Affiliate Link edit screen and list table.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker\Admin;

use AffLinkCloaker\App;
use AffLinkCloaker\Clicks;
use AffLinkCloaker\Links;
use AffLinkCloaker\Settings;
use WP_Post;

final class LinkEditor {

	private const NONCE = 'alc_save_link';

	public static function init(): void {
		add_action( 'add_meta_boxes_' . Links::POST_TYPE, array( self::class, 'metaBoxes' ) );
		add_filter( 'wp_insert_post_data', array( self::class, 'filterSlug' ), 10, 2 );
		add_action( 'save_post_' . Links::POST_TYPE, array( self::class, 'save' ) );
		add_filter( 'manage_' . Links::POST_TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . Links::POST_TYPE . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	public static function assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || Links::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script( 'alc-admin', App::url( 'assets/admin.js' ), array(), App::version(), true );
		wp_localize_script( 'alc-admin', 'alcAdmin', array( 'copied' => __( 'Copied!', 'aff-link-cloaker' ) ) );
		wp_enqueue_style( 'alc-admin', App::url( 'assets/admin.css' ), array(), App::version() );
	}

	public static function copyField( string $url ): string {
		return sprintf(
			'<span class="alc-copy"><input type="text" readonly value="%1$s" onfocus="this.select()" /> <button type="button" class="button button-small alc-copy-btn" data-url="%1$s">%2$s</button></span>',
			esc_attr( $url ),
			esc_html__( 'Copy', 'aff-link-cloaker' )
		);
	}

	public static function statusBadge( int $post_id ): string {
		$request = Links::meta( $post_id, 'request_status' );
		$offer   = Links::meta( $post_id, 'offer_status' );
		if ( '' === $request && '' === $offer ) {
			return '';
		}
		$dead  = Links::isDead( $post_id );
		$label = 'inactive' === $offer ? __( 'Offer ended', 'aff-link-cloaker' ) : ( '' !== $request ? $request : __( 'Active', 'aff-link-cloaker' ) );
		return sprintf( '<span class="alc-badge %s">%s</span>', $dead ? 'alc-badge-dead' : 'alc-badge-ok', esc_html( $label ) );
	}

	/* -------------------------------------------------------------- */

	public static function metaBoxes(): void {
		add_meta_box( 'alc_link_details', __( 'Link Details', 'aff-link-cloaker' ), array( self::class, 'renderDetails' ), null, 'normal', 'high' );
		add_meta_box( 'alc_link_stats', __( 'Clicks', 'aff-link-cloaker' ), array( self::class, 'renderStats' ), null, 'side' );
	}

	public static function renderDetails( WP_Post $post ): void {
		wp_nonce_field( self::NONCE, 'alc_nonce' );
		$cloaked = 'publish' === $post->post_status ? Links::cloakedUrl( $post->ID ) : '';
		$dest    = Links::destination( $post->ID );
		$source  = App::instance()->sources->get( Links::meta( $post->ID, 'source' ) );
		$synced  = (int) Links::meta( $post->ID, 'synced_at' );
		?>
		<table class="form-table alc-form">
			<tr>
				<th><?php esc_html_e( 'Cloaked link', 'aff-link-cloaker' ); ?></th>
				<td>
					<?php
					if ( $cloaked ) {
						echo self::copyField( $cloaked ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in copyField().
					} else {
						echo '<em>' . esc_html__( 'Publish this link to activate its cloaked URL.', 'aff-link-cloaker' ) . '</em>';
					}
					?>
				</td>
			</tr>
			<tr>
				<th><label for="alc_slug"><?php esc_html_e( 'Slug', 'aff-link-cloaker' ); ?></label></th>
				<td>
					<code><?php echo esc_html( trailingslashit( home_url( (string) Settings::get( 'prefix' ) ) ) ); ?></code>
					<input type="text" id="alc_slug" name="alc_slug" value="<?php echo esc_attr( $post->post_name ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th><label for="alc_target_url"><?php esc_html_e( 'Affiliate URL', 'aff-link-cloaker' ); ?></label></th>
				<td>
					<input type="url" id="alc_target_url" name="alc_target_url" value="<?php echo esc_attr( Links::meta( $post->ID, 'target_url' ) ); ?>" class="large-text" placeholder="https://" />
					<?php if ( $source ) : ?>
						<p class="description"><?php echo esc_html( sprintf( /* translators: %s: network name */ __( 'Kept up to date by the %s sync.', 'aff-link-cloaker' ), $source->label() ) ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $source ) : ?>
			<tr>
				<th><?php esc_html_e( 'Offer', 'aff-link-cloaker' ); ?></th>
				<td>
					<?php
					echo esc_html( $source->label() );
					foreach ( array( 'vendor', 'offer_id' ) as $key ) {
						$value = Links::meta( $post->ID, $key );
						if ( '' !== $value ) {
							echo ' · ' . esc_html( $value );
						}
					}
					echo ' ' . self::statusBadge( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in statusBadge().
					if ( $synced ) {
						/* translators: %s: time ago */
						echo '<p class="description">' . esc_html( sprintf( __( 'Last synced %s ago.', 'aff-link-cloaker' ), human_time_diff( $synced ) ) ) . '</p>';
					}
					?>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th><?php esc_html_e( 'Clicks go to', 'aff-link-cloaker' ); ?></th>
				<td>
					<?php if ( $dest ) : ?>
						<a href="<?php echo esc_url( $dest ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $dest ); ?></a>
					<?php else : ?>
						<span class="alc-warn"><?php esc_html_e( 'No valid destination: this link shows a 404.', 'aff-link-cloaker' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="alc_redirect_type"><?php esc_html_e( 'Redirect type', 'aff-link-cloaker' ); ?></label></th>
				<td>
					<select id="alc_redirect_type" name="alc_redirect_type">
						<option value=""><?php esc_html_e( 'Site default', 'aff-link-cloaker' ); ?></option>
						<?php foreach ( Settings::redirectTypes() as $code => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( (int) Links::meta( $post->ID, 'redirect_type' ), $code ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
		<?php if ( $cloaked ) : ?>
			<p><strong><?php esc_html_e( 'Shortcode:', 'aff-link-cloaker' ); ?></strong> <code>[alc_link slug="<?php echo esc_html( $post->post_name ); ?>"]</code></p>
		<?php endif; ?>
		<?php
	}

	public static function renderStats( WP_Post $post ): void {
		printf(
			'<p>%s <strong>%d</strong><br>%s <strong>%d</strong><br>%s <strong>%d</strong></p>',
			esc_html__( 'Total:', 'aff-link-cloaker' ),
			Clicks::count( $post->ID ),
			esc_html__( 'Last 30 days:', 'aff-link-cloaker' ),
			Clicks::count( $post->ID, 30 ),
			esc_html__( 'Last 7 days:', 'aff-link-cloaker' ),
			Clicks::count( $post->ID, 7 )
		);
	}

	private static function nonceOk(): bool {
		return isset( $_POST['alc_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['alc_nonce'] ) ), self::NONCE );
	}

	/**
	 * The post type isn't public, so WordPress shows no permalink editor; take the slug field.
	 *
	 * @param array<string, mixed> $data    Post data.
	 * @param array<string, mixed> $postarr Raw post array.
	 * @return array<string, mixed>
	 */
	public static function filterSlug( array $data, array $postarr ): array {
		if ( Links::POST_TYPE !== $data['post_type'] || ! isset( $_POST['alc_slug'] ) || ! self::nonceOk() ) {
			return $data;
		}
		$slug = sanitize_title( wp_unslash( $_POST['alc_slug'] ) );
		if ( '' !== $slug ) {
			$data['post_name'] = wp_unique_post_slug( $slug, (int) ( $postarr['ID'] ?? 0 ), (string) $data['post_status'], Links::POST_TYPE, 0 );
		}
		return $data;
	}

	public static function save( int $post_id ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! self::nonceOk() || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$url = esc_url_raw( wp_unslash( $_POST['alc_target_url'] ?? '' ), array( 'http', 'https' ) );
		Links::setMeta( $post_id, 'target_url', $url );

		$type = absint( $_POST['alc_redirect_type'] ?? 0 );
		Links::setMeta( $post_id, 'redirect_type', array_key_exists( $type, Settings::redirectTypes() ) ? $type : '' );
	}

	/* -------------------------------------------------------------- */

	/**
	 * @param array<string, string> $cols Columns.
	 * @return array<string, string>
	 */
	public static function columns( array $cols ): array {
		$new = array();
		foreach ( $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['alc_url']    = __( 'Cloaked URL', 'aff-link-cloaker' );
				$new['alc_source'] = __( 'Source', 'aff-link-cloaker' );
				$new['alc_clicks'] = __( 'Clicks (30d / total)', 'aff-link-cloaker' );
			}
		}
		return $new;
	}

	public static function column( string $col, int $post_id ): void {
		switch ( $col ) {
			case 'alc_url':
				echo 'publish' === get_post_status( $post_id ) ? self::copyField( Links::cloakedUrl( $post_id ) ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'alc_source':
				$source = App::instance()->sources->get( Links::meta( $post_id, 'source' ) );
				echo esc_html( $source ? $source->label() : __( 'Manual', 'aff-link-cloaker' ) );
				$vendor = Links::meta( $post_id, 'vendor' );
				if ( '' !== $vendor ) {
					echo '<br><small>' . esc_html( $vendor ) . '</small>';
				}
				echo ' ' . self::statusBadge( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'alc_clicks':
				echo (int) Clicks::count( $post_id, 30 ) . ' / ' . (int) Clicks::count( $post_id );
				break;
		}
	}
}
