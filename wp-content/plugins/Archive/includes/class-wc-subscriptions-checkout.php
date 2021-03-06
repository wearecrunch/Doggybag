<?php
/**
 * Subscriptions Checkout
 *
 * Extends the WooCommerce checkout class to add subscription meta on checkout.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WC_Subscriptions_Checkout
 * @category	Class
 * @author		Brent Shepherd
 */
class WC_Subscriptions_Checkout {

	private static $signup_option_changed = false;

	private static $guest_checkout_option_changed = false;

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 1.0
	 */
	public static function init() {

		// We need to create subscriptions on checkout and want to do it after almost all other extensions have added their products/items/fees
		add_action( 'woocommerce_checkout_order_processed', __CLASS__ . '::process_checkout', 100, 2 );

		// Make sure users can register on checkout (before any other hooks before checkout)
		add_action( 'woocommerce_before_checkout_form', __CLASS__ . '::make_checkout_registration_possible', -1 );

		// Display account fields as required
		add_action( 'woocommerce_checkout_fields', __CLASS__ . '::make_checkout_account_fields_required', 10 );

		// Restore the settings after switching them for the checkout form
		add_action( 'woocommerce_after_checkout_form', __CLASS__ . '::restore_checkout_registration_settings', 100 );

		// Make sure guest checkout is not enabled in option param passed to WC JS
		add_filter( 'woocommerce_params', __CLASS__ . '::filter_woocommerce_script_paramaters', 10, 1 );
		add_filter( 'wc_checkout_params', __CLASS__ . '::filter_woocommerce_script_paramaters', 10, 1 );

		// Force registration during checkout process
		add_action( 'woocommerce_before_checkout_process', __CLASS__ . '::force_registration_during_checkout', 10 );
	}

	/**
	 * Create subscriptions purchased on checkout.
	 *
	 * @param int $order_id The post_id of a shop_order post/WC_Order object
	 * @param array $posted_data The data posted on checkout
	 * @since 2.0
	 */
	public static function process_checkout( $order_id, $posted_data ) {

		if ( ! WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return;
		}

		$order = new WC_Order( $order_id );

		$subscriptions = array();

		// First clear out any subscriptions created for a failed payment to give us a clean slate for creating new subscriptions
		$subscriptions = wcs_get_subscriptions_for_order( $order->id, array( 'order_type' => 'parent' ) );

		if ( ! empty( $subscriptions ) ) {
			foreach ( $subscriptions as $subscription ) {
				wp_delete_post( $subscription->id );
			}
		}

		// Create new subscriptions for each group of subscription products in the cart (that is not a renewal)
		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {

			$subscription = self::create_subscription( $order, $recurring_cart ); // Exceptions are caught by WooCommerce

			if ( is_wp_error( $subscription ) ) {
				throw new Exception( $subscription->get_error_message() );
			}

			do_action( 'woocommerce_checkout_subscription_created', $subscription, $order, $recurring_cart );
		}

		do_action( 'subscriptions_created_for_order', $order ); // Backward compatibility
	}

	/**
	 * Create a new subscription from a cart item on checkout.
	 *
	 * The function doesn't validate whether the cart item is a subscription product, meaning it can be used for any cart item,
	 * but the item will need a `subscription_period` and `subscription_period_interval` value set on it, at a minimum.
	 *
	 * @param WC_Order $order
	 * @param WC_Cart $cart
	 * @since 2.0
	 */
	public static function create_subscription( $order, $cart ) {
		global $wpdb;

		try {
			// Start transaction if available
			$wpdb->query( 'START TRANSACTION' );

			// Set the recurring line totals on the subscription
			$variation_id = wcs_cart_pluck( $cart, 'variation_id' );
			$product_id   = empty( $variation_id ) ? wcs_cart_pluck( $cart, 'product_id' ) : $variation_id;

			// We need to use the $order->order_date value because the post_date_gmt isn't always set
			$order_date_gmt = get_gmt_from_date( $order->order_date );

			$subscription = wcs_create_subscription( array(
				'start_date'       => $cart->start_date,
				'order_id'         => $order->id,
				'customer_id'      => $order->get_user_id(),
				'billing_period'   => wcs_cart_pluck( $cart, 'subscription_period' ),
				'billing_interval' => wcs_cart_pluck( $cart, 'subscription_period_interval' ),
				'customer_note'    => $order->customer_note,
			) );

			if ( is_wp_error( $subscription ) ) {
				throw new Exception( $subscription->get_error_message() );
			}

			// Set the subscription's billing and shipping address
			$subscription = wcs_copy_order_address( $order, $subscription );

			$subscription->update_dates( array(
				'trial_end'    => $cart->trial_end_date,
				'next_payment' => $cart->next_payment_date,
				'end'          => $cart->end_date,
			) );

			// Store trial period for PayPal
			if ( wcs_cart_pluck( $cart, 'subscription_trial_length' ) > 0 ) {
				update_post_meta( $subscription->id, '_trial_period', wcs_cart_pluck( $cart, 'subscription_trial_period' ) );
			}

			// Set the payment method on the subscription
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

			if ( $cart->needs_payment() && isset( $available_gateways[ $order->payment_method ] ) ) {
				$subscription->set_payment_method( $available_gateways[ $order->payment_method ] );
			}

			if ( ! $cart->needs_payment() || 'yes' == get_option( WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments', 'no' ) ) {
				$subscription->update_manual( 'true' );
			} elseif ( ! isset( $available_gateways[ $order->payment_method ] ) || ! $available_gateways[ $order->payment_method ]->supports( 'subscriptions' ) ) {
				$subscription->update_manual( 'true' );
			}

			wcs_copy_order_meta( $order, $subscription, 'subscription' );

			// Store the line items
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				$item_id = self::add_cart_item( $subscription, $cart_item, $cart_item_key );
			}

			// Store fees (although no fees recur by default, extensions may add them)
			foreach ( $cart->get_fees() as $fee_key => $fee ) {
				$item_id = $subscription->add_fee( $fee );

				if ( ! $item_id ) {
					throw new Exception( sprintf( __( 'Error %d: Unable to create subscription. Please try again.', 'woocommerce-subscriptions' ), 403 ) );
				}

				// Allow plugins to add order item meta to fees
				do_action( 'woocommerce_add_order_fee_meta', $order->id, $item_id, $fee, $fee_key );
			}

			self::add_shipping( $subscription, $cart );

			// Store tax rows
			foreach ( array_keys( $cart->taxes + $cart->shipping_taxes ) as $tax_rate_id ) {
				if ( $tax_rate_id && ! $subscription->add_tax( $tax_rate_id, $cart->get_tax_amount( $tax_rate_id ), $cart->get_shipping_tax_amount( $tax_rate_id ) ) && apply_filters( 'woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated' ) !== $tax_rate_id ) {
					throw new Exception( sprintf( __( 'Error %d: Unable to subscription order. Please try again.', 'woocommerce-subscriptions' ), 405 ) );
				}
			}

			// Store coupons
			foreach ( $cart->get_coupons() as $code => $coupon ) {
				if ( ! $subscription->add_coupon( $code, $cart->get_coupon_discount_amount( $code ), $cart->get_coupon_discount_tax_amount( $code ) ) ) {
					throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce-subscriptions' ), 406 ) );
				}
			}

			// Set the recurring totals on the subscription
			$subscription->set_total( $cart->shipping_total, 'shipping' );
			$subscription->set_total( $cart->get_cart_discount_total(), 'cart_discount' );
			$subscription->set_total( $cart->get_cart_discount_tax_total(), 'cart_discount_tax' );
			$subscription->set_total( $cart->tax_total, 'tax' );
			$subscription->set_total( $cart->shipping_tax_total, 'shipping_tax' );
			$subscription->set_total( $cart->total );

			// If we got here, the subscription was created without problems
			$wpdb->query( 'COMMIT' );

		} catch ( Exception $e ) {
			// There was an error adding the subscription
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'checkout-error', $e->getMessage() );
		}

		return $subscription;
	}


	/**
	 * Stores shipping info on the subscription
	 *
	 * @param WC_Subscription $subscription instance of a subscriptions object
	 * @param WC_Cart $cart A cart with recurring items in it
	 */
	public static function add_shipping( $subscription, $cart ) {

		// We need to make sure we only get recurring shipping packages
		WC_Subscriptions_Cart::set_calculation_type( 'recurring_total' );

		foreach ( $cart->get_shipping_packages() as $base_package ) {

			$package = WC()->shipping->calculate_shipping_for_package( $base_package );

			foreach ( WC()->shipping->get_packages() as $package_key => $package_to_ignore ) {

				if ( isset( $package['rates'][ WC()->checkout()->shipping_methods[ $package_key ] ] ) ) {

					$item_id = $subscription->add_shipping( $package['rates'][ WC()->checkout()->shipping_methods[ $package_key ] ] );

					if ( ! $item_id ) {
						throw new Exception( __( 'Error: Unable to create subscription. Please try again.', 'woocommerce-subscriptions' ) );
					}

					// Allows plugins to add order item meta to shipping
					do_action( 'woocommerce_add_shipping_order_item', $subscription->id, $item_id, $package_key );
					do_action( 'woocommerce_subscriptions_add_recurring_shipping_order_item', $subscription->id, $item_id, $package_key );
				}
			}
		}

		WC_Subscriptions_Cart::set_calculation_type( 'none' );
	}

	/**
	 * Add a cart item to a subscription.
	 *
	 * @since 2.0
	 */
	public static function add_cart_item( $subscription, $cart_item, $cart_item_key ) {
		$item_id = $subscription->add_product(
			$cart_item['data'],
			$cart_item['quantity'],
			array(
				'variation' => $cart_item['variation'],
				'totals'    => array(
					'subtotal'     => $cart_item['line_subtotal'],
					'subtotal_tax' => $cart_item['line_subtotal_tax'],
					'total'        => $cart_item['line_total'],
					'tax'          => $cart_item['line_tax'],
					'tax_data'     => $cart_item['line_tax_data'],
				),
			)
		);

		if ( ! $item_id ) {
			throw new Exception( sprintf( __( 'Error %d: Unable to create subscription. Please try again.', 'woocommerce-subscriptions' ), 402 ) );
		}

		$cart_item_product_id = ( 0 != $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];

		if ( WC_Subscriptions_Product::get_trial_length( wcs_get_canonical_product_id( $cart_item ) ) > 0 ) {
			wc_add_order_item_meta( $item_id, '_has_trial', 'true' );
		}

		// Allow plugins to add order item meta
		do_action( 'woocommerce_add_order_item_meta', $item_id, $cart_item, $cart_item_key );

		do_action( 'woocommerce_add_subscription_item_meta', $item_id, $cart_item, $cart_item_key );

		return $item_id;
	}

	/**
	 * When a new order is inserted, add subscriptions related order meta.
	 *
	 * @since 1.0
	 */
	public static function add_order_meta( $order_id, $posted ) {
		_deprecated_function( __METHOD__, '2.0' );
	}

	/**
	 * Add each subscription product's details to an order so that the state of the subscription persists even when a product is changed
	 *
	 * @since 1.2.5
	 */
	public static function add_order_item_meta( $item_id, $values ) {
		_deprecated_function( __METHOD__, '2.0' );
	}

	/**
	 * If shopping cart contains subscriptions, make sure a user can register on the checkout page
	 *
	 * @since 1.0
	 */
	public static function make_checkout_registration_possible( $checkout = '' ) {

		if ( WC_Subscriptions_Cart::cart_contains_subscription() && ! is_user_logged_in() ) {

			// Make sure users can sign up
			if ( false === $checkout->enable_signup ) {
				$checkout->enable_signup = true;
				self::$signup_option_changed = true;
			}

			// Make sure users are required to register an account
			if ( true === $checkout->enable_guest_checkout ) {
				$checkout->enable_guest_checkout = false;
				self::$guest_checkout_option_changed = true;

				if ( ! is_user_logged_in() ) {
					$checkout->must_create_account = true;
				}
			}
		}

	}

	/**
	 * Make sure account fields display the required "*" when they are required.
	 *
	 * @since 1.3.5
	 */
	public static function make_checkout_account_fields_required( $checkout_fields ) {

		if ( WC_Subscriptions_Cart::cart_contains_subscription() && ! is_user_logged_in() ) {

			$account_fields = array(
				'account_username',
				'account_password',
				'account_password-2',
			);

			foreach ( $account_fields as $account_field ) {
				if ( isset( $checkout_fields['account'][ $account_field ] ) ) {
					$checkout_fields['account'][ $account_field ]['required'] = true;
				}
			}
		}

		return $checkout_fields;
	}

	/**
	 * After displaying the checkout form, restore the store's original registration settings.
	 *
	 * @since 1.1
	 */
	public static function restore_checkout_registration_settings( $checkout = '' ) {

		if ( self::$signup_option_changed ) {
			$checkout->enable_signup = false;
		}

		if ( self::$guest_checkout_option_changed ) {
			$checkout->enable_guest_checkout = true;
			if ( ! is_user_logged_in() ) { // Also changed must_create_account
				$checkout->must_create_account = false;
			}
		}
	}

	/**
	 * Also make sure the guest checkout option value passed to the woocommerce.js forces registration.
	 * Otherwise the registration form is hidden by woocommerce.js.
	 *
	 * @since 1.1
	 */
	public static function filter_woocommerce_script_paramaters( $woocommerce_params ) {

		if ( WC_Subscriptions_Cart::cart_contains_subscription() && ! is_user_logged_in() && isset( $woocommerce_params['option_guest_checkout'] ) && 'yes' == $woocommerce_params['option_guest_checkout'] ) {
			$woocommerce_params['option_guest_checkout'] = 'no';
		}

		return $woocommerce_params;
	}

	/**
	 * During the checkout process, force registration when the cart contains a subscription.
	 *
	 * @since 1.1
	 */
	public static function force_registration_during_checkout( $woocommerce_params ) {

		if ( WC_Subscriptions_Cart::cart_contains_subscription() && ! is_user_logged_in() ) {
			$_POST['createaccount'] = 1;
		}

	}

	/**
	 * When creating an order at checkout, if the checkout is to renew a subscription from a failed
	 * payment, hijack the order creation to make a renewal order - not a plain WooCommerce order.
	 *
	 * @since 1.3
	 * @deprecated 2.0
	 */
	public static function filter_woocommerce_create_order( $order_id, $checkout_object ) {
		_deprecated_function( __METHOD__, '2.0' );
		return $order_id;
	}

	/**
	 * Customise which actions are shown against a subscriptions order on the My Account page.
	 *
	 * @since 1.3
	 */
	public static function filter_woocommerce_my_account_my_orders_actions( $actions, $order ) {
		_deprecated_function( __METHOD__, '2.0', 'WCS_Cart_Renewal::filter_my_account_my_orders_actions()' );
		return $actions;
	}
}

WC_Subscriptions_Checkout::init();
