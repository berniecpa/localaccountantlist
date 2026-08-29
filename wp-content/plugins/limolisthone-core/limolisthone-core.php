<?php
/**
 * Plugin Name:       LimoListHone Core
 * Description:       Directory engine for the LimoListHone limo service directory: listings, search, submissions, quotes, reviews, and Stripe-powered boosted placement.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            LimoListHone
 * License:           GPL-2.0-or-later
 * Text Domain:       limolisthone
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LLH_CORE_VERSION', '1.0.0' );
define( 'LLH_CORE_FILE', __FILE__ );
define( 'LLH_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'LLH_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once LLH_CORE_DIR . 'includes/class-post-types.php';
require_once LLH_CORE_DIR . 'includes/class-admin.php';
require_once LLH_CORE_DIR . 'includes/class-search.php';
require_once LLH_CORE_DIR . 'includes/class-submission.php';
require_once LLH_CORE_DIR . 'includes/class-quotes.php';
require_once LLH_CORE_DIR . 'includes/class-reviews.php';
require_once LLH_CORE_DIR . 'includes/class-stripe.php';
require_once LLH_CORE_DIR . 'includes/class-setup.php';

LLH_Post_Types::init();
LLH_Admin::init();
LLH_Search::init();
LLH_Submission::init();
LLH_Quotes::init();
LLH_Reviews::init();
LLH_Stripe::init();

register_activation_hook( __FILE__, 'llh_core_activate' );
register_deactivation_hook( __FILE__, 'llh_core_deactivate' );

/**
 * Register content types and flush rewrites on activation, and schedule the
 * daily maintenance event that expires lapsed boosts.
 */
function llh_core_activate() {
	LLH_Post_Types::register();
	LLH_Setup::install();
	flush_rewrite_rules();

	if ( ! wp_next_scheduled( 'llh_daily_maintenance' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'llh_daily_maintenance' );
	}
}

function llh_core_deactivate() {
	wp_clear_scheduled_hook( 'llh_daily_maintenance' );
	flush_rewrite_rules();
}

/**
 * Whether a listing currently has active (paid or manual) boosted placement.
 *
 * An expiry of 0 with the boost flag set means "no expiry" (manual boost).
 */
function llh_is_boosted( $listing_id ) {
	if ( '1' !== get_post_meta( $listing_id, '_llh_boost_active', true ) ) {
		return false;
	}
	$expires = (int) get_post_meta( $listing_id, '_llh_boost_expires', true );
	return 0 === $expires || $expires > time();
}

/**
 * Activate boosted placement for a listing.
 *
 * @param int $listing_id Listing post ID.
 * @param int $expires    Unix timestamp when the boost lapses, 0 for never.
 */
function llh_set_boost( $listing_id, $expires ) {
	update_post_meta( $listing_id, '_llh_boost_active', '1' );
	update_post_meta( $listing_id, '_llh_boost_expires', (int) $expires );
}

function llh_clear_boost( $listing_id ) {
	update_post_meta( $listing_id, '_llh_boost_active', '0' );
	delete_post_meta( $listing_id, '_llh_boost_expires' );
}

add_action( 'llh_daily_maintenance', 'llh_expire_lapsed_boosts' );

/**
 * Safety net for missed webhooks: clear the boost flag on listings whose
 * expiry timestamp has passed.
 */
function llh_expire_lapsed_boosts() {
	$expired = get_posts(
		array(
			'post_type'      => 'limo_listing',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_llh_boost_active',
					'value' => '1',
				),
				array(
					'key'     => '_llh_boost_expires',
					'value'   => time(),
					'compare' => '<',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => '_llh_boost_expires',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	foreach ( $expired as $listing_id ) {
		llh_clear_boost( $listing_id );
	}
}
