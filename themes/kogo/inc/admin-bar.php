<?php
/** Kogo branding for the existing site menu in the WordPress toolbar. */

function kogo_admin_bar_site_logo( $admin_bar ) {
	if ( ! $admin_bar->get_node( 'site-name' ) ) {
		return;
	}
	$admin_bar->add_node( array(
		'id'    => 'site-name',
		'title' => '<span class="kogo-admin-bar-logo" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Kogo', 'kogo' ) . '</span>',
		'meta'  => array( 'menu_title' => __( 'Kogo', 'kogo' ) ),
	) );
}
add_action( 'admin_bar_menu', 'kogo_admin_bar_site_logo', 31 );

function kogo_admin_bar_logo_styles() {
	?>
	<style>
		#wpadminbar #wp-admin-bar-site-name > .ab-item { display: flex; align-items: center; width: auto; padding: 0 10px; text-indent: 0; }
		#wpadminbar #wp-admin-bar-site-name > .ab-item:before { content: none; display: none; }
		#wpadminbar #wp-admin-bar-site-name .kogo-admin-bar-logo {
			display: block; width: 50px; height: 22px; color: inherit; background-color: currentColor;
			-webkit-mask: url('<?php echo esc_url( get_theme_file_uri( 'assets/images/icons/logo.svg' ) ); ?>') center / contain no-repeat;
			mask: url('<?php echo esc_url( get_theme_file_uri( 'assets/images/icons/logo.svg' ) ); ?>') center / contain no-repeat;
		}
	</style>
	<?php
}
add_action( 'wp_before_admin_bar_render', 'kogo_admin_bar_logo_styles' );
