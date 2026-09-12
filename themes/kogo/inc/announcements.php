<?php
/** Saved site announcements with a single active selection. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function kogo_get_announcements() {
	return wp_parse_args( get_option( 'kogo_announcements', array() ), array( 'items' => array(), 'active_id' => '' ) );
}

add_action( 'admin_menu', function () {
	add_menu_page( __( 'Announcements', 'kogo' ), __( 'Announcements', 'kogo' ), 'edit_pages', 'kogo-announcements', 'kogo_announcements_admin_page', 'dashicons-megaphone', 30 );
} );

/** All mutations pass through this nonce-protected editor endpoint. */
function kogo_save_announcement() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage announcements.', 'kogo' ) );
	}
	check_admin_referer( 'kogo_announcement' );
	foreach ( array( 'announcement_id', 'announcement_action', 'message', 'link_url', 'link_label', 'active' ) as $field ) {
		if ( isset( $_POST[ $field ] ) && ! is_string( $_POST[ $field ] ) ) {
			wp_die( esc_html__( 'Invalid announcement form. Reload the page and try again.', 'kogo' ) );
		}
	}
	$data   = kogo_get_announcements();
	$id     = sanitize_key( wp_unslash( $_POST['announcement_id'] ?? '' ) );
	$action = sanitize_key( wp_unslash( $_POST['announcement_action'] ?? '' ) );

	if ( $id && ! isset( $data['items'][ $id ] ) ) {
		wp_die( esc_html__( 'This announcement no longer exists. Reload the page and try again.', 'kogo' ) );
	}

	if ( 'save' === $action ) {
		$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		$url     = esc_url_raw( wp_unslash( $_POST['link_url'] ?? '' ), array( 'http', 'https' ) );
		if ( '' === trim( $message ) || ( ! empty( $_POST['link_url'] ) && ! $url ) ) {
			wp_die( esc_html__( 'Enter a message and a valid link, or leave the link empty.', 'kogo' ), '', array( 'back_link' => true ) );
		}
		$id = $id ?: wp_generate_uuid4();
		$data['items'][ $id ] = array(
			'message'    => $message,
			'link_url'   => $url,
			'link_label' => sanitize_text_field( wp_unslash( $_POST['link_label'] ?? '' ) ) ?: __( 'More info', 'kogo' ),
		);
		if ( ! empty( $_POST['active'] ) ) {
			$data['active_id'] = $id;
		} elseif ( $id === $data['active_id'] ) {
			$data['active_id'] = '';
		}
	} elseif ( $id && in_array( $action, array( 'activate', 'deactivate', 'delete' ), true ) ) {
		if ( 'activate' === $action ) {
			$data['active_id'] = $id;
		} elseif ( $id === $data['active_id'] ) {
			$data['active_id'] = '';
		}
		if ( 'delete' === $action ) {
			unset( $data['items'][ $id ] );
		}
	} else {
		wp_die( esc_html__( 'Choose a valid announcement action.', 'kogo' ) );
	}

	// A single option write keeps the saved items and active choice together.
	update_option( 'kogo_announcements', $data );
	wp_safe_redirect( admin_url( 'admin.php?page=kogo-announcements&saved=1' ) );
	exit;
}
add_action( 'admin_post_kogo_announcement', 'kogo_save_announcement' );

function kogo_announcement_action_fields( $id, $action ) {
	wp_nonce_field( 'kogo_announcement' );
	echo '<input type="hidden" name="action" value="kogo_announcement"><input type="hidden" name="announcement_id" value="' . esc_attr( $id ) . '"><input type="hidden" name="announcement_action" value="' . esc_attr( $action ) . '">';
}

function kogo_announcements_admin_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$data    = kogo_get_announcements();
	$edit_id = isset( $_GET['edit'] ) && is_string( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
	$edit_id = isset( $data['items'][ $edit_id ] ) ? $edit_id : '';
	$item    = $data['items'][ $edit_id ] ?? array( 'message' => '', 'link_url' => '', 'link_label' => __( 'More info', 'kogo' ) );
	$active  = $edit_id ? $edit_id === $data['active_id'] : ! $data['active_id'];
	?>
	<div class="wrap" style="max-width: 1000px">
		<h1><?php esc_html_e( 'Announcements', 'kogo' ); ?></h1>
		<p><?php esc_html_e( 'Show a short message above the site header. Only one announcement can be active. Visitors can dismiss it for the rest of their browser session.', 'kogo' ); ?></p>
		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Announcements updated.', 'kogo' ); ?></p></div>
		<?php endif; ?>
		<?php if ( $data['items'] ) : ?>
			<h2><?php esc_html_e( 'Saved announcements', 'kogo' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th scope="col"><?php esc_html_e( 'Message', 'kogo' ); ?></th><th scope="col"><?php esc_html_e( 'Status', 'kogo' ); ?></th><th scope="col"><?php esc_html_e( 'Actions', 'kogo' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( array_reverse( $data['items'], true ) as $id => $saved ) : ?>
					<tr>
						<td style="overflow-wrap: anywhere; max-width: 520px"><?php echo esc_html( $saved['message'] ); ?></td>
						<td><?php echo $id === $data['active_id'] ? '<strong>' . esc_html__( 'Active', 'kogo' ) . '</strong>' : esc_html__( 'Inactive', 'kogo' ); ?></td>
						<td style="min-width: 190px">
							<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'kogo-announcements', 'edit' => $id ), admin_url( 'admin.php' ) ) . '#announcement-editor' ); ?>"><?php esc_html_e( 'Edit', 'kogo' ); ?></a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline">
								<?php kogo_announcement_action_fields( $id, $id === $data['active_id'] ? 'deactivate' : 'activate' ); ?>
								<button class="button button-small" type="submit"><?php echo $id === $data['active_id'] ? esc_html__( 'Hide', 'kogo' ) : esc_html__( 'Show', 'kogo' ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline">
								<?php kogo_announcement_action_fields( $id, 'delete' ); ?>
								<button class="button-link-delete" style="margin-left: 8px" type="submit"><?php esc_html_e( 'Delete', 'kogo' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No announcements yet. Create your first one below.', 'kogo' ); ?></p>
		<?php endif; ?>
		<p class="description"><?php echo $data['active_id'] ? esc_html__( 'The active announcement is showing on all pages.', 'kogo' ) : esc_html__( 'No announcement is currently showing.', 'kogo' ); ?></p>
		<h2 id="announcement-editor"><?php echo $edit_id ? esc_html__( 'Edit announcement', 'kogo' ) : esc_html__( 'Add announcement', 'kogo' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php kogo_announcement_action_fields( $edit_id, 'save' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="announcement-message"><?php esc_html_e( 'Message', 'kogo' ); ?></label></th><td><textarea class="large-text" id="announcement-message" name="message" rows="3" required><?php echo esc_textarea( $item['message'] ); ?></textarea><p class="description"><?php esc_html_e( 'Keep it short so it is easy to read on mobile. Plain text only.', 'kogo' ); ?></p></td></tr>
				<tr><th scope="row"><label for="announcement-url"><?php esc_html_e( 'Link URL (optional)', 'kogo' ); ?></label></th><td><input class="large-text" type="url" id="announcement-url" name="link_url" placeholder="https://" value="<?php echo esc_attr( $item['link_url'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="announcement-label"><?php esc_html_e( 'Link text', 'kogo' ); ?></label></th><td><input class="regular-text" id="announcement-label" name="link_label" value="<?php echo esc_attr( $item['link_label'] ); ?>"><p class="description"><?php esc_html_e( 'Used only when a link URL is provided.', 'kogo' ); ?></p></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Visibility', 'kogo' ); ?></th><td><label><input type="checkbox" name="active" value="1" <?php checked( $active ); ?>> <?php esc_html_e( 'Show this announcement on all pages', 'kogo' ); ?></label><p class="description"><?php esc_html_e( 'Showing this announcement replaces the currently active one. Both stay saved.', 'kogo' ); ?></p></td></tr>
			</table>
			<?php submit_button( $edit_id ? __( 'Save announcement', 'kogo' ) : __( 'Create announcement', 'kogo' ) ); ?>
			<?php if ( $edit_id ) : ?><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=kogo-announcements#announcement-editor' ) ); ?>"><?php esc_html_e( 'Add a new announcement', 'kogo' ); ?></a></p><?php endif; ?>
		</form>
	</div>
	<?php
}

/** Prepend to the shared header; keep the header's existing sticky behavior. */
function kogo_render_announcement( $content, $block ) {
	if ( is_admin() || 'header' !== ( $block['attrs']['slug'] ?? '' ) ) {
		return $content;
	}
	$data = kogo_get_announcements();
	$item = $data['items'][ $data['active_id'] ] ?? null;
	if ( ! $item || ! $item['message'] ) {
		return $content;
	}
	$GLOBALS['kogo_announcement_rendered'] = true;
	$key = $data['active_id'] . '-' . substr( md5( wp_json_encode( $item ) ), 0, 12 );
	$link = $item['link_url'] ? ' <span aria-hidden="true">•</span> <a href="' . esc_url( $item['link_url'] ) . '">' . esc_html( $item['link_label'] ) . '</a>' : '';
	$message = '<p>' . esc_html( $item['message'] ) . $link . '</p>';
	// A second visual copy makes the mobile marquee loop without a blank gap.
	$copy = '<p class="kogo-announcement__copy" aria-hidden="true">' . esc_html( $item['message'] ) . str_replace( '<a ', '<a tabindex="-1" ', $link ) . '</p>';
	$banner = '<div class="kogo-announcement" role="region" aria-label="' . esc_attr__( 'Announcement', 'kogo' ) . '" data-announcement-key="' . esc_attr( $key ) . '"><div class="kogo-announcement__inner"><div class="kogo-announcement__viewport"><div class="kogo-announcement__track">' . $message . $copy . '</div></div><button class="kogo-announcement__close" type="button" aria-label="' . esc_attr__( 'Dismiss announcement', 'kogo' ) . '"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m3 3 14 14M17 3 3 17" stroke="currentColor" stroke-width="1.5"/></svg></button></div></div>';
	// Check storage before the header paints, so a dismissed banner never flashes.
	$script = '<script>(function(){var banner=document.currentScript.previousElementSibling;try{if(sessionStorage.getItem("kogo-announcement-dismissed:"+banner.dataset.announcementKey)==="1"){banner.hidden=true;}}catch(e){}var button=banner.querySelector("button");button.addEventListener("click",function(){try{sessionStorage.setItem("kogo-announcement-dismissed:"+banner.dataset.announcementKey,"1");}catch(e){}banner.hidden=true;if(document.activeElement===button){var next=document.querySelector("header.site-header a,main a,main button");if(next){next.focus({preventScroll:true});}}});})();</script>';
	return $banner . $script . $content;
}
add_filter( 'render_block_core/template-part', 'kogo_render_announcement', 20, 2 );

/** The FSE canvas renders its blocks first; cover templates without a header. */
function kogo_announcement_body_fallback() {
	if ( empty( $GLOBALS['kogo_announcement_rendered'] ) ) {
		echo kogo_render_announcement( '', array( 'attrs' => array( 'slug' => 'header' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'wp_body_open', 'kogo_announcement_body_fallback' );
