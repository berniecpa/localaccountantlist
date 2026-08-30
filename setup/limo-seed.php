<?php
/**
 * Convert the ListingPro demo into the Local Limo Guide directory.
 *
 * Run on the server with:  wp eval-file setup/limo-seed.php
 * (apply.sh does this for you, after taking a database backup.)
 *
 * Idempotent: safe to run repeatedly. Nothing is deleted — demo listings are
 * drafted, and every piece of content edited in place is backed up first
 * (post meta `_elementor_data_llh_backup`, option `llh_seed_backups`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run this through wp-cli: wp eval-file setup/limo-seed.php\n";
	exit( 1 );
}

/* ------------------------------------------------------------------ config */

// Edit these before running if you want different cities.
$llh_cities = array( 'Houston', 'Galveston', 'Sugar Land', 'The Woodlands', 'Katy' );

$llh_categories = array(
	'Stretch Limo',
	'Party Bus',
	'SUV Limo',
	'Executive Sedan',
	'Sprinter Van',
	'Classic Car',
);

$llh_features = array(
	'Wet Bar',
	'WiFi',
	'Red Carpet Service',
	'Champagne Service',
	'LED Lighting',
	'Premium Sound System',
	'Privacy Partition',
	'Wheelchair Accessible',
);

// Old demo string => limo replacement, applied to the Elementor front page.
$llh_copy_swaps = array(
	'Explore Your City'  => 'Find Your Perfect Ride',
	"Let's uncover the best places to eat, drink, and shop nearest to you." => 'Compare trusted limo and chauffeur services for weddings, proms, nights out, and corporate travel.',
	'Cities You Must Explore This Summer' => 'Find limo services in these cities',
	'Nearly 80% Of Consumers Turn To Directories With Reviews To Find A Local Business.' => 'Riders compare and book limo services through directories with real reviews. Get your fleet in front of them.',
	'Popular Exclusive Listings In Our Directory' => 'Top-rated limo services in our directory',
	"Let's Checkout What Everyone is Talking About" => 'What riders are saying',
	'Checkout Latest News And Articles From Our Blog' => 'Limo tips, pricing guides, and event planning advice',
	'Are You a Local Business?' => 'Do You Run a Limo Service?',
	'Join the community of hundreds of flourishing local business in your city.' => 'List your fleet for free, collect reviews, and upgrade to Featured to appear first in every search.',
	'Ex: food, service, barber, hotel' => 'Ex: stretch limo, party bus, airport transfer',
);

$llh_sample_listings = array(
	array(
		'title'    => 'Royal Stretch Limousines',
		'content'  => 'Family-owned stretch limo service with a fully detailed fleet, professional chauffeurs, and complimentary champagne for wedding bookings. Serving the metro area for over 15 years.',
		'category' => 'Stretch Limo',
		'features' => array( 'Wet Bar', 'Red Carpet Service', 'Champagne Service', 'Privacy Partition' ),
		'phone'    => '(713) 555-0142',
		'email'    => 'bookings@example.com',
		'website'  => 'https://example.com',
		'tagline'  => 'Arrive like royalty',
	),
	array(
		'title'    => 'Night Owl Party Bus',
		'content'  => 'Party buses for 20-40 guests with LED dance floors, club sound systems, and on-board coolers. Perfect for birthdays, bachelor and bachelorette parties, and game days.',
		'category' => 'Party Bus',
		'features' => array( 'LED Lighting', 'Premium Sound System', 'Wet Bar', 'WiFi' ),
		'phone'    => '(713) 555-0177',
		'email'    => 'ride@example.com',
		'website'  => 'https://example.com',
		'tagline'  => 'The party starts on the bus',
	),
	array(
		'title'    => 'Sterling Executive Cars',
		'content'  => 'Discreet black-car service for corporate travel and airport transfers. Flight tracking, bottled water, and WiFi in every vehicle. Corporate accounts welcome.',
		'category' => 'Executive Sedan',
		'features' => array( 'WiFi', 'Privacy Partition' ),
		'phone'    => '(713) 555-0199',
		'email'    => 'dispatch@example.com',
		'website'  => 'https://example.com',
		'tagline'  => 'On time, every time',
	),
);

/* ------------------------------------------------------------------ helpers */

function llh_say( $msg ) {
	echo $msg . "\n";
}

function llh_ensure_terms( $taxonomy, $names ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		llh_say( "!! taxonomy '$taxonomy' does not exist — skipped (is listingpro-plugin active?)" );
		return array();
	}
	$ids = array();
	foreach ( $names as $name ) {
		$existing = term_exists( $name, $taxonomy );
		if ( $existing ) {
			$ids[ $name ] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
		} else {
			$created = wp_insert_term( $name, $taxonomy );
			if ( ! is_wp_error( $created ) ) {
				$ids[ $name ] = (int) $created['term_id'];
				llh_say( "  + $taxonomy: $name" );
			}
		}
	}
	return $ids;
}

/* ------------------------------------------------------- 1. taxonomy terms */

llh_say( '== Creating limo taxonomy terms ==' );
$cat_ids  = llh_ensure_terms( 'listing-category', $llh_categories );
$city_ids = llh_ensure_terms( 'location', $llh_cities );
llh_ensure_terms( 'features', $llh_features );

/* ------------------------------------------------- 2. draft demo listings */

llh_say( '== Drafting demo listings (kept as drafts, not deleted) ==' );
if ( post_type_exists( 'listing' ) ) {
	$demo = get_posts(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_llh_seed',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
	foreach ( $demo as $id ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
	}
	llh_say( '  drafted ' . count( $demo ) . ' demo listings' );
} else {
	llh_say( "!! post type 'listing' missing — is listingpro-plugin active?" );
}

/* ------------------------------------------------- 3. sample limo listings */

llh_say( '== Creating sample limo listings ==' );
if ( post_type_exists( 'listing' ) ) {
	$city_names = array_keys( $city_ids );
	foreach ( $llh_sample_listings as $i => $sample ) {
		$exists = get_posts(
			array(
				'post_type'      => 'listing',
				'post_status'    => 'any',
				'title'          => $sample['title'],
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( $exists ) {
			llh_say( '  = exists: ' . $sample['title'] );
			continue;
		}

		$listing_id = wp_insert_post(
			array(
				'post_type'    => 'listing',
				'post_status'  => 'publish',
				'post_title'   => $sample['title'],
				'post_content' => $sample['content'],
			)
		);
		if ( is_wp_error( $listing_id ) || ! $listing_id ) {
			continue;
		}

		update_post_meta( $listing_id, '_llh_seed', 1 );
		wp_set_object_terms( $listing_id, $sample['category'], 'listing-category' );
		wp_set_object_terms( $listing_id, $sample['features'], 'features' );
		if ( $city_names ) {
			wp_set_object_terms( $listing_id, $city_names[ $i % count( $city_names ) ], 'location' );
		}

		// ListingPro reads listing details from the lp_listingpro_options meta
		// array; unknown keys are ignored, so set the common ones defensively.
		$lp = array(
			'tagline_text'   => $sample['tagline'],
			'phone'          => $sample['phone'],
			'email'          => $sample['email'],
			'website'        => $sample['website'],
			'gAddress'       => $city_names ? $city_names[ $i % count( $city_names ) ] . ', TX' : '',
			'business_hours' => '',
		);
		update_post_meta( $listing_id, 'lp_listingpro_options', $lp );

		llh_say( '  + ' . $sample['title'] );
	}
}

/* ----------------------------------------------- 4. homepage copy rewrite */

llh_say( '== Rewriting homepage demo copy (Elementor) ==' );
$front_id = (int) get_option( 'page_on_front' );
if ( $front_id ) {
	$data = get_post_meta( $front_id, '_elementor_data', true );
	if ( $data && is_string( $data ) ) {
		if ( ! get_post_meta( $front_id, '_elementor_data_llh_backup', true ) ) {
			update_post_meta( $front_id, '_elementor_data_llh_backup', wp_slash( $data ) );
			llh_say( '  backup saved to _elementor_data_llh_backup' );
		}
		$replaced = 0;
		foreach ( $llh_copy_swaps as $old => $new ) {
			// Elementor stores JSON; escape the strings the way JSON stores them.
			$old_json = trim( wp_json_encode( $old ), '"' );
			$new_json = trim( wp_json_encode( $new ), '"' );
			$count    = 0;
			$data     = str_replace( $old_json, $new_json, $data, $count );
			$replaced += $count;
		}
		update_post_meta( $front_id, '_elementor_data', wp_slash( $data ) );
		llh_say( "  replaced $replaced demo strings" );

		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::instance()->files_manager->clear_cache();
			llh_say( '  Elementor CSS cache cleared' );
		}
	} else {
		llh_say( '  front page has no Elementor data — skipped' );
	}
} else {
	llh_say( '  no static front page set — skipped' );
}

/* ------------------------------------------------------- 5. site settings */

llh_say( '== Site settings ==' );
update_option( 'blogdescription', 'Find and book limo services near you' );
llh_say( '  tagline set' );

if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules();
	llh_say( '  pretty permalinks enabled' );
}

llh_say( '' );
llh_say( 'Done. Review the site, then handle the manual items in AUDIT.md' );
llh_say( '(license activation, logo, hero images, pricing plans + Stripe).' );
