<?php
/**
 * Stripe boost subscriptions.
 *
 * A company clicks "Boost this listing", pays through Stripe Checkout
 * (subscription mode), and the listing gains boosted placement while the
 * subscription stays paid. Webhooks keep the boost flag in sync; a daily
 * cron sweep (see main plugin file) catches missed webhooks via the expiry
 * timestamp, which each paid invoice pushes forward.
 *
 * Talks to the Stripe REST API directly with wp_remote_post — no SDK.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLH_Stripe {

	const OPTION = 'llh_stripe';

	/** Each paid cycle grants ~1 month plus grace for retries/webhook lag. */
	const BOOST_PERIOD = 35 * DAY_IN_SECONDS;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_llh_boost_checkout', array( __CLASS__, 'handle_checkout' ) );
		add_action( 'admin_post_nopriv_llh_boost_checkout', array( __CLASS__, 'handle_checkout' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_webhook_route' ) );
	}

	public static function settings() {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'secret_key'     => '',
				'price_id'       => '',
				'webhook_secret' => '',
			)
		);
	}

	public static function is_configured() {
		$s = self::settings();
		return $s['secret_key'] && $s['price_id'];
	}

	/* ---------------------------------------------------------------------
	 * Settings page
	 * ------------------------------------------------------------------- */

	public static function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=limo_listing',
			__( 'Boost Settings', 'limolisthone' ),
			__( 'Boost Settings', 'limolisthone' ),
			'manage_options',
			'llh-stripe',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'llh_stripe_group',
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ) )
		);
	}

	public static function sanitize_settings( $input ) {
		$input = (array) $input;
		return array(
			'secret_key'     => isset( $input['secret_key'] ) ? trim( sanitize_text_field( $input['secret_key'] ) ) : '',
			'price_id'       => isset( $input['price_id'] ) ? trim( sanitize_text_field( $input['price_id'] ) ) : '',
			'webhook_secret' => isset( $input['webhook_secret'] ) ? trim( sanitize_text_field( $input['webhook_secret'] ) ) : '',
		);
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Boost Settings (Stripe)', 'limolisthone' ); ?></h1>
			<p>
				<?php esc_html_e( 'Companies pay a recurring Stripe subscription to have their listing shown first in directory results.', 'limolisthone' ); ?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'llh_stripe_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="llh_secret_key"><?php esc_html_e( 'Stripe secret key', 'limolisthone' ); ?></label></th>
						<td>
							<input type="password" id="llh_secret_key" class="regular-text"
								name="<?php echo esc_attr( self::OPTION ); ?>[secret_key]"
								value="<?php echo esc_attr( $s['secret_key'] ); ?>" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'sk_live_… or sk_test_… from your Stripe dashboard (Developers → API keys).', 'limolisthone' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llh_price_id"><?php esc_html_e( 'Boost price ID', 'limolisthone' ); ?></label></th>
						<td>
							<input type="text" id="llh_price_id" class="regular-text"
								name="<?php echo esc_attr( self::OPTION ); ?>[price_id]"
								value="<?php echo esc_attr( $s['price_id'] ); ?>" />
							<p class="description"><?php esc_html_e( 'price_… of a recurring (monthly) Stripe Price for the boost product.', 'limolisthone' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llh_webhook_secret"><?php esc_html_e( 'Webhook signing secret', 'limolisthone' ); ?></label></th>
						<td>
							<input type="password" id="llh_webhook_secret" class="regular-text"
								name="<?php echo esc_attr( self::OPTION ); ?>[webhook_secret]"
								value="<?php echo esc_attr( $s['webhook_secret'] ); ?>" autocomplete="off" />
							<p class="description">
								<?php
								printf(
									/* translators: %s: webhook URL */
									esc_html__( 'whsec_… for a webhook endpoint pointed at %s (events: checkout.session.completed, invoice.paid, customer.subscription.deleted).', 'limolisthone' ),
									'<code>' . esc_html( rest_url( 'limolisthone/v1/stripe-webhook' ) ) . '</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Checkout
	 * ------------------------------------------------------------------- */

	/**
	 * Render the "Boost this listing" button. Called from the theme.
	 */
	public static function render_boost_button( $listing_id ) {
		if ( ! self::is_configured() || llh_is_boosted( $listing_id ) ) {
			return;
		}
		?>
		<form class="llh-boost-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="llh_boost_checkout" />
			<input type="hidden" name="llh_listing_id" value="<?php echo (int) $listing_id; ?>" />
			<?php wp_nonce_field( 'llh_boost_' . $listing_id, 'llh_boost_nonce' ); ?>
			<button type="submit" class="llh-button llh-button--gold">&#9650; <?php esc_html_e( 'Boost this listing', 'limolisthone' ); ?></button>
			<span class="llh-boost-note"><?php esc_html_e( 'Own this listing? Subscribe to appear first in search results.', 'limolisthone' ); ?></span>
		</form>
		<?php
	}

	/**
	 * Create a Stripe Checkout Session and redirect the payer to it.
	 */
	public static function handle_checkout() {
		$listing_id = isset( $_POST['llh_listing_id'] ) ? absint( $_POST['llh_listing_id'] ) : 0;
		$listing    = $listing_id ? get_post( $listing_id ) : null;

		if ( ! $listing || 'limo_listing' !== $listing->post_type || 'publish' !== $listing->post_status ) {
			wp_die( esc_html__( 'Invalid listing.', 'limolisthone' ) );
		}
		if ( ! isset( $_POST['llh_boost_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['llh_boost_nonce'] ), 'llh_boost_' . $listing_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'limolisthone' ) );
		}
		if ( ! self::is_configured() ) {
			wp_die( esc_html__( 'Boosting is not available right now.', 'limolisthone' ) );
		}

		$settings  = self::settings();
		$permalink = get_permalink( $listing );

		$response = wp_remote_post(
			'https://api.stripe.com/v1/checkout/sessions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $settings['secret_key'],
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'mode'                                      => 'subscription',
					'line_items[0][price]'                      => $settings['price_id'],
					'line_items[0][quantity]'                   => 1,
					'client_reference_id'                       => (string) $listing_id,
					'success_url'                               => add_query_arg( 'llh_boost', 'success', $permalink ),
					'cancel_url'                                => add_query_arg( 'llh_boost', 'cancelled', $permalink ),
					'subscription_data[metadata][llh_listing_id]' => (string) $listing_id,
					'metadata[llh_listing_id]'                  => (string) $listing_id,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_die( esc_html__( 'Could not reach the payment provider. Please try again later.', 'limolisthone' ) );
		}

		$session = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $session['url'] ) ) {
			wp_die( esc_html__( 'The payment provider rejected the request. Please try again later.', 'limolisthone' ) );
		}

		// Off-site redirect to Stripe-hosted checkout.
		wp_redirect( $session['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Webhook
	 * ------------------------------------------------------------------- */

	public static function register_webhook_route() {
		register_rest_route(
			'limolisthone/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_webhook' ),
				// Authentication is the Stripe signature check inside the handler.
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function handle_webhook( WP_REST_Request $request ) {
		$payload   = $request->get_body();
		$signature = $request->get_header( 'stripe-signature' );
		$secret    = self::settings()['webhook_secret'];

		if ( ! $secret || ! self::verify_signature( $payload, (string) $signature, $secret ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid signature' ), 400 );
		}

		$event = json_decode( $payload, true );
		if ( empty( $event['type'] ) || empty( $event['data']['object'] ) ) {
			return new WP_REST_Response( array( 'error' => 'malformed event' ), 400 );
		}

		$object = $event['data']['object'];

		switch ( $event['type'] ) {
			case 'checkout.session.completed':
				self::on_checkout_completed( $object );
				break;

			case 'invoice.paid':
				self::on_invoice_paid( $object );
				break;

			case 'customer.subscription.deleted':
				self::on_subscription_deleted( $object );
				break;
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Verify a Stripe-Signature header: HMAC-SHA256 of "{t}.{payload}" with
	 * the endpoint's signing secret, within a 10-minute tolerance.
	 */
	public static function verify_signature( $payload, $header, $secret, $tolerance = 600 ) {
		$timestamp  = 0;
		$signatures = array();

		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === $pair[0] ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}

		if ( ! $timestamp || ! $signatures || abs( time() - $timestamp ) > $tolerance ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}
		return false;
	}

	protected static function on_checkout_completed( $session ) {
		$listing_id = 0;
		if ( ! empty( $session['client_reference_id'] ) ) {
			$listing_id = absint( $session['client_reference_id'] );
		} elseif ( ! empty( $session['metadata']['llh_listing_id'] ) ) {
			$listing_id = absint( $session['metadata']['llh_listing_id'] );
		}
		if ( ! $listing_id || 'limo_listing' !== get_post_type( $listing_id ) ) {
			return;
		}

		if ( ! empty( $session['customer'] ) ) {
			update_post_meta( $listing_id, '_llh_stripe_customer', sanitize_text_field( $session['customer'] ) );
		}
		if ( ! empty( $session['subscription'] ) ) {
			update_post_meta( $listing_id, '_llh_stripe_subscription', sanitize_text_field( $session['subscription'] ) );
		}

		llh_set_boost( $listing_id, time() + self::BOOST_PERIOD );
	}

	protected static function on_invoice_paid( $invoice ) {
		$listing_id = self::listing_for_invoice( $invoice );
		if ( $listing_id ) {
			llh_set_boost( $listing_id, time() + self::BOOST_PERIOD );
		}
	}

	protected static function on_subscription_deleted( $subscription ) {
		$listing_id = 0;
		if ( ! empty( $subscription['metadata']['llh_listing_id'] ) ) {
			$listing_id = absint( $subscription['metadata']['llh_listing_id'] );
		}
		if ( ! $listing_id && ! empty( $subscription['id'] ) ) {
			$listing_id = self::find_listing_by_subscription( $subscription['id'] );
		}
		if ( $listing_id && 'limo_listing' === get_post_type( $listing_id ) ) {
			llh_clear_boost( $listing_id );
			delete_post_meta( $listing_id, '_llh_stripe_subscription' );
		}
	}

	/**
	 * Resolve an invoice to a listing: subscription metadata first, then the
	 * subscription ID stored on the listing. Field locations vary across
	 * Stripe API versions, so check both classic and 2025+ shapes.
	 */
	protected static function listing_for_invoice( $invoice ) {
		$details = array();
		if ( ! empty( $invoice['subscription_details']['metadata'] ) ) {
			$details = $invoice['subscription_details']['metadata'];
		} elseif ( ! empty( $invoice['parent']['subscription_details']['metadata'] ) ) {
			$details = $invoice['parent']['subscription_details']['metadata'];
		}
		if ( ! empty( $details['llh_listing_id'] ) ) {
			$listing_id = absint( $details['llh_listing_id'] );
			if ( 'limo_listing' === get_post_type( $listing_id ) ) {
				return $listing_id;
			}
		}

		$subscription_id = '';
		if ( ! empty( $invoice['subscription'] ) && is_string( $invoice['subscription'] ) ) {
			$subscription_id = $invoice['subscription'];
		} elseif ( ! empty( $invoice['parent']['subscription_details']['subscription'] ) ) {
			$subscription_id = $invoice['parent']['subscription_details']['subscription'];
		}

		return $subscription_id ? self::find_listing_by_subscription( $subscription_id ) : 0;
	}

	protected static function find_listing_by_subscription( $subscription_id ) {
		$found = get_posts(
			array(
				'post_type'      => 'limo_listing',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_llh_stripe_subscription',
				'meta_value'     => $subscription_id,
			)
		);
		return $found ? (int) $found[0] : 0;
	}
}
