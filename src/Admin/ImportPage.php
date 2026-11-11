<?php
/**
 * Affiliate Links → Import: pick offers from a network's API, or paste a list of links.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker\Admin;

use AffLinkCloaker\App;
use AffLinkCloaker\Importer;
use AffLinkCloaker\Links;
use AffLinkCloaker\Sources\Offer;
use AffLinkCloaker\Sources\Source;
use AffLinkCloaker\Sync;

final class ImportPage {

	public const SLUG       = 'alc-import';
	private const PASTE_MAX = 200;

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_alc_import_offers', array( self::class, 'handleOffers' ) );
		add_action( 'admin_post_alc_import_pasted', array( self::class, 'handlePasted' ) );
		add_action( 'admin_post_alc_sync_now', array( self::class, 'handleSync' ) );
	}

	public static function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Links::POST_TYPE,
			__( 'Import Offers', 'aff-link-cloaker' ),
			__( 'Import Offers', 'aff-link-cloaker' ),
			'edit_posts',
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	private static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'post_type' => Links::POST_TYPE, 'page' => self::SLUG ), $args ), admin_url( 'edit.php' ) );
	}

	/* -------------------------------------------------------------- */

	public static function render(): void {
		// Networks with an offers API get a tab; the rest (e.g. JVZoo) are imported by pasting links.
		$sources = array_filter( App::instance()->sources->all(), static fn ( Source $s ): bool => $s->hasOfferApi() );
		$tab     = sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'paste' !== $tab && ! isset( $sources[ $tab ] ) ) {
			$tab = $sources ? (string) array_key_first( $sources ) : 'paste';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Offers', 'aff-link-cloaker' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $sources as $slug => $source ) : ?>
					<a href="<?php echo esc_url( self::url( array( 'tab' => $slug ) ) ); ?>" class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $source->label() ); ?></a>
				<?php endforeach; ?>
				<a href="<?php echo esc_url( self::url( array( 'tab' => 'paste' ) ) ); ?>" class="nav-tab <?php echo 'paste' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Paste links', 'aff-link-cloaker' ); ?></a>
			</nav>
			<?php
			if ( 'paste' === $tab ) {
				self::renderPaste();
			} else {
				self::renderSource( $sources[ $tab ] );
			}
			?>
		</div>
		<?php
	}

	private static function renderSource( Source $source ): void {
		if ( ! $source->canListOffers() ) {
			printf(
				'<div class="notice notice-info inline"><p>%s</p><p><a class="button button-primary" href="%s">%s</a> <a class="button" href="%s">%s</a></p></div>',
				esc_html( sprintf( /* translators: %s: network */ __( 'Add your %s API key to list your approved offers here and create cloaked links for them in one click.', 'aff-link-cloaker' ), $source->label() ) ),
				esc_url( admin_url( 'edit.php?post_type=' . Links::POST_TYPE . '&page=' . SettingsPage::SLUG ) ),
				esc_html__( 'Add API key', 'aff-link-cloaker' ),
				esc_url( self::url( array( 'tab' => 'paste' ) ) ),
				esc_html__( 'Or paste links instead', 'aff-link-cloaker' )
			);
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Read-only view filters.
		$fresh = ! empty( $_GET['refresh'] ) && check_admin_referer( 'alc_refresh_offers' );
		$show  = sanitize_key( wp_unslash( $_GET['show'] ?? 'approved' ) );
		// phpcs:enable

		$offers = $source->listOffers( (bool) $fresh );
		if ( is_wp_error( $offers ) ) {
			printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( $offers->get_error_message() ) );
			return;
		}

		$total    = count( $offers );
		$approved = array_values( array_filter( $offers, static fn ( Offer $o ): bool => $o->isPromotable() ) );
		$list     = 'all' === $show ? $offers : $approved;
		$refresh  = wp_nonce_url( self::url( array( 'tab' => $source->slug(), 'refresh' => 1, 'show' => $show ) ), 'alc_refresh_offers' );
		$cats     = get_terms( array( 'taxonomy' => Links::TAXONOMY, 'hide_empty' => false ) );
		$last     = get_option( Sync::LAST_RUN_OPTION );
		?>
		<ul class="subsubsub">
			<li><a href="<?php echo esc_url( self::url( array( 'tab' => $source->slug() ) ) ); ?>" class="<?php echo 'all' !== $show ? 'current' : ''; ?>"><?php esc_html_e( 'Approved only', 'aff-link-cloaker' ); ?> <span class="count">(<?php echo (int) count( $approved ); ?>)</span></a> |</li>
			<li><a href="<?php echo esc_url( self::url( array( 'tab' => $source->slug(), 'show' => 'all' ) ) ); ?>" class="<?php echo 'all' === $show ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'aff-link-cloaker' ); ?> <span class="count">(<?php echo (int) $total; ?>)</span></a></li>
		</ul>
		<form class="alc-toolbar" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alc_sync_now" />
			<?php wp_nonce_field( 'alc_sync_now' ); ?>
			<a class="button" href="<?php echo esc_url( $refresh ); ?>"><?php echo esc_html( sprintf( /* translators: %s: network */ __( 'Reload from %s', 'aff-link-cloaker' ), $source->label() ) ); ?></a>
			<button type="submit" class="button"><?php esc_html_e( 'Sync existing links now', 'aff-link-cloaker' ); ?></button>
			<?php if ( is_array( $last ) && ! empty( $last['time'] ) ) : ?>
				<span class="description"><?php echo esc_html( sprintf( /* translators: %s: time ago */ __( 'Links last synced %s ago.', 'aff-link-cloaker' ), human_time_diff( (int) $last['time'] ) ) ); ?></span>
			<?php endif; ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alc_import_offers" />
			<input type="hidden" name="source" value="<?php echo esc_attr( $source->slug() ); ?>" />
			<?php wp_nonce_field( 'alc_import_offers' ); ?>
			<table class="wp-list-table widefat fixed striped alc-offers">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" class="alc-check-all" aria-label="<?php esc_attr_e( 'Select all new offers', 'aff-link-cloaker' ); ?>" /></td>
						<th class="column-primary"><?php esc_html_e( 'Offer', 'aff-link-cloaker' ); ?></th>
						<th><?php esc_html_e( 'Vendor', 'aff-link-cloaker' ); ?></th>
						<th><?php esc_html_e( 'Status', 'aff-link-cloaker' ); ?></th>
						<th><?php esc_html_e( 'Launch', 'aff-link-cloaker' ); ?></th>
						<th class="alc-col-wide"><?php esc_html_e( 'Cloaked link', 'aff-link-cloaker' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $list ) : ?>
					<tr><td colspan="6"><?php 'all' === $show ? esc_html_e( 'You have no affiliate requests on this network yet.', 'aff-link-cloaker' ) : esc_html_e( 'No approved offers. Switch to “All” to see pending and paused ones.', 'aff-link-cloaker' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $list as $offer ) : ?>
					<?php
					$existing = Links::findExisting( $offer->source, $offer->offerId, $offer->affiliateUrl );
					$can      = ! $existing && $offer->isPromotable();
					?>
					<tr>
						<th scope="row" class="check-column">
							<?php if ( $can ) : ?>
								<input type="checkbox" name="offers[]" value="<?php echo esc_attr( $offer->key() ); ?>" />
							<?php endif; ?>
						</th>
						<td class="column-primary">
							<strong><?php echo esc_html( $offer->name ); ?></strong>
							<?php if ( '' !== $offer->affiliateUrl ) : ?>
								<br><small><a href="<?php echo esc_url( $offer->affiliateUrl ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $offer->affiliateUrl ); ?></a></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $offer->vendor ); ?></td>
						<td>
							<span class="alc-badge <?php echo $offer->isPromotable() ? 'alc-badge-ok' : 'alc-badge-dead'; ?>"><?php echo esc_html( 'inactive' === $offer->offerStatus ? __( 'Offer ended', 'aff-link-cloaker' ) : $offer->requestStatus ); ?></span>
						</td>
						<td><?php echo esc_html( $offer->startDate ? mysql2date( get_option( 'date_format' ), $offer->startDate ) : '' ); ?></td>
						<td>
							<?php if ( $existing ) : ?>
								<?php
								$cloaked = 'publish' === get_post_status( $existing ) ? Links::cloakedUrl( $existing ) : '';
								echo $cloaked ? LinkEditor::copyField( $cloaked ) : esc_html__( '(draft)', 'aff-link-cloaker' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
								<a href="<?php echo esc_url( (string) get_edit_post_link( $existing ) ); ?>"><?php esc_html_e( 'Edit', 'aff-link-cloaker' ); ?></a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php self::importOptions( $cats ); ?>
			<?php submit_button( __( 'Create cloaked links for selected offers', 'aff-link-cloaker' ) ); ?>
		</form>
		<?php
	}

	/** @param mixed $cats Result of get_terms(). */
	private static function importOptions( $cats ): void {
		?>
		<p class="alc-import-options">
			<?php if ( is_array( $cats ) && $cats ) : ?>
				<label><?php esc_html_e( 'Category', 'aff-link-cloaker' ); ?>
					<select name="category">
						<option value="0">&mdash;</option>
						<?php foreach ( $cats as $t ) : ?>
							<option value="<?php echo esc_attr( (string) $t->term_id ); ?>"><?php echo esc_html( $t->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
			<label><input type="checkbox" name="draft" value="1" /> <?php esc_html_e( 'Create as drafts', 'aff-link-cloaker' ); ?></label>
		</p>
		<?php
	}

	private static function renderPaste(): void {
		$cats = get_terms( array( 'taxonomy' => Links::TAXONOMY, 'hide_empty' => false ) );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alc_import_pasted" />
			<?php wp_nonce_field( 'alc_import_pasted' ); ?>
			<p><label for="alc_pasted"><?php esc_html_e( 'Affiliate links, one per line. Add a name with “Name | URL”; otherwise rename the links afterwards.', 'aff-link-cloaker' ); ?></label></p>
			<textarea id="alc_pasted" name="links" rows="10" class="large-text code" placeholder="Super Traffic Bot | https://warriorplus.com/o2/a/abc123/0&#10;https://www.jvzoo.com/c/12345/67890"></textarea>
			<p class="description">
				<?php
				/* translators: %d: max lines */
				echo esc_html( sprintf( __( 'Up to %d per batch. Links from any network work. WarriorPlus links are matched to your offers on the next sync; JVZoo links get their product name from JVZoo when you leave the name out. Links you already have are skipped.', 'aff-link-cloaker' ), self::PASTE_MAX ) );
				?>
			</p>
			<?php self::importOptions( $cats ); ?>
			<?php submit_button( __( 'Create cloaked links', 'aff-link-cloaker' ) ); ?>
		</form>
		<?php
	}

	/* -------------------------------------------------------------- */

	private static function guard( string $nonce ): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'aff-link-cloaker' ), 403 );
		}
		check_admin_referer( $nonce );
	}

	/** @return array{status: string, category: int} */
	private static function postedOptions(): array {
		// phpcs:disable WordPress.Security.NonceVerification -- Verified in guard().
		return array(
			'status'   => ! empty( $_POST['draft'] ) || ! current_user_can( 'publish_posts' ) ? 'draft' : 'publish',
			'category' => absint( $_POST['category'] ?? 0 ),
		);
		// phpcs:enable
	}

	public static function handleOffers(): void {
		self::guard( 'alc_import_offers' );
		// phpcs:disable WordPress.Security.NonceVerification -- Verified in guard().
		$slug     = sanitize_key( wp_unslash( $_POST['source'] ?? '' ) );
		$selected = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['offers'] ?? array() ) );
		// phpcs:enable

		$source = App::instance()->sources->get( $slug );
		$back   = self::url( array( 'tab' => $slug ) );
		if ( ! $source || ! $selected ) {
			Notices::flash( 'warning', __( 'No offers were selected.', 'aff-link-cloaker' ) );
			wp_safe_redirect( $back );
			exit;
		}

		// Only trust offers as the network reports them, never URLs from the form.
		$offers = $source->listOffers();
		if ( is_wp_error( $offers ) ) {
			Notices::flash( 'error', $offers->get_error_message() );
			wp_safe_redirect( $back );
			exit;
		}
		$by_key = array();
		foreach ( $offers as $offer ) {
			$by_key[ $offer->key() ] = $offer;
		}

		$results = array();
		foreach ( $selected as $key ) {
			if ( isset( $by_key[ $key ] ) ) {
				$results[] = array( $by_key[ $key ], Importer::save( $by_key[ $key ], self::postedOptions() ) );
			}
		}
		self::flashResults( $results );
		wp_safe_redirect( $back );
		exit;
	}

	public static function handlePasted(): void {
		self::guard( 'alc_import_pasted' );
		$text = sanitize_textarea_field( wp_unslash( $_POST['links'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$rows = array_slice( Importer::parsePasted( $text ), 0, self::PASTE_MAX );

		$results = array();
		foreach ( Importer::importLinks( $rows, self::postedOptions() ) as $r ) {
			$results[] = array(
				$r['offer'],
				match ( $r['status'] ) {
					'created' => array( 'post_id' => $r['post_id'], 'created' => true ),
					'exists'  => new \WP_Error( 'alc_exists', __( 'Already has a cloaked link; skipped.', 'aff-link-cloaker' ) ),
					default   => $r['error'] ?? new \WP_Error( 'alc_error', __( 'Could not import.', 'aff-link-cloaker' ) ),
				},
			);
		}
		self::flashResults( $results );
		wp_safe_redirect( self::url( array( 'tab' => 'paste' ) ) );
		exit;
	}

	public static function handleSync(): void {
		self::guard( 'alc_sync_now' );
		$r = Sync::run();
		if ( $r['errors'] ) {
			Notices::flash( 'error', __( 'Sync failed:', 'aff-link-cloaker' ), $r['errors'] );
		} else {
			/* translators: 1: links checked, 2: links that can't earn */
			Notices::flash( 'success', sprintf( __( 'Sync finished: %1$d links checked, %2$d point to offers you can no longer earn from.', 'aff-link-cloaker' ), $r['updated'] + $r['created'], $r['dead'] ) );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . Links::POST_TYPE ) );
		exit;
	}

	/** @param list<array{0: Offer, 1: array{post_id: int, created: bool}|\WP_Error}> $results */
	private static function flashResults( array $results ): void {
		$ok  = array();
		$bad = array();
		foreach ( $results as [ $offer, $r ] ) {
			$label = '' !== $offer->name ? $offer->name : $offer->affiliateUrl;
			if ( is_wp_error( $r ) ) {
				$bad[] = $label . ' – ' . $r->get_error_message();
				continue;
			}
			$cloaked = 'publish' === get_post_status( $r['post_id'] ) ? Links::cloakedUrl( $r['post_id'] ) : __( '(draft)', 'aff-link-cloaker' );
			// Link each new cloaked URL to its edit screen (WP Foundation escapes text and URL).
			$ok[] = array(
				'text' => html_entity_decode( get_the_title( $r['post_id'] ), ENT_QUOTES, 'UTF-8' ) . ' → ' . $cloaked,
				'url'  => (string) get_edit_post_link( $r['post_id'], 'raw' ),
			);
		}
		if ( $ok ) {
			/* translators: %d: count */
			Notices::flash( 'success', sprintf( _n( '%d cloaked link created:', '%d cloaked links created:', count( $ok ), 'aff-link-cloaker' ), count( $ok ) ), $ok );
		}
		if ( $bad ) {
			Notices::flash( 'warning', __( 'Not imported:', 'aff-link-cloaker' ), $bad );
		}
		if ( ! $ok && ! $bad ) {
			Notices::flash( 'warning', __( 'Nothing to import.', 'aff-link-cloaker' ) );
		}
	}
}
