<?php
/**
 * Plugin Name: WooCommerce NYP Subscription Switching
 * Plugin URI:  http://github.com/helgatheviking/woocommerce-nyp-subscription-switching
 * Description: Enable price changing on Name Your Price subscriptions
 * Version:     0.1.2
 * Author:      Kathy Darling
 * Author URI:  http://www.kathyisawesome.com
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wc_nyp_sub_switch
 * Domain Path: /languages
 * Requires at least: 4.5
 * Tested up to: 4.8
 * WC requires at least: 3.6.0
 * WC tested up to: 3.8.0
 */

/**
 * Copyright: © 2016 Kathy Darling.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */


/**
 * The Main WC_NYP_Subs_Switching class
 **/
if ( ! class_exists( 'WC_NYP_Subs_Switching' ) ) :

class WC_NYP_Subs_Switching {

	/**
	 * @var WC_NYP_Subs_Switching - the single instance of the class
	 * @since 0.1.0
	 */
	protected static $_instance = null;

	/**
	 * @var plugin version
	 * @since 0.1.0
	 */
	public $version = '0.2.0';

	/**
	 * @var required WooCommerce version
	 * @since 0.1.0
	 */
	public $required_woo = '3.6.0';

	/**
	 * @var required versions
	 * @since 0.2.0
	 */
	public $required = array( 
		'woo'  => '3.6.0',
		'nyp'  => '2.9.0',
		'subs' => '2.6.0'
	);

	/**
	 * Main WC_NYP_Subs_Switching Instance
	 *
	 * Ensures only one instance of WC_NYP_Subs_Switching is loaded or can be loaded.
	 *
	 * @static
	 * @see WC_NYP_Subs_Switching()
	 * @return WC_NYP_Subs_Switching - Main instance
	 * @since 0.1.0
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 0.1.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cloning this object is forbidden.' ), 'wc_nyp_sub_switch' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 0.1.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.' ), 'wc_nyp_sub_switch' );
	}

	/**
	 * WC_NYP_Subs_Switching Constructor
	 *
	 * @access public
     * @return WC_NYP_Subs_Switching
	 * @since 0.1.0
	 */

	public function __construct() {

		if( ! $this->environment_check() ) {
			add_action('admin_notices', array( $this, 'admin_notices' ) );
			return false;
		}

		// allow subscription prices to be switched
		add_filter( 'wcs_is_product_switchable', array( $this, 'is_switchable' ), 10, 3 );
		add_filter( 'woocommerce_subscriptions_add_switch_query_args', array( $this, 'add_switch_query_args' ), 10, 3 );
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( $this, 'disable_attributes' ) );
		add_filter( 'woocommerce_subscriptions_switch_error_message', array( $this, 'subscription_switch_validation' ), 10, 6 );

		// allow nyp sub variations to be price switched
		add_filter( 'template_redirect', array( $this, 'nyp_subscription_switch_handler' ) );

    }

	/**
	 * Check all requirements are met.
	 *
	 * @return bool
	 * @since  0.2.0
	 */
	public function environment_check() {

		$notices = array();

		// Check WooCommerce version.
		if ( function_exists( 'WC' ) && version_compare(  WC()->version, $this->required[ 'woo' ] ) < 0 ) {
			$notices[] = sprintf( __( '<strong>WooCommerce Name Your Price: Subscription Switching</strong> mini-extension is inactive. The <strong>WooCommerce</strong> plugin must be active and atleast version %s for Name Your Price Switching to function</strong>. Please update or activate WooCommerce.', 'wc_nyp_sub_switch' ), $this->required[ 'woo' ] );
		}
			
		if( class_exists( 'WC_Subscriptions' ) && version_compare( WC_Subscriptions::$version, $this->required[ 'subs' ] ) < 0 ) {
			$notices[] = sprintf( __( '<strong>WooCommerce Name Your Price: Subscription Switching</strong> mini-extension is inactive. The <strong>WooCommerce Subscriptions</strong> plugin must be active and atleast version %s for Name Your Price Switching to function</strong>. Please update or activate WooCommerce Subscriptions.', 'wc_nyp_sub_switch' ), $this->required[ 'subs' ] );
		}

		if( class_exists( 'wc_nyp_sub_switch' ) && version_compare( WC_Name_Your_Price()->version, $this->required[ 'nyp' ] ) < 0 ) {
			$notices[] = sprintf( __( '<strong>WooCommerce Name Your Price: Subscription Switching</strong> mini-extension is inactive. The <strong>WooCommerce Name Your Price</strong> plugin must be active and atleast version %s for Name Your Price Switching to function</strong>. Please update or activate WooCommerce Name Your Price.', 'wc_nyp_sub_switch' ), $this->required[ 'nyp' ] );
		}

		if( empty( $notices ) ) {
			// If the option does not exist at all, we assume that by activating this plugin a user wants to enable it.
			if( false === get_option( WC_Subscriptions_Admin::$option_prefix . '_allow_switching_nyp_price' ) ) {
				update_option( WC_Subscriptions_Admin::$option_prefix . '_allow_switching_nyp_price', 'yes' );
			}

			return true;
		} else {
			update_option( 'wc_nyp_subs_switching_notices', $notices );
			return false;
		}

    }

	/**
	 * Display notices.
	 *
	 * @return bool
	 * @since  0.2.0
	 */
	public function admin_notices( $data ) {

		$notices = get_option( 'wc_nyp_subs_switching_notices' );

		if( is_array( $notices ) ) {
			foreach( $notices as $notice ) {
				echo '<div class="notice notice-error">
					<p>' . wp_kses_post( $notice ) . '</p>
				</div>';
			}
			delete_option( 'wc_nyp_subs_switching_notices' );
		}

	}

	/*
	 * Ensures that NYP products are allowed to be switched
	 *
	 * @param bool $is_product_switchable
	 * @param obj $product
	 * @return bool
	 * @since 0.1.0
	 */
	public function is_switchable( $is_product_switchable, $product, $variation ){
		if( WC_Name_Your_Price_Helpers::is_nyp( $product ) || WC_Name_Your_Price_Helpers::is_nyp( $variation ) ){
			$is_product_switchable = true;
		}

		return $is_product_switchable;
	}


	/*
	 * Add the existing price/period to switch link to pre-populate values
	 *
	 * @param str $permalink
	 * @param int $subscription_id
	 * @param $item_id (the order item)
	 * @return str
	 * @since 0.1.0
	 */
	public function add_switch_query_args( $permalink, $subscription_id, $item_id ){
		$subscription  = wcs_get_subscription( $subscription_id );
		$existing_item = wcs_get_order_item( $item_id, $subscription );

		$nyp_id = ! empty( $existing_item['variation_id'] ) ? $existing_item['variation_id'] : $existing_item['product_id'];

		$nyp_product = wc_get_product( $nyp_id );

		if( WC_Name_Your_Price_Helpers::is_nyp( $nyp_product ) ){

			$inc_tax = get_option( 'woocommerce_prices_include_tax', 'no' ) == 'yes' ? true : false;

			$args = array( 'nyp' => $subscription->get_item_subtotal( $existing_item, $inc_tax ) );

			if( WC_Name_Your_Price_Helpers::is_billing_period_variable( $nyp_product ) ){
				$args['nyp-period'] = WC_Name_Your_Price_Core_Compatibility::get_prop( $subscription, 'billing_period' );
			}

			if( $nyp_product->is_type( 'subscription_variation' ) ){
				$args = array_merge( $args, $nyp_product->get_variation_attributes() );
			}

			$permalink = add_query_arg( $args, $permalink );

		}
		return $permalink;
	}


	/*
	 * Disable the attribute select if switching is not allowed
	 *
	 * @param str $html
	 * @return str
	 * @since 0.1.0
	 */
	public function disable_attributes( $html ){
		global $product;

		if ( isset( $_GET['switch-subscription'] ) && isset( $_GET['nyp-only'] ) ){
			$html = str_replace( '<select', '<select disabled="disabled"', $html );
			add_filter( 'woocommerce_reset_variations_link', '__return_null' );
		}

		return $html;
	}


	/*
	 * Test if the switching subscription is valid
	 * if already valid (ie: changing variation), then skip
	 * if not already valid, check that price or period is changed
	 *
	 * @param bool $is_product_switchable
	 * @param obj $product
	 * @return bool
	 * @since 0.1.0
	 */
	public function subscription_switch_validation( $error_message, $product_id, $quantity, $variation_id, $subscription, $item ){

		$nyp_id = $variation_id ? $variation_id : $product_id;

		// check that $item isn't empty so that you don't accidentally override the "can't find old sub" error
		if( ! empty( $item ) && WC_Name_Your_Price_Helpers::is_nyp( $nyp_id ) ){

			$prefix = apply_filters( 'nyp_field_prefix', '', $nyp_id );

			$nyp_product = wc_get_product( $nyp_id );

			$initial_subscription_price = $subscription->get_line_subtotal( $item );
			$new_subscription_price = floatval( WC_Name_Your_Price_Helpers::get_posted_price( $nyp_id, $prefix ) );
			$initial_subscription_period = WC_Name_Your_Price_Core_Compatibility::get_prop( $subscription, 'billing_period' );
			$new_subscription_period = WC_Name_Your_Price_Helpers::get_posted_period( $nyp_id, $prefix );

			$nyp_error_message = '';

			// if variable billing period check both price and billing period
			if( WC_Name_Your_Price_Helpers::is_billing_period_variable( $nyp_id ) && $new_subscription_price == $initial_subscription_price && $new_subscription_period == $initial_subscription_period ){
				$nyp_error_message = __( 'Please modify the price or billing period so that it is not the same as your existing subscription.', 'wc_name_your_price' );
			} else if ( $new_subscription_price == $initial_subscription_price ){
				$nyp_error_message = __( 'Please modify the price so that it is not the same as your existing subscription.', 'wc_name_your_price' );
			}

			// replace the error message
			$error_message = $nyp_error_message;

		}

		return $error_message;
	}

	/*
	 * Enable switching for NYP variations
	 *
	 * @param bool $is_product_switchable
	 * @param obj $product
	 * @return bool
	 * @since 0.1.0
	 */
	public function nyp_subscription_switch_handler(){
		global $post;
		if ( ! isset( $_GET['switch-subscription'] ) && is_product() && $product = wc_get_product( $post ) ) {

			// variable product with no variation switching allowed
			if ( $product->is_type( 'variable-subscription' ) && in_array( get_option( WC_Subscriptions_Admin::$option_prefix . '_allow_switching', 'no' ), array( 'no', 'grouped' ) ) && 'no' != $product->limit_subscriptions ) {

				// Check if the user has an active subscription for this product, and if so, initiate the switch process
				$subscriptions = wcs_get_users_subscriptions();

				foreach ( $subscriptions as $subscription ) {

					// If already subscribed to the variation and variation is NYP
					if ( $subscription->has_product( $product->id ) && ( 'any' == $product->limit_subscriptions || $subscription->has_status( $product->limit_subscriptions ) || ( 'active' == $product->limit_subscriptions && $subscription->has_status( 'on-hold' ) ) ) ) {

						// If switching is enabled for this product type, initiate the auto-switch process
						// Get the matching item
						foreach ( $subscription->get_items() as $line_item_id => $line_item ) {
							if ( $line_item['product_id'] == $product->id || $line_item['variation_id'] == $product->id ) {
								$item_id = $line_item_id;
								$item    = $line_item;
								break;
							}
						}

						if ( isset( $line_item['variation_id'] ) && WC_Name_Your_Price_Helpers::is_nyp(  $line_item['variation_id'] ) ) {
							wp_redirect( add_query_arg( array( 'auto-switch' => 'true', 'nyp-only' => 1 ), WC_Subscriptions_Switcher::get_switch_url( $item_id, $item, $subscription ) ) );
							exit;

						}

					}

				}

			}

		}

	}

} //end class: do not remove or there will be no more guacamole for you

endif; // end class_exists check


/**
 * Returns the main instance of WC_NYP_Subs_Switching to prevent the need to use globals.
 *
 * @since  0.1.0
 * @return WC_NYP_Subs_Switching
 */
function WC_NYP_Subs_Switching() {
  return WC_NYP_Subs_Switching::instance();
}

// Launch the whole plugin once NYP is loaded
add_action( 'plugins_loaded', 'WC_NYP_Subs_Switching' );
