<?php
/**
 * Not-found page.
 */

get_header();
?>
<div class="llh-container llh-section" style="text-align:center;padding-top:100px;padding-bottom:100px;">
	<h1><?php esc_html_e( 'Wrong turn', 'limolisthone' ); ?></h1>
	<p style="color:var(--llh-muted);"><?php esc_html_e( 'That page took a detour. Let’s get you back on the road.', 'limolisthone' ); ?></p>
	<p>
		<a class="llh-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'limolisthone' ); ?></a>
		<a class="llh-button llh-button--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'limo_listing' ) ); ?>"><?php esc_html_e( 'Browse limos', 'limolisthone' ); ?></a>
	</p>
</div>
<?php get_footer(); ?>
