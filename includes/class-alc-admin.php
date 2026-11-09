<?php
/**
 * Admin UI: link meta box, list columns, Import and Settings screens.
 *
 * @package AffLinkCloaker
 */

defined( 'ABSPATH' ) || exit;

class ALC_Admin {

	const IMPORT_PAGE = 'alc-import';
	const BULK_LIMIT  = 50;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'add_meta_boxes_' . ALC_Post_Type::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'filter_slug' ), 10, 2 );
		add_action( 'save_post_' . ALC_Post_Type::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . ALC_Post_Type::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . ALC_Post_Type::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_alc_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_alc_refresh', array( __CLASS__, 'handle_refresh' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ALC_FILE ), array( __CLASS__, 'plugin_links' ) );
	}

	private static function parent_slug() {
		return 'edit.php?post_type=' . ALC_Post_Type::POST_TYPE;
	}

	public static function menu() {
		add_submenu_page( self::parent_slug(), __( 'Import Offers', 'aff-link-cloaker' ), __( 'Import Offers', 'aff-link-cloaker' ), 'edit_posts', self::IMPORT_PAGE, array( __CLASS__, 'render_import' ) );
		add_submenu_page( self::parent_slug(), __( 'Link Cloaker Settings', 'aff-link-cloaker' ), __( 'Settings', 'aff-link-cloaker' ), 'manage_options', ALC_Settings::PAGE, array( __CLASS__, 'render_settings' ) );
	}

	public static function plugin_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( self::parent_slug() . '&page=' . ALC_Settings::PAGE ) ) . '">' . esc_html__( 'Settings', 'aff-link-cloaker' ) . '</a>' );
		return $links;
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || ALC_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script( 'alc-admin', ALC_URL . 'assets/admin.js', array(), ALC_VERSION, true );
		wp_localize_script( 'alc-admin', 'alcAdmin', array( 'copied' => __( 'Copied!', 'aff-link-cloaker' ) ) );
		wp_enqueue_style( 'alc-admin', ALC_URL . 'assets/admin.css', array(), ALC_VERSION );
	}

	/* ------------------------------------------------------------------ */
	/* Meta box                                                            */
	/* ------------------------------------------------------------------ */

	public static function meta_boxes() {
		add_meta_box( 'alc_link_details', __( 'Link Details', 'aff-link-cloaker' ), array( __CLASS__, 'render_meta_box' ), null, 'normal', 'high' );
		add_meta_box( 'alc_link_stats', __( 'Clicks', 'aff-link-cloaker' ), array( __CLASS__, 'render_stats_box' ), null, 'side' );
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'alc_save_link', 'alc_nonce' );
		$sources = ALC_Plugin::instance()->sources->all();
		$m       = array();
		foreach ( array_keys( ALC_Post_Type::META ) as $k ) {
			$m[ $k ] = ALC_Post_Type::get_meta( $post->ID, $k );
		}
		$cloaked = 'publish' === $post->post_status ? ALC_Post_Type::cloaked_url( $post->ID ) : '';
		$dest    = ALC_Post_Type::destination( $post->ID );
		?>
		<table class="form-table alc-form">
			<tr>
				<th><?php esc_html_e( 'Cloaked link', 'aff-link-cloaker' ); ?></th>
				<td>
					<?php if ( $cloaked ) : ?>
						<?php self::copy_field( $cloaked ); ?>
					<?php else : ?>
						<em><?php esc_html_e( 'Publish this link to activate its cloaked URL.', 'aff-link-cloaker' ); ?></em>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="alc_slug"><?php esc_html_e( 'Slug', 'aff-link-cloaker' ); ?></label></th>
				<td>
					<code><?php echo esc_html( trailingslashit( home_url( ALC_Settings::get( 'prefix' ) ) ) ); ?></code>
					<input type="text" id="alc_slug" name="alc_slug" value="<?php echo esc_attr( $post->post_name ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th><label for="alc_source"><?php esc_html_e( 'Source', 'aff-link-cloaker' ); ?></label></th>
				<td>
					<select id="alc_source" name="alc_source">
						<option value=""><?php esc_html_e( 'None (manual URL)', 'aff-link-cloaker' ); ?></option>
						<?php foreach ( $sources as $s ) : ?>
							<option value="<?php echo esc_attr( $s->get_slug() ); ?>" <?php selected( $m['source'], $s->get_slug() ); ?>><?php echo esc_html( $s->get_label() ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="alc_offer_id" class="alc-inline"><?php esc_html_e( 'Offer code', 'aff-link-cloaker' ); ?></label>
					<input type="text" id="alc_offer_id" name="alc_offer_id" value="<?php echo esc_attr( $m['offer_id'] ); ?>" />
					<p class="description"><?php esc_html_e( 'With a source and offer code, the destination is built from your account settings on every click.', 'aff-link-cloaker' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="alc_target_url"><?php esc_html_e( 'Manual / fallback URL', 'aff-link-cloaker' ); ?></label></th>
				<td>
					<input type="url" id="alc_target_url" name="alc_target_url" value="<?php echo esc_attr( $m['target_url'] ); ?>" class="large-text" placeholder="https://" />
					<p class="description"><?php esc_html_e( 'Used when no source/offer code is set, or the source can’t build a link.', 'aff-link-cloaker' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Current destination', 'aff-link-cloaker' ); ?></th>
				<td>
					<?php if ( $dest ) : ?>
						<a href="<?php echo esc_url( $dest ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $dest ); ?></a>
					<?php else : ?>
						<span class="alc-warn"><?php esc_html_e( 'No valid destination – this link will 404.', 'aff-link-cloaker' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="alc_redirect_type"><?php esc_html_e( 'Redirect type', 'aff-link-cloaker' ); ?></label></th>
				<td>
					<select id="alc_redirect_type" name="alc_redirect_type">
						<option value=""><?php esc_html_e( 'Site default', 'aff-link-cloaker' ); ?></option>
						<?php foreach ( ALC_Settings::redirect_types() as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( (int) $m['redirect_type'], $code ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="alc_offer_url"><?php esc_html_e( 'Offer / sales page', 'aff-link-cloaker' ); ?></label></th>
				<td><input type="url" id="alc_offer_url" name="alc_offer_url" value="<?php echo esc_attr( $m['offer_url'] ); ?>" class="large-text" /></td>
			</tr>
			<tr>
				<th><label for="alc_image"><?php esc_html_e( 'Image URL', 'aff-link-cloaker' ); ?></label></th>
				<td>
					<input type="url" id="alc_image" name="alc_image" value="<?php echo esc_attr( $m['image'] ); ?>" class="large-text" />
					<?php if ( $m['image'] ) : ?>
						<p><img src="<?php echo esc_url( $m['image'] ); ?>" alt="" class="alc-preview" /></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php if ( $cloaked ) : ?>
			<p><strong><?php esc_html_e( 'Shortcode:', 'aff-link-cloaker' ); ?></strong> <code>[alc_link slug="<?php echo esc_html( $post->post_name ); ?>"]</code></p>
		<?php endif; ?>
		<?php
	}

	public static function render_stats_box( $post ) {
		printf(
			'<p>%s <strong>%d</strong><br>%s <strong>%d</strong><br>%s <strong>%d</strong></p>',
			esc_html__( 'Total:', 'aff-link-cloaker' ),
			(int) ALC_Clicks::count( $post->ID ),
			esc_html__( 'Last 30 days:', 'aff-link-cloaker' ),
			(int) ALC_Clicks::count( $post->ID, 30 ),
			esc_html__( 'Last 7 days:', 'aff-link-cloaker' ),
			(int) ALC_Clicks::count( $post->ID, 7 )
		);
	}

	private static function copy_field( $url ) {
		printf(
			'<span class="alc-copy"><input type="text" readonly value="%1$s" class="regular-text" onfocus="this.select()" /> <button type="button" class="button alc-copy-btn" data-url="%1$s">%2$s</button></span>',
			esc_attr( $url ),
			esc_html__( 'Copy', 'aff-link-cloaker' )
		);
	}

	private static function nonce_ok() {
		return isset( $_POST['alc_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['alc_nonce'] ) ), 'alc_save_link' );
	}

	/** Let users edit post_name (CPT isn't public so WP shows no permalink editor). */
	public static function filter_slug( $data, $postarr ) {
		if ( ALC_Post_Type::POST_TYPE !== $data['post_type'] || ! self::nonce_ok() || ! isset( $_POST['alc_slug'] ) ) {
			return $data;
		}
		$slug = sanitize_title( wp_unslash( $_POST['alc_slug'] ) );
		if ( '' !== $slug && ! empty( $postarr['ID'] ) ) {
			$data['post_name'] = wp_unique_post_slug( $slug, $postarr['ID'], $data['post_status'], $data['post_type'], 0 );
		} elseif ( '' !== $slug ) {
			$data['post_name'] = $slug;
		}
		return $data;
	}

	public static function save_meta( $post_id, $post ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! self::nonce_ok() || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$source = isset( $_POST['alc_source'] ) ? sanitize_key( wp_unslash( $_POST['alc_source'] ) ) : '';
		if ( $source && ! ALC_Plugin::instance()->sources->get( $source ) ) {
			$source = '';
		}
		ALC_Post_Type::set_meta( $post_id, 'source', $source );

		$offer = isset( $_POST['alc_offer_id'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', wp_unslash( $_POST['alc_offer_id'] ) ) : '';
		ALC_Post_Type::set_meta( $post_id, 'offer_id', $offer );

		foreach ( array( 'target_url', 'offer_url', 'image' ) as $k ) {
			$v = isset( $_POST[ 'alc_' . $k ] ) ? esc_url_raw( wp_unslash( $_POST[ 'alc_' . $k ] ), array( 'http', 'https' ) ) : '';
			ALC_Post_Type::set_meta( $post_id, $k, $v );
		}

		$type = isset( $_POST['alc_redirect_type'] ) ? absint( $_POST['alc_redirect_type'] ) : 0;
		ALC_Post_Type::set_meta( $post_id, 'redirect_type', array_key_exists( $type, ALC_Settings::redirect_types() ) ? $type : '' );
	}

	/* ------------------------------------------------------------------ */
	/* List table                                                          */
	/* ------------------------------------------------------------------ */

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $label ) {
			$new[ $k ] = $label;
			if ( 'title' === $k ) {
				$new['alc_url']    = __( 'Cloaked URL', 'aff-link-cloaker' );
				$new['alc_source'] = __( 'Source', 'aff-link-cloaker' );
				$new['alc_clicks'] = __( 'Clicks (30d / total)', 'aff-link-cloaker' );
			}
		}
		return $new;
	}

	public static function column( $col, $post_id ) {
		switch ( $col ) {
			case 'alc_url':
				if ( 'publish' === get_post_status( $post_id ) ) {
					self::copy_field( ALC_Post_Type::cloaked_url( $post_id ) );
				} else {
					echo '&mdash;';
				}
				break;
			case 'alc_source':
				$slug   = ALC_Post_Type::get_meta( $post_id, 'source' );
				$source = $slug ? ALC_Plugin::instance()->sources->get( $slug ) : null;
				echo esc_html( $source ? $source->get_label() . ' · ' . ALC_Post_Type::get_meta( $post_id, 'offer_id' ) : __( 'Manual', 'aff-link-cloaker' ) );
				break;
			case 'alc_clicks':
				echo (int) ALC_Clicks::count( $post_id, 30 ) . ' / ' . (int) ALC_Clicks::count( $post_id );
				break;
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( ALC_Post_Type::POST_TYPE === $post->post_type && ALC_Post_Type::get_meta( $post->ID, 'source' ) && current_user_can( 'edit_post', $post->ID ) ) {
			$url                   = wp_nonce_url( admin_url( 'admin-post.php?action=alc_refresh&post=' . $post->ID ), 'alc_refresh_' . $post->ID );
			$actions['alc_refresh'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Refresh offer data', 'aff-link-cloaker' ) . '</a>';
		}
		return $actions;
	}

	/* ------------------------------------------------------------------ */
	/* Import                                                              */
	/* ------------------------------------------------------------------ */

	public static function render_import() {
		$sources = ALC_Plugin::instance()->sources->all();
		$terms   = get_terms( array( 'taxonomy' => ALC_Post_Type::TAXONOMY, 'hide_empty' => false ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Offers', 'aff-link-cloaker' ); ?></h1>
			<?php foreach ( $sources as $s ) : ?>
				<?php if ( ! $s->is_configured() ) : ?>
					<div class="notice notice-warning inline"><p>
						<?php
						printf(
							/* translators: 1: source name, 2: settings link */
							esc_html__( 'Your %1$s affiliate ID isn’t set yet. %2$s, or paste full affiliate links below.', 'aff-link-cloaker' ),
							esc_html( $s->get_label() ),
							'<a href="' . esc_url( admin_url( self::parent_slug() . '&page=' . ALC_Settings::PAGE ) ) . '">' . esc_html__( 'Add it in Settings', 'aff-link-cloaker' ) . '</a>'
						);
						?>
					</p></div>
				<?php endif; ?>
			<?php endforeach; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="alc_import" />
				<?php wp_nonce_field( 'alc_import', 'alc_import_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="alc_import_source"><?php esc_html_e( 'Source', 'aff-link-cloaker' ); ?></label></th>
						<td>
							<select id="alc_import_source" name="source">
								<?php foreach ( $sources as $s ) : ?>
									<option value="<?php echo esc_attr( $s->get_slug() ); ?>"><?php echo esc_html( $s->get_label() ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="alc_import_lines"><?php esc_html_e( 'Offers', 'aff-link-cloaker' ); ?></label></th>
						<td>
							<textarea id="alc_import_lines" name="lines" rows="8" class="large-text code" placeholder="https://warriorplus.com/o2/a/abc123/456789&#10;abc123 | https://vendor-sales-page.com/"></textarea>
							<p class="description">
								<?php
								/* translators: %d: max lines */
								printf( esc_html__( 'One per line (up to %d): an affiliate link, a WarriorPlus offer URL, or an offer code. Optionally add “| sales page URL” to read the title, description and image from the vendor page.', 'aff-link-cloaker' ), (int) self::BULK_LIMIT );
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Options', 'aff-link-cloaker' ); ?></th>
						<td>
							<label><input type="checkbox" name="fetch" value="1" checked /> <?php esc_html_e( 'Read offer details (title, description, image) from the source site', 'aff-link-cloaker' ); ?></label><br>
							<label><input type="checkbox" name="draft" value="1" /> <?php esc_html_e( 'Import as drafts', 'aff-link-cloaker' ); ?></label>
						</td>
					</tr>
					<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
					<tr>
						<th><label for="alc_import_cat"><?php esc_html_e( 'Category', 'aff-link-cloaker' ); ?></label></th>
						<td>
							<select id="alc_import_cat" name="category">
								<option value="0">&mdash;</option>
								<?php foreach ( $terms as $t ) : ?>
									<option value="<?php echo esc_attr( $t->term_id ); ?>"><?php echo esc_html( $t->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				<?php submit_button( __( 'Import & Create Links', 'aff-link-cloaker' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_import() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'aff-link-cloaker' ), 403 );
		}
		check_admin_referer( 'alc_import', 'alc_import_nonce' );

		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$rows   = ALC_Importer::parse_bulk( isset( $_POST['lines'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lines'] ) ) : '' );
		$rows   = array_slice( $rows, 0, self::BULK_LIMIT );
		$status = ! empty( $_POST['draft'] ) || ! current_user_can( 'publish_posts' ) ? 'draft' : 'publish';

		$results = array();
		foreach ( $rows as $row ) {
			$r = ALC_Importer::import(
				$source,
				$row['input'],
				array(
					'sales_url' => $row['sales_url'],
					'status'    => $status,
					'fetch'     => ! empty( $_POST['fetch'] ),
					'category'  => isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0,
				)
			);
			$results[] = is_wp_error( $r )
				? array( 'input' => $row['input'], 'error' => $r->get_error_message() )
				: array( 'input' => $row['input'], 'post_id' => $r['post_id'], 'created' => $r['created'], 'warning' => $r['warning'] );
		}

		set_transient( 'alc_import_results_' . get_current_user_id(), $results, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( self::parent_slug() . '&alc_imported=1' ) );
		exit;
	}

	public static function handle_refresh() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'alc_refresh_' . $post_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'aff-link-cloaker' ), 403 );
		}

		$source = ALC_Post_Type::get_meta( $post_id, 'source' );
		$offer  = ALC_Post_Type::get_meta( $post_id, 'offer_id' );
		$target = ALC_Post_Type::get_meta( $post_id, 'target_url' );
		$r      = ALC_Importer::import(
			$source,
			$target && false !== strpos( $target, '/o2/a/' ) ? $target : $offer,
			array( 'sales_url' => ALC_Post_Type::get_meta( $post_id, 'offer_url' ) )
		);
		$result = is_wp_error( $r )
			? array( 'input' => $offer, 'error' => $r->get_error_message() )
			: array( 'input' => $offer, 'post_id' => $r['post_id'], 'created' => false, 'warning' => $r['warning'] );

		set_transient( 'alc_import_results_' . get_current_user_id(), array( $result ), 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( self::parent_slug() . '&alc_imported=1' ) );
		exit;
	}

	public static function notices() {
		if ( empty( $_GET['alc_imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$key     = 'alc_import_results_' . get_current_user_id();
		$results = get_transient( $key );
		delete_transient( $key );
		if ( ! $results ) {
			return;
		}
		$ok  = array_filter( $results, static function ( $r ) { return empty( $r['error'] ); } );
		$bad = array_filter( $results, static function ( $r ) { return ! empty( $r['error'] ); } );

		if ( $ok ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			/* translators: %d: count */
			echo esc_html( sprintf( _n( '%d link created or updated:', '%d links created or updated:', count( $ok ), 'aff-link-cloaker' ), count( $ok ) ) );
			echo '</p><ul class="alc-results">';
			foreach ( $ok as $r ) {
				$cloaked = 'publish' === get_post_status( $r['post_id'] ) ? ALC_Post_Type::cloaked_url( $r['post_id'] ) : __( '(draft)', 'aff-link-cloaker' );
				printf(
					'<li><a href="%s">%s</a> → <code>%s</code>%s%s</li>',
					esc_url( get_edit_post_link( $r['post_id'] ) ),
					esc_html( get_the_title( $r['post_id'] ) ),
					esc_html( $cloaked ),
					$r['created'] ? '' : ' <em>' . esc_html__( '(updated)', 'aff-link-cloaker' ) . '</em>',
					$r['warning'] ? ' <span class="alc-warn">' . esc_html( sprintf( /* translators: %s: error */ __( 'Details not read: %s', 'aff-link-cloaker' ), $r['warning'] ) ) . '</span>' : ''
				);
			}
			echo '</ul></div>';
		}
		if ( $bad ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Some offers could not be imported:', 'aff-link-cloaker' ) . '</p><ul class="alc-results">';
			foreach ( $bad as $r ) {
				printf( '<li><code>%s</code> – %s</li>', esc_html( $r['input'] ), esc_html( $r['error'] ) );
			}
			echo '</ul></div>';
		}
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	public static function render_settings() {
		$s    = ALC_Settings::all();
		$name = ALC_Settings::OPTION;
		$flag = static function ( $key, $label ) use ( $s, $name ) {
			printf(
				'<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label><br>',
				esc_attr( $name ),
				esc_attr( $key ),
				checked( $s[ $key ], 1, false ),
				esc_html( $label )
			);
		};
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Link Cloaker Settings', 'aff-link-cloaker' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( ALC_Settings::PAGE ); ?>
				<h2><?php esc_html_e( 'Links', 'aff-link-cloaker' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="alc_prefix"><?php esc_html_e( 'Link prefix', 'aff-link-cloaker' ); ?></label></th>
						<td>
							<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
							<input type="text" id="alc_prefix" name="<?php echo esc_attr( $name ); ?>[prefix]" value="<?php echo esc_attr( $s['prefix'] ); ?>" class="small-text" />
							<code>/your-slug/</code>
							<p class="description"><?php esc_html_e( 'Changing this breaks links you have already shared elsewhere.', 'aff-link-cloaker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="alc_redirect"><?php esc_html_e( 'Default redirect', 'aff-link-cloaker' ); ?></label></th>
						<td>
							<select id="alc_redirect" name="<?php echo esc_attr( $name ); ?>[redirect_type]">
								<?php foreach ( ALC_Settings::redirect_types() as $code => $label ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( (int) $s['redirect_type'], $code ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Shortcode links', 'aff-link-cloaker' ); ?></th>
						<td>
							<?php
							$flag( 'nofollow', __( 'Add rel="nofollow"', 'aff-link-cloaker' ) );
							$flag( 'sponsored', __( 'Add rel="sponsored" (recommended by Google for affiliate links)', 'aff-link-cloaker' ) );
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
				</table>

				<h2>WarriorPlus</h2>
				<table class="form-table">
					<tr>
						<th><label for="alc_wp_aff"><?php esc_html_e( 'Affiliate ID', 'aff-link-cloaker' ); ?></label></th>
						<td>
							<input type="text" id="alc_wp_aff" name="<?php echo esc_attr( $name ); ?>[wp_affiliate_id]" value="<?php echo esc_attr( $s['wp_affiliate_id'] ); ?>" class="regular-text" />
							<p class="description"><?php echo wp_kses_post( __( 'The last part of any of your WarriorPlus affiliate links: <code>https://warriorplus.com/o2/a/OFFER/<strong>THIS</strong></code>', 'aff-link-cloaker' ) ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
