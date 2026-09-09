<?php
/**
 * Convert the ListingPro demo into the Local Accountant List directory
 * (https://localaccountantlist.com).
 *
 * Run on the server with:  wp eval-file setup/seed.php
 * (setup/apply.sh does this for you, after taking a database backup.)
 *
 * Idempotent: safe to run repeatedly. Nothing is deleted — demo listings are
 * drafted, and content edited in place is backed up first
 * (post meta `_elementor_data_llh_backup`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run this through wp-cli: wp eval-file setup/seed.php\n";
	exit( 1 );
}

/* ------------------------------------------------------------------ config */

// Edit before running to change the seeded metro areas.
$lal_cities = array( 'New York', 'Los Angeles', 'Chicago', 'Houston', 'Miami', 'Dallas', 'Atlanta', 'Phoenix' );

$lal_categories = array(
	'Tax Preparation',
	'Bookkeeping',
	'CPA Firm',
	'Payroll Services',
	'Audit & Assurance',
	'Business Advisory',
	'Forensic Accounting',
);

$lal_features = array(
	'IRS Representation',
	'QuickBooks ProAdvisor',
	'Free Consultation',
	'Virtual Appointments',
	'Small Business Specialist',
	'Individual Tax Returns',
	'Spanish Speaking',
	'Year-Round Service',
);

// Old demo string => replacement, applied to the Elementor front page.
$lal_copy_swaps = array(
	'Explore Your City'  => 'Find a Trusted Accountant',
	"Let's uncover the best places to eat, drink, and shop nearest to you." => 'Compare CPAs, tax preparers, bookkeepers, and payroll pros near you — read real client reviews and request a consultation.',
	'Cities You Must Explore This Summer' => 'Find accounting firms in these cities',
	'Happening Cities' => 'Popular Cities',
	'Nearly 80% Of Consumers Turn To Directories With Reviews To Find A Local Business.' => 'Most clients choose a tax or accounting professional after reading reviews online. Make sure they find your firm.',
	'Popular Exclusive Listings In Our Directory' => 'Top-rated accounting firms in our directory',
	"Let's Checkout What Everyone is Talking About" => 'What clients are saying',
	'Checkout Latest News And Articles From Our Blog' => 'Tax tips, filing deadlines, and small business finance advice',
	'Are You a Local Business?' => 'Are You an Accountant or Firm?',
	'Join the community of hundreds of flourishing local business in your city.' => 'List your practice for free, collect client reviews, and upgrade to Featured to appear first in every search.',
	'Ex: food, service, barber, hotel' => 'Ex: CPA, tax preparation, bookkeeping',
);

$lal_sample_listings = array(
	array(
		'title'    => 'Summit Tax & Accounting',
		'content'  => 'Full-service CPA firm handling individual and business tax returns, tax planning, and IRS representation. Enrolled to practice before the IRS, with 20+ years of combined experience and year-round support — not just at filing season.',
		'category' => 'Tax Preparation',
		'features' => array( 'IRS Representation', 'Free Consultation', 'Year-Round Service', 'Individual Tax Returns' ),
		'phone'    => '(212) 555-0148',
		'email'    => 'hello@example.com',
		'website'  => 'https://example.com',
		'tagline'  => 'Taxes done right, year-round',
	),
	array(
		'title'    => 'Ledger & Main Bookkeeping',
		'content'  => 'Monthly bookkeeping, payroll, and clean-up services for small businesses and startups. Certified QuickBooks ProAdvisors who close your books on time, every month, with clear reports you can actually read.',
		'category' => 'Bookkeeping',
		'features' => array( 'QuickBooks ProAdvisor', 'Small Business Specialist', 'Virtual Appointments' ),
		'phone'    => '(312) 555-0186',
		'email'    => 'books@example.com',
		'website'  => 'https://example.com',
		'tagline'  => 'Clean books, clear decisions',
	),
	array(
		'title'    => 'Hartwell CPA Group',
		'content'  => 'Audit, assurance, and advisory services for growing companies and nonprofits. Financial statement audits, reviews, and compilations, plus CFO-level advisory for businesses that have outgrown DIY finances.',
		'category' => 'Audit & Assurance',
		'features' => array( 'Free Consultation', 'Small Business Specialist', 'Year-Round Service' ),
		'phone'    => '(713) 555-0117',
		'email'    => 'info@example.com',
		'website'  => 'https://example.com',
		'tagline'  => 'Assurance you can build on',
	),
);

/* ------------------------------------------------------------------ helpers */

function lal_say( $msg ) {
	echo $msg . "\n";
}

function lal_ensure_terms( $taxonomy, $names ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		lal_say( "!! taxonomy '$taxonomy' does not exist — skipped (is listingpro-plugin active?)" );
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
				lal_say( "  + $taxonomy: $name" );
			}
		}
	}
	return $ids;
}

/* ------------------------------------------------------- 1. taxonomy terms */

lal_say( '== Creating accounting taxonomy terms ==' );
$cat_ids  = lal_ensure_terms( 'listing-category', $lal_categories );
$city_ids = lal_ensure_terms( 'location', $lal_cities );
lal_ensure_terms( 'features', $lal_features );

/* ------------------------------------------------- 2. draft demo listings */

lal_say( '== Drafting demo listings (kept as drafts, not deleted) ==' );
if ( post_type_exists( 'listing' ) ) {
	$demo = get_posts(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_lal_seed',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
	foreach ( $demo as $id ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
	}
	lal_say( '  drafted ' . count( $demo ) . ' demo listings' );
} else {
	lal_say( "!! post type 'listing' missing — is listingpro-plugin active?" );
}

/* -------------------------------------------- 3. sample accounting firms */

lal_say( '== Creating sample accounting firm listings ==' );
if ( post_type_exists( 'listing' ) ) {
	$city_names = array_keys( $city_ids );
	foreach ( $lal_sample_listings as $i => $sample ) {
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
			lal_say( '  = exists: ' . $sample['title'] );
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

		update_post_meta( $listing_id, '_lal_seed', 1 );
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
			'gAddress'       => $city_names ? $city_names[ $i % count( $city_names ) ] : '',
			'business_hours' => '',
		);
		update_post_meta( $listing_id, 'lp_listingpro_options', $lp );

		lal_say( '  + ' . $sample['title'] );
	}
}

/* ----------------------------------------------- 4. homepage copy rewrite */

lal_say( '== Rewriting homepage demo copy (Elementor) ==' );
$front_id = (int) get_option( 'page_on_front' );
if ( $front_id ) {
	$data = get_post_meta( $front_id, '_elementor_data', true );
	if ( $data && is_string( $data ) ) {
		if ( ! get_post_meta( $front_id, '_elementor_data_llh_backup', true ) ) {
			update_post_meta( $front_id, '_elementor_data_llh_backup', wp_slash( $data ) );
			lal_say( '  backup saved to _elementor_data_llh_backup' );
		}
		$replaced = 0;
		foreach ( $lal_copy_swaps as $old => $new ) {
			// Elementor stores JSON; match strings the way JSON escapes them.
			$old_json = trim( wp_json_encode( $old ), '"' );
			$new_json = trim( wp_json_encode( $new ), '"' );
			$count    = 0;
			$data     = str_replace( $old_json, $new_json, $data, $count );
			$replaced += $count;
		}
		update_post_meta( $front_id, '_elementor_data', wp_slash( $data ) );
		lal_say( "  replaced $replaced demo strings" );

		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::instance()->files_manager->clear_cache();
			lal_say( '  Elementor CSS cache cleared' );
		}
	} else {
		lal_say( '  front page has no Elementor data — skipped' );
	}
} else {
	lal_say( '  no static front page set — skipped' );
}

/* ------------------------------------------------------- 5. site settings */

lal_say( '== Site settings ==' );
update_option( 'blogdescription', 'Find trusted CPAs, tax preparers, and bookkeepers near you' );
lal_say( '  tagline set' );

if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules();
	lal_say( '  pretty permalinks enabled' );
}

lal_say( '' );
lal_say( 'Done. Review the site, then handle the manual items in AUDIT.md' );
lal_say( '(logo, hero images, pricing plans + Stripe).' );
