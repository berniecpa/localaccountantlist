<?php
/**
 * Front-end search, filters, and boosted-first ordering for listing archives.
 *
 * Filters arrive as GET params on the listing archive / taxonomy pages:
 *   llh_s        keyword
 *   llh_area     service_area term slug
 *   llh_vehicle  vehicle_type term slug
 *   llh_capacity minimum passenger capacity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLH_Search {

	public static function init() {
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_listings' ) );
	}

	/**
	 * Whether a query is one of ours to shape: the listing archive or one of
	 * the listing taxonomies.
	 */
	protected static function is_listing_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return false;
		}
		return $query->is_post_type_archive( 'limo_listing' )
			|| $query->is_tax( array( 'service_area', 'vehicle_type', 'amenities' ) );
	}

	public static function filter_listings( $query ) {
		if ( ! self::is_listing_query( $query ) ) {
			return;
		}

		$keyword  = isset( $_GET['llh_s'] ) ? sanitize_text_field( wp_unslash( $_GET['llh_s'] ) ) : '';
		$area     = isset( $_GET['llh_area'] ) ? sanitize_title( wp_unslash( $_GET['llh_area'] ) ) : '';
		$vehicle  = isset( $_GET['llh_vehicle'] ) ? sanitize_title( wp_unslash( $_GET['llh_vehicle'] ) ) : '';
		$capacity = isset( $_GET['llh_capacity'] ) ? absint( $_GET['llh_capacity'] ) : 0;

		if ( $keyword ) {
			$query->set( 's', $keyword );
		}

		$tax_query = (array) $query->get( 'tax_query' );
		if ( $area ) {
			$tax_query[] = array(
				'taxonomy' => 'service_area',
				'field'    => 'slug',
				'terms'    => $area,
			);
		}
		if ( $vehicle ) {
			$tax_query[] = array(
				'taxonomy' => 'vehicle_type',
				'field'    => 'slug',
				'terms'    => $vehicle,
			);
		}
		if ( $tax_query ) {
			$query->set( 'tax_query', $tax_query );
		}

		// Boosted-first ordering. Every listing carries _llh_boost_active
		// ("1"/"0", backfilled on save), and the NOT EXISTS arm keeps any
		// stragglers in the result set (MySQL sorts their NULL last on DESC).
		$meta_query = array(
			'relation'     => 'AND',
			'boost_clause' => array(
				'relation'         => 'OR',
				'boost_flag'       => array(
					'key'     => '_llh_boost_active',
					'compare' => 'EXISTS',
				),
				'boost_flag_none'  => array(
					'key'     => '_llh_boost_active',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		if ( $capacity ) {
			$meta_query['capacity_clause'] = array(
				'key'     => '_llh_max_capacity',
				'value'   => $capacity,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}

		$existing = (array) $query->get( 'meta_query' );
		if ( $existing ) {
			$meta_query = array(
				'relation' => 'AND',
				$meta_query,
				$existing,
			);
		}

		$query->set( 'meta_query', $meta_query );
		$query->set(
			'orderby',
			array(
				'boost_flag' => 'DESC',
				'date'       => 'DESC',
			)
		);
	}

	/**
	 * Current filter values, for repopulating filter forms in the theme.
	 */
	public static function current_filters() {
		return array(
			'keyword'  => isset( $_GET['llh_s'] ) ? sanitize_text_field( wp_unslash( $_GET['llh_s'] ) ) : '',
			'area'     => isset( $_GET['llh_area'] ) ? sanitize_title( wp_unslash( $_GET['llh_area'] ) ) : '',
			'vehicle'  => isset( $_GET['llh_vehicle'] ) ? sanitize_title( wp_unslash( $_GET['llh_vehicle'] ) ) : '',
			'capacity' => isset( $_GET['llh_capacity'] ) ? absint( $_GET['llh_capacity'] ) : 0,
		);
	}
}
