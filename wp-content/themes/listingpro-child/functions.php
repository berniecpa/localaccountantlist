<?php
/**
 * ListingPro child theme for locallimoguide.com.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', function () {
	$parent = wp_get_theme( get_template() );
	wp_enqueue_style( 'listingpro-parent', get_template_directory_uri() . '/style.css', array(), $parent->get( 'Version' ) );
	wp_enqueue_style(
		'listingpro-child',
		get_stylesheet_uri(),
		array( 'listingpro-parent' ),
		wp_get_theme()->get( 'Version' )
	);
}, 20 );
