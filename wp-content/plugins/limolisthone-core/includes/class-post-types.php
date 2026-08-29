<?php
/**
 * Listing post type, taxonomies, and meta registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLH_Post_Types {

	/**
	 * Listing meta fields editable in admin and via the submission form.
	 * key => array( label, sanitize callback, input type )
	 */
	public static function meta_fields() {
		return array(
			'_llh_phone'        => array( __( 'Phone', 'limolisthone' ), 'sanitize_text_field', 'tel' ),
			'_llh_email'        => array( __( 'Contact email', 'limolisthone' ), 'sanitize_email', 'email' ),
			'_llh_website'      => array( __( 'Website', 'limolisthone' ), 'esc_url_raw', 'url' ),
			'_llh_address'      => array( __( 'Address', 'limolisthone' ), 'sanitize_text_field', 'text' ),
			'_llh_fleet_size'   => array( __( 'Fleet size', 'limolisthone' ), 'absint', 'number' ),
			'_llh_max_capacity' => array( __( 'Max passenger capacity', 'limolisthone' ), 'absint', 'number' ),
			'_llh_price_range'  => array( __( 'Price range', 'limolisthone' ), 'sanitize_text_field', 'text' ),
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'save_post_limo_listing', array( __CLASS__, 'ensure_boost_meta' ), 20 );
	}

	public static function register() {
		register_post_type(
			'limo_listing',
			array(
				'labels'       => array(
					'name'               => __( 'Listings', 'limolisthone' ),
					'singular_name'      => __( 'Listing', 'limolisthone' ),
					'add_new_item'       => __( 'Add New Listing', 'limolisthone' ),
					'edit_item'          => __( 'Edit Listing', 'limolisthone' ),
					'view_item'          => __( 'View Listing', 'limolisthone' ),
					'search_items'       => __( 'Search Listings', 'limolisthone' ),
					'not_found'          => __( 'No listings found.', 'limolisthone' ),
					'all_items'          => __( 'All Listings', 'limolisthone' ),
				),
				'public'       => true,
				'menu_icon'    => 'dashicons-car',
				'menu_position' => 5,
				'has_archive'  => 'limos',
				'rewrite'      => array( 'slug' => 'limo' ),
				'supports'     => array( 'title', 'editor', 'thumbnail', 'comments' ),
				'show_in_rest' => true,
			)
		);

		register_taxonomy(
			'service_area',
			'limo_listing',
			array(
				'labels'            => array(
					'name'          => __( 'Service Areas', 'limolisthone' ),
					'singular_name' => __( 'Service Area', 'limolisthone' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'limo-area', 'hierarchical' => true ),
				'show_in_rest'      => true,
			)
		);

		register_taxonomy(
			'vehicle_type',
			'limo_listing',
			array(
				'labels'            => array(
					'name'          => __( 'Vehicle Types', 'limolisthone' ),
					'singular_name' => __( 'Vehicle Type', 'limolisthone' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'limo-type' ),
				'show_in_rest'      => true,
			)
		);

		register_taxonomy(
			'amenities',
			'limo_listing',
			array(
				'labels'       => array(
					'name'          => __( 'Amenities', 'limolisthone' ),
					'singular_name' => __( 'Amenity', 'limolisthone' ),
				),
				'hierarchical' => false,
				'public'       => true,
				'rewrite'      => array( 'slug' => 'limo-amenity' ),
				'show_in_rest' => true,
			)
		);

		foreach ( array_keys( self::meta_fields() ) as $key ) {
			register_post_meta(
				'limo_listing',
				$key,
				array(
					'type'              => in_array( $key, array( '_llh_fleet_size', '_llh_max_capacity' ), true ) ? 'integer' : 'string',
					'single'            => true,
					'sanitize_callback' => self::meta_fields()[ $key ][1],
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}

		// Private quote-request records so the site owner keeps a copy of every lead.
		register_post_type(
			'llh_quote',
			array(
				'labels'          => array(
					'name'          => __( 'Quote Requests', 'limolisthone' ),
					'singular_name' => __( 'Quote Request', 'limolisthone' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=limo_listing',
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Every listing needs the boost flag present (even as "0") so boosted-first
	 * ordering by this meta key never drops rows.
	 */
	public static function ensure_boost_meta( $post_id ) {
		if ( '' === get_post_meta( $post_id, '_llh_boost_active', true ) ) {
			update_post_meta( $post_id, '_llh_boost_active', '0' );
		}
	}
}
