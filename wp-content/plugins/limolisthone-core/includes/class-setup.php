<?php
/**
 * First-run setup: creates the content the theme and forms expect so a fresh
 * install works with zero manual configuration. Everything here is idempotent
 * and runs on plugin activation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLH_Setup {

	/**
	 * Default vehicle types so the submission form and filters are usable
	 * immediately. Site owners can rename/extend under Listings → Vehicle Types.
	 */
	protected static function default_vehicle_types() {
		return array(
			__( 'Stretch Limo', 'limolisthone' ),
			__( 'Party Bus', 'limolisthone' ),
			__( 'SUV Limo', 'limolisthone' ),
			__( 'Executive Sedan', 'limolisthone' ),
			__( 'Sprinter Van', 'limolisthone' ),
			__( 'Classic Car', 'limolisthone' ),
		);
	}

	/**
	 * Run on activation, after post types are registered.
	 */
	public static function install() {
		self::create_submission_page();
		self::seed_vehicle_types();
	}

	/**
	 * The theme links to the `get-listed` slug; create that page with the
	 * submission shortcode if it doesn't exist yet (trashed copies count as
	 * existing only if restorable — wp_insert_post below skips when found).
	 */
	protected static function create_submission_page() {
		if ( get_page_by_path( 'get-listed' ) ) {
			return;
		}

		wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Get Listed', 'limolisthone' ),
				'post_name'    => 'get-listed',
				'post_content' => "<!-- wp:paragraph -->\n<p>" . __( 'List your limo or chauffeur service in our directory — free. Fill out the form below and our team will review and publish your listing.', 'limolisthone' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[limo_submit_listing]\n<!-- /wp:shortcode -->",
			)
		);
	}

	protected static function seed_vehicle_types() {
		foreach ( self::default_vehicle_types() as $name ) {
			if ( ! term_exists( $name, 'vehicle_type' ) ) {
				wp_insert_term( $name, 'vehicle_type' );
			}
		}
	}
}
