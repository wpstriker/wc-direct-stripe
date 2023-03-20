<?php
/*
Plugin Name: WC Direct Stripe
Plugin URI: http://www.wpstriker.com/plugins
Description: Plugin for direct stripe checkout from product page.
Version: 1.0.0
Author: wpstriker
Author URI: http://www.wpstriker.com
License: GPLv2
Copyright 2020 wpstriker (email : wpstriker@gmail.com)
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'WC_DIRECT_STRIPE_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_DIRECT_STRIPE_DIR', plugin_dir_path( __FILE__ ) );

require_once WC_DIRECT_STRIPE_DIR . 'functions.php';

if( ! class_exists( 'WC_Direct_Stripe' ) ) :

class WC_Direct_Stripe {
	protected $settings;
	
	protected $debug = false;
	
	public function __construct() {
		$this->init();
	}
	
	public function init() {
		
		//add_action( 'wp', array( $this, 'maybe_debug' ), 8 );
		
		add_action( 'wp', array( $this, 'handle_stripe_success' ) );
		
		add_action( 'wp', array( $this, 'handle_stripe_cancel' ) );
		
		add_action( 'wp_ajax_stripe_direct_checkout', array( $this, 'handle_stripe_checkout' ) );
		
		add_action( 'wp_ajax_nopriv_stripe_direct_checkout', array( $this, 'handle_stripe_checkout' ) );
		
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'show_stripe_button' ) );		
		
		add_action( 'wp_enqueue_scripts', array( $this, 'load_wc_stripe_scripts' ) );
		
		add_action( 'admin_menu', array( $this, 'direct_stripe_settings_menu_add' ), 99 );
			
		if( ! session_id() ) {
			@session_start();
		}
			
		if( isset( $_GET['_debug'] ) ) {
			$this->debug 	= true;
		}
	}
	
	public function direct_stripe_settings_menu_add() {
		add_menu_page( 'Direct Stripe Setting', 'Direct Stripe Setting', 'administrator', 'direct_stipe_setting', array( $this, 'direct_stipe_setting_page' ) );
	}
	
	public function direct_stipe_setting_page() {
		$is_updated	= false;
		
		if( isset( $_POST['submit'] ) && $_POST['submit'] != '' ) {
			update_option( 'stripe_publishable_key', $_POST['stripe_publishable_key'] );
			update_option( 'stripe_secret_key', $_POST['stripe_secret_key'] );
			
			$is_updated	= true;
		}		
		?>
		<div class="wrap">
            <h1>Direct Stripe Setting</h1>
            
            <?php if( $is_updated ) { ?>
			<div class="updated settings-error notice is-dismissible" style="margin: 0 0 20px; max-width: 845px;"> 
				<p><strong>Settings saved successfully.</strong></p>
				<button class="notice-dismiss" type="button">
					<span class="screen-reader-text">Dismiss this notice.</span>
				</button>
			</div>
			<?php } ?>
            
            <form method="post">
            
	            <table class="form-table">
                
                    <tr>
                        <th scope="row"><label for="stripe_publishable_key">Stripe Publishable Key</label></th>
                        <td>
                        	<input name="stripe_publishable_key" type="text" id="stripe_publishable_key" value="<?php echo get_option( 'stripe_publishable_key' );?>" class="regular-text" />
                      	</td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stripe_secret_key">Stripe Secret Key</label></th>
                        <td>
                        	<input name="stripe_secret_key" type="text" id="stripe_secret_key" value="<?php echo get_option( 'stripe_secret_key' );?>" class="regular-text" />
                      	</td>
                    </tr>
                                                         
                </table>
                
                <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
                
            </form>        
        </div>
    	<?php	
	}
	
	public function load_wc_stripe_scripts() {			
		wp_register_style( 'wc_direct_stripe_styles', WC_DIRECT_STRIPE_URL . 'css/wc_direct_stripe.css' );
		//wp_enqueue_style( 'wc_direct_stripe_styles' );

		wp_register_script( 'stripe_lib', 'https://js.stripe.com/v3/', '', '3.0', true );
		wp_register_script( 'wc_direct_stripe', WC_DIRECT_STRIPE_URL . 'js/wc_direct_stripe.js', array( 'jquery', 'stripe_lib' ), '', true );										
		
		$wc_direct_stripe_params	= array(
										'key'		=> get_option( 'stripe_publishable_key' ),
										'ajax_url' 	=> admin_url( 'admin-ajax.php' )
										);
		
		wp_localize_script( 'wc_direct_stripe', 'wc_direct_stripe_params', $wc_direct_stripe_params );
		
		wp_enqueue_script( 'wc_direct_stripe' );
	}
	
	public function handle_stripe_success() {
		if( ! isset( $_GET['spg'] ) || $_GET['spg'] != 'success' ) {
			return;
		}
		
		$order_id	= intval( $_REQUEST['oid'] );
		
		$order	= wc_get_order( $order_id );
		
		if( ! $order || is_wp_error( $order ) ) {
			wp_redirect( get_permalink( woocommerce_get_page_id( 'shop' ) ) );
			die();
		}
		
		print_rr( $_REQUEST );	
		
		$stripe_session_id	= get_post_meta( $order_id, 'stripe_session_id', true );
		
		print_rr( $stripe_session_id );
		
		// Process success
		if( ! class_exists( 'Stripe\Stripe' ) ) {	
			require_once WC_DIRECT_STRIPE_DIR . 'vendor/autoload.php';
		}
		
		try {
			// Use Stripe's library to make requests...
			\Stripe\Stripe::setApiKey( get_option( 'stripe_secret_key' ) );
						
			$events = \Stripe\Event::all([
			  	'type' 		=> 'checkout.session.completed',
			  	'created' 	=> [
					// Check for events created in the last 24 hours.
					'gte'	=> time() - 24 * 60 * 60,
			  	],
			]);
			
			if( $events ) {  
				foreach ( $events->autoPagingIterator() as $event ) {
			  		$session = $event->data->object;
					
					if( $session->id == $stripe_session_id ) {
						
						// Fulfill the purchase...			  			
						$customer	= $this->get_stripe_customer( $session->customer ); 
						
						$customer_email	= $customer ? $customer->email : false;
						
						if( $customer_email && ! $order->get_billing_email() ) {
							$order->set_billing_email( $customer_email );
						}
						
						$order->add_order_note( __( 'Pyament Intent ID = ' . $session->payment_intent, 'woocommerce' ) );
						
						$order->save();
						
						$order->payment_complete( $session->payment_intent );
						
						add_post_meta( $order->get_id(), '_stripe_payment_intent_id', $session->payment_intent );
						
						$order->save();
						
						wp_redirect( $order->get_checkout_order_received_url() );
						die();
						break;						
					}
				}
			}
		} catch(\Stripe\Exception\CardException $e) {
			// Since it's a decline, \Stripe\Exception\CardException will be caught
			//echo 'Status is:' . $e->getHttpStatus() . '\n';
			//echo 'Type is:' . $e->getError()->type . '\n';
			//echo 'Code is:' . $e->getError()->code . '\n';
			// param is '' in this case
			//echo 'Param is:' . $e->getError()->param . '\n';
			//echo 'Message is:' . $e->getError()->message . '\n';
			
		} catch (\Stripe\Exception\RateLimitException $e) {
			// Too many requests made to the API too quickly
			
		} catch (\Stripe\Exception\InvalidRequestException $e) {
			// Invalid parameters were supplied to Stripe's API
			
		} catch (\Stripe\Exception\AuthenticationException $e) {
			// Authentication with Stripe's API failed
			// (maybe you changed API keys recently)
			
		} catch (\Stripe\Exception\ApiConnectionException $e) {
			// Network communication with Stripe failed
			
		} catch (\Stripe\Exception\ApiErrorException $e) {
			// Display a very generic error to the user, and maybe send
			// yourself an email
			
		} catch (Exception $e) {
			// Something else happened, completely unrelated to Stripe
			
		}		
		
		wp_redirect( get_permalink( woocommerce_get_page_id( 'shop' ) ) );		
		die();
	}
	
	public function get_stripe_customer( $customer_id = false ) {
		if( ! $customer_id ) {
			return false;
		}
		
		// Process success
		if( ! class_exists( 'Stripe\Stripe' ) ) {	
			require_once WC_DIRECT_STRIPE_DIR . 'vendor/autoload.php';
		}
		
		try {
			// Use Stripe's library to make requests...
			\Stripe\Stripe::setApiKey( get_option( 'stripe_secret_key' ) );
						
			$customer = \Stripe\Customer::retrieve( $customer_id );
			
			return $customer;
		} catch(\Stripe\Exception\CardException $e) {
			// Since it's a decline, \Stripe\Exception\CardException will be caught
			//echo 'Status is:' . $e->getHttpStatus() . '\n';
			//echo 'Type is:' . $e->getError()->type . '\n';
			//echo 'Code is:' . $e->getError()->code . '\n';
			// param is '' in this case
			//echo 'Param is:' . $e->getError()->param . '\n';
			//echo 'Message is:' . $e->getError()->message . '\n';			
		} catch (\Stripe\Exception\RateLimitException $e) {
			// Too many requests made to the API too quickly
		} catch (\Stripe\Exception\InvalidRequestException $e) {
			// Invalid parameters were supplied to Stripe's API
		} catch (\Stripe\Exception\AuthenticationException $e) {
			// Authentication with Stripe's API failed
			// (maybe you changed API keys recently)
		} catch (\Stripe\Exception\ApiConnectionException $e) {
			// Network communication with Stripe failed
		} catch (\Stripe\Exception\ApiErrorException $e) {
			// Display a very generic error to the user, and maybe send
			// yourself an email
		} catch (Exception $e) {
			// Something else happened, completely unrelated to Stripe
		}	
		
		return false;	
	}
	
	public function handle_stripe_cancel() {
		if( ! isset( $_GET['cpg'] ) || $_GET['cpg'] != 'cancel' ) {
			return;	
		}
		
		$order_id	= intval( $_REQUEST['oid'] );
		
		$order	= wc_get_order( $order_id );
		
		if( ! $order || is_wp_error( $order ) ) {
			wp_redirect( get_permalink( woocommerce_get_page_id( 'shop' ) ) );
			die();
		}
		
		// Process cancel
		// Cancel the order + restore stock.
		WC()->session->set( 'order_awaiting_payment', false );
		$order->update_status( 'cancelled', __( 'Order cancelled by customer.', 'woocommerce' ) );

		do_action( 'woocommerce_cancelled_order', $order->get_id() );

		wp_redirect( get_permalink( woocommerce_get_page_id( 'shop' ) ) );		
		die();
	}
	
	public function show_stripe_button() {
		global $product;

		if ( ! $product->is_purchasable() ) {
			return;
		}

		?>
        <button type="button" name="stripe-checkout" value="<?php echo esc_attr( $product->get_id() ); ?>" class="stripe_direct_button button alt">Stripe Checkout</button>
        <?php
	}
	
	public function handle_stripe_checkout() {
		
		$product_id	= $_POST['product_id'];
		
		if( ! $product_id ) {
			return 0;
		}
		
		$product	= get_product( $product_id );
		
		$nonce 		= wp_create_nonce( 'direct-stripe' );
			
		global $woocommerce;
		
		$user_id	= get_current_user_id();
		
		$order_data = array(
			'status' 		=> apply_filters('woocommerce_default_order_status', 'pending' ),
			'customer_id' 	=> $user_id
		);
	
		// Now we create the order
		$order 	= wc_create_order( $order_data );
		
		$order->add_product( $product, 1 ); 	// This is an existing SIMPLE product
		
		if( $user_id ) {
			$address = array(
						'first_name' => get_user_meta( $user_id, 'billing_first_name', true ),
						'last_name'  => get_user_meta( $user_id, 'billing_last_name', true ),
						'company'    => get_user_meta( $user_id, 'billing_company', true ),
						'email'      => get_user_meta( $user_id, 'billing_email', true ),
						'phone'      => get_user_meta( $user_id, 'billing_phone', true ),
						'address_1'  => get_user_meta( $user_id, 'billing_address_1', true ),
						'address_2'  => get_user_meta( $user_id, 'billing_address_2', true ),
						'city'       => get_user_meta( $user_id, 'billing_city', true ),
						'state'      => get_user_meta( $user_id, 'billing_state', true ),
						'postcode'   => get_user_meta( $user_id, 'billing_postcode', true ),
						'country'    => get_user_meta( $user_id, 'billing_country', true ),
			);
			$order->set_address( $address, 'billing' );
		}
		
		$order->calculate_totals();
		//$order->update_status( "completed", 'API Order', TRUE );
		
		if( ! class_exists( 'Stripe\Stripe' ) ) {	
			require_once WC_DIRECT_STRIPE_DIR . 'vendor/autoload.php';
		}
		
		try {
			// Use Stripe's library to make requests...
			\Stripe\Stripe::setApiKey( get_option( 'stripe_secret_key' ) );
						
			$session = \Stripe\Checkout\Session::create([
				'payment_method_types'	=> ['card'],
				'line_items' => [[
					'name' 			=> $product->get_name(),
					//'description' 	=> $product->get_description() ,
					'images' 		=>   $product->get_image_id() ? [ wp_get_attachment_image_url( $product->get_image_id(), 'full' ) ] : null,
					'amount' 		=> $product->get_price() * 100,
					'currency' 		=> 'USD',
					'quantity' 		=> 1,
				]],
				'success_url' 	=> add_query_arg( array( 'spg' => 'success', 'session_id' => $nonce, 'oid' => $order->get_id() ), site_url( '/' ) ),
				'cancel_url' 	=> add_query_arg( array( 'cpg' => 'cancel', 'session_id' => $nonce, 'oid' => $order->get_id() ), site_url( '/' ) ),
			]);
		
		} catch(\Stripe\Exception\CardException $e) {
			// Since it's a decline, \Stripe\Exception\CardException will be caught
			//echo 'Status is:' . $e->getHttpStatus() . '\n';
			//echo 'Type is:' . $e->getError()->type . '\n';
			//echo 'Code is:' . $e->getError()->code . '\n';
			// param is '' in this case
			//echo 'Param is:' . $e->getError()->param . '\n';
			//echo 'Message is:' . $e->getError()->message . '\n';
			$this->_log( $e->getError()->message );
			return 0;
		} catch (\Stripe\Exception\RateLimitException $e) {
			// Too many requests made to the API too quickly
			$this->_log( $e->getError()->message );
			return 0;
		} catch (\Stripe\Exception\InvalidRequestException $e) {
			// Invalid parameters were supplied to Stripe's API
			$this->_log( $e->getError()->message );
			return 0;
		} catch (\Stripe\Exception\AuthenticationException $e) {
			// Authentication with Stripe's API failed
			// (maybe you changed API keys recently)
			$this->_log( $e->getError()->message );
			return 0;
		} catch (\Stripe\Exception\ApiConnectionException $e) {
			// Network communication with Stripe failed
			$this->_log( $e->getError()->message );
			return 0;
		} catch (\Stripe\Exception\ApiErrorException $e) {
			// Display a very generic error to the user, and maybe send
			// yourself an email
			$this->_log( $e->getError()->message );
			return 0;
		} catch (Exception $e) {
			// Something else happened, completely unrelated to Stripe
			$this->_log( $e->getError()->message );
			return 0;
		}		
		
		update_post_meta( $order->get_id(), 'stripe_session_id', $session->id );
		
		echo $session->id;
					
		die();
	}
		
	public function send_mail( $to, $subject, $body, $attachments = array() ) {
		// This is headers
		$sender_name	= get_bloginfo( 'name' );
		$sender_email	= get_option( 'admin_email' );
		$return_email	= get_option( 'admin_email' );
		
		$headers 	 = "From: ".$sender_name." <".$sender_email.">" . "\r\n";
		$headers 	.= "Reply-To: ".$return_email."" . "\r\n";
		$headers 	.= "Return-Path: ".$return_email."\r\n";
		$headers  	.= "MIME-Version: 1.0\r\n";
		$headers 	.= "Content-type: text/html; charset: utf8\r\n";
		$headers 	.= "X-Mailer: PHP/" . phpversion()."\r\n";
		$headers 	.= "X-Priority: 1 (Highest)\n";
		$headers 	.= "X-MSMail-Priority: High\n";
		$headers 	.= "Importance: High\n";
		
		wp_mail( $to, $subject, $body, $headers, $attachments );
	}
	
	public function maybe_debug() {
		if( ! isset( $_GET['_debug2'] ) )
			return;
					
		exit;	
	}
		
	public function get_domain_name() {
		$domain = site_url( "/" ); 
		$domain = str_replace( array( 'http://', 'https://', 'www.' ), '', $domain );
		$domain = explode( "/", $domain );
		$domain	= $domain[0] ? $domain[0] : $_SERVER['SERVER_ADDR'];	
		
		return $domain;
	}
	
	public function base64_url_encode( $data ) { 
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); 
	}
	
	public function base64_url_decode( $data ) { 
		return base64_decode( str_pad( strtr( $data, '-_', '+/' ), strlen( $data ) % 4, '=', STR_PAD_RIGHT ) ); 
	}

	public function _log( $msg = "" ) {
		
		$msg	= ( is_array( $msg ) || is_object( $msg ) ) ? print_r( $msg, 1 ) : $msg;
		 	
		error_log( date('[Y-m-d H:i:s e] ') . $msg . PHP_EOL, 3, __DIR__ . "/debug.log" );
	}
}

endif;

$WC_Direct_Stripe	= new WC_Direct_Stripe();