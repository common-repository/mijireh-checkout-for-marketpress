<?php

/*
 * Plugin Name: Mijireh Checkout for MarketPress
 * Plugin URI: http://www.patsatech.com
 * Description: Mijireh Checkout Plugin for accepting payments on your MarketPress Store.
 * Author: PatSaTECH
 * Version: 1.0.0
 * Author URI: http://www.patsatech.com
 * Contributors: patsatech
 * Text Domain: patsatech-jigoshop-mijireh
 * Domain Path: /lang
 */


add_action('init', 'mijireh_gateway_init');

function mijireh_gateway_init() {

	load_plugin_textdomain('patsatech-marketpress-mijireh', false,  basename(dirname(__FILE__)) . '/languages' );

}

register_activation_hook(__FILE__, 'install_slurp_page');

function install_slurp_page() {
	if(!get_page_by_path('mijireh-secure-checkout')) {
    	$page = array(
	      	'post_title' => 'Mijireh Secure Checkout',
	      	'post_name' => 'mijireh-secure-checkout',
	      	'post_parent' => 0,
	      	'post_status' => 'private',
	      	'post_type' => 'page',
	      	'comment_status' => 'closed',
	      	'ping_status' => 'closed',
	      	'post_content' => "<h1>Checkout</h1>\n\n{{mj-checkout-form}}",
    	);
    	wp_insert_post($page);
  	}
}

register_uninstall_hook(__FILE__, 'remove_slurp_page');

function remove_slurp_page() {
	$force_delete = true;
  	$post = get_page_by_path('mijireh-secure-checkout');
  	wp_delete_post($post->ID, $force_delete);
}

add_action('mp_load_gateway_plugins', 'register_mijireh_gateway');

function register_mijireh_gateway() {
	
	class MP_Gateway_Mijireh extends MP_Gateway_API {
	
		//private gateway slug. Lowercase alpha (a-z) and dashes (-) only please!
	  	var $plugin_name = 'mijireh';
	  	
	  	//name of your gateway, for the admin side.
	  	var $admin_name = '';
	  	
	  	//public name of your gateway, for lists and such.
	  	var $public_name = '';
		
	  	//url for an image for your checkout method. Displayed on checkout form if set
	  	var $method_img_url = '';
	  	
	  	//url for an submit button image for your checkout method. Displayed on checkout form if set
	  	var $method_button_img_url = '';
		
	  	//whether or not ssl is needed for checkout page
	  	var $force_ssl = false;
	  	
	  	//always contains the url to send payment notifications to if needed by your gateway. Populated by the parent class
	  	var $ipn_url;
		
	  	//whether if this is the only enabled gateway it can skip the payment_form step
	  	var $skip_form = true;
		
	  	/**
	   	 * Runs when your class is instantiated. Use to setup your plugin instead of __construct()
	   	 */
	  	function on_creation() {
	    	global $mp;
	    	$settings = get_option('mp_settings');
	    	
	    	//set names here to be able to translate
	    	$this->admin_name = __('Mijireh Checkout', 'patsatech-marketpress-mijireh');
	    	$this->public_name = __('Credit Card', 'patsatech-marketpress-mijireh');
	       	
	    	if ( isset( $settings['gateways']['mijireh'] ) ) {
	    		
	      		$this->currencyCode = $settings['gateways']['mijireh']['currency'];
	      		$this->access_key = $settings['gateways']['mijireh']['access_key'];
		  		
	    	}
			
			add_action( 'add_meta_boxes', array( $this, 'add_page_slurp_meta' ) );
	  		add_action( 'wp_ajax_page_slurp', array( $this, 'page_slurp' ) );
			
	  	}
	  	
	    /**
	     * page_slurp function.
	     *
	     * @access public
	     * @return void
	     */
	    public function page_slurp() {
			
	    	self::init_mijireh();
			
			$settings = get_option('mp_settings');
			
      		$ipn_url = home_url($settings['slugs']['store'] . '/payment-return/mijireh');
			
			$page 	= get_page( absint( $_POST['page_id'] ) );
			$url 	= get_permalink( $page->ID );
	    	$job_id = $url;
			if ( wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'publish' ) ) ) {
				$job_id = Mijireh::slurp( $url, $page->ID, $ipn_url );
	    	}
			echo $job_id;
			die;
		}
	    
	    /**
	     * add_page_slurp_meta function.
	     *
	     * @access public
	     * @return void
	     */
	    public function add_page_slurp_meta() {
	    	
	    	if ( self::is_slurp_page() ) {
	       		wp_enqueue_style( 'mijireh_css', WP_PLUGIN_URL . '/'. plugin_basename( dirname(__FILE__)) .'/assets/css/mijireh.css' );
	        	wp_enqueue_script( 'pusher', 'https://d3dy5gmtp8yhk7.cloudfront.net/1.11/pusher.min.js', null, false, true );
	        	wp_enqueue_script( 'page_slurp', WP_PLUGIN_URL . '/'. plugin_basename( dirname(__FILE__)) .'/assets/js/page_slurp.js', array('jquery'), false, true );
				
				add_meta_box(
					'slurp_meta_box', 		// $id
					'Mijireh Page Slurp', 	// $title
					array( 'MP_Gateway_Mijireh', 'draw_page_slurp_meta_box' ), // $callback
					'page', 	// $page
					'normal', 	// $context
					'high'		// $priority
				);
			}
	    }
		
	    /**
	     * is_slurp_page function.
	     *
	     * @access public
	     * @return void
	     */
	    public function is_slurp_page() {
			global $post;
			$is_slurp = false;
			if ( isset( $post ) && is_object( $post ) ) {
				$content = $post->post_content;
				if ( strpos( $content, '{{mj-checkout-form}}') !== false ) {
					$is_slurp = true;
				}
			}
			return $is_slurp;
	    }
		
	    /**
	     * draw_page_slurp_meta_box function.
	     *
	     * @access public
	     * @param mixed $post
	     * @return void
	     */
	    public function draw_page_slurp_meta_box( $post ) {
	    	
	    	self::init_mijireh();
			
			$settings = get_option('mp_settings');
			
			echo "<div id='mijireh_notice' class='mijireh-info alert-message info' data-alert='alert'>";
			echo    "<h2>Slurp your custom checkout page!</h2>";
			echo    "<p>Get the page designed just how you want and when you're ready, click the button below and slurp it right up.</p>";
			echo    "<div id='slurp_progress' class='meter progress progress-info progress-striped active' style='display: none;'><div id='slurp_progress_bar' class='bar' style='width: 20%;'>Slurping...</div></div>";
			
			if(!empty($settings['gateways']['mijireh']['access_key'])){
				
				echo    "<p><a href='#' id='page_slurp' rel=". $post->ID ." class='button-primary'>Slurp This Page!</a> ";
				echo    '<a class="nobold" href="https://secure.mijireh.com/checkout/' . $settings['gateways']['mijireh']['access_key'] . '" id="view_slurp" target="_blank">Preview Checkout Page</a></p>';
				
			}else{
				
				echo '<p style="color:red;font-size:15px;text-shadow: none;"><b>Please enter you Access Key in Mijireh Settings. <a class="nobold" target="_blank" href="' . home_url('/wp-admin/edit.php?post_type=product&page=marketpress&tab=gateways') . '" id="view_slurp" target="_new">Enter Access Key</a></b></p>';
				
			}
			
			echo  "</div>";
			
	    }
		
		/**
		 * init_mijireh function.
		 *
		 * @access public
		 */
		public function init_mijireh() {
			if ( ! class_exists( 'Mijireh' ) ) {
				
		    	require_once 'mijireh/Mijireh.php';
				
	    		$settings = get_option('mp_settings');
				
		    	Mijireh::$access_key = $settings['gateways']['mijireh']['access_key'];
				
		    }
		}
		
		/**
		 * Return fields you need to add to the top of the payment screen, like your credit card info fields
		 *
		 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
		 * @param array $shipping_info. Contains shipping info and email in case you need it
		 */
	  	function payment_form($cart, $shipping_info) {
	    	if (isset($_GET['cancel'])){
	      		echo '<div class="mp_checkout_error">' . __('Your Mijireh transaction has been canceled.', 'patsatech-marketpress-mijireh') . '</div>';
			}
	  	}

	  	/**
	   	 * Use this to process any fields you added. Use the $_REQUEST global,
	     *  and be sure to save it to both the $_SESSION and usermeta if logged in.
	     *  DO NOT save credit card details to usermeta as it's not PCI compliant.
	     *  Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
	     *  it will redirect to the next step.
	     *
	     * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
	     * @param array $shipping_info. Contains shipping info and email in case you need it
	     */
	  	function process_payment_form($cart, $shipping_info) {
	    	global $mp;
	    
	    	$mp->generate_order_id();
			
	  	}
	  
	    /**
	     * Return the chosen payment details here for final confirmation. You probably don't need
	     *  to post anything in the form as it should be in your $_SESSION var already.
	     *
	     * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
  	     * @param array $shipping_info. Contains shipping info and email in case you need it
	     */
	  	function confirm_payment_form($cart, $shipping_info) {
	    	global $mp;
			
	  	}
	
	  	/**
	     * Use this to do the final payment. Create the order then process the payment. If
	     *  you know the payment is successful right away go ahead and change the order status
	     *  as well.
	     *  Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
	     *  it will redirect to the next step.
	     *
	     * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
	     * @param array $shipping_info. Contains shipping info and email in case you need it
		 */
	  	function process_payment($cart, $shipping_info) {
	    	global $mp, $current_user;;
	    	
		    $timestamp = time();
			
		    $settings = get_option('mp_settings');
			
		    $order_id = $mp->generate_order_id();
			
			$this->init_mijireh();
			
			$mj_order = new Mijireh_Order();
			
		    $totals = array();
		    $counter = 0;
	    	
	    	foreach ($cart as $product_id => $variations) {
	      		foreach ($variations as $variation => $data) {
		  			$totals[] = $mp->before_tax_price($data['price']) * $data['quantity'];
					
				    $sku = empty($data['SKU']) ? $product_id : $data['SKU'];
					
					$mj_order->add_item( $data['name'], $mp->before_tax_price($data['price']), $data['quantity'], $sku );
					
					$counter++;
	      		}
	    	}
	    	
		    $total = array_sum($totals);
		    
		    if ( $coupon = $mp->coupon_value($mp->get_coupon_code(), $total) ) {
				$mj_order->discount = $total - $coupon['new_total'];
				$total = $coupon['new_total'];
		    }
			
		    //shipping line
		    if ( ($shipping_price = $mp->shipping_price()) !== false ) {
			    
				$total = $total + $shipping_price;
				
				if($settings['tax']['tax_shipping']) {
					$mj_order->shipping 		= $shipping_price;
				}else {
					$mj_order->shipping 		= $shipping_price;
				}
				
		    }
	    	
		    //tax line
		    if ( ($tax_price = $mp->tax_price()) !== false ) {
			    $total = $total + $tax_price;
				$mj_order->tax = $tax_price;
		    }
			
			$mj_order->total = $total;
			
		    //setup transients for ipn in case checkout doesn't redirect (ipn should come within 12 hrs!)
			set_transient('mp_order_'. $order_id . '_cart', $cart, 60*60*12);
			set_transient('mp_order_'. $order_id . '_shipping', $shipping_info, 60*60*12);
			set_transient('mp_order_'. $order_id . '_userid', $current_user->ID, 60*60*12);
	    	
			// add billing address to order
			$billing 					= new Mijireh_Address();
			$billing->first_name 		= $names[0];
			$billing->last_name 		= $names[1];
			$billing->street 			= $shipping_info['address1'];
			$billing->apt_suite 		= $shipping_info['address2'];
			$billing->city 				= $shipping_info['city'];
			$billing->state_province	= $shipping_info['state'];
			$billing->zip_code 			= $shipping_info['zip'];
			$billing->country 			= $shipping_info['country'];
			$billing->phone 			= $shipping_info['phone'];
			if ( $billing->validate() ){
				$mj_order->set_billing_address( $billing );
				$mj_order->set_shipping_address( $billing );
			}
			
			// set order name
			$mj_order->first_name 		= $names[0];
			$mj_order->last_name 		= $names[1];
			$mj_order->email 			= $shipping_info['email'];
			
			// add meta data to identify jigoshop_mijireh order
			$mj_order->add_meta_data( 'mp_order_id', $order_id );
			
			// Set URL for mijireh payment notification - use WC API
			$mj_order->return_url 		= $this->ipn_url;
			
			// Identify PatSaTECH
			$mj_order->partner_id 		= 'patsatech';
			
			try {
				$mj_order->create();
				
	    		wp_redirect($mj_order->checkout_url);
	    		
	    		exit(0);	
			} catch (Mijireh_Exception $e) {
				
			    $mp->cart_checkout_error( __('Mijireh Error : ', 'patsatech-marketpress-mijireh') . $e->getMessage() );
				
			}
		}
	  	
	  	/**
	     * Filters the order confirmation email message body. You may want to append something to
	     *  the message. Optional
	     *
	     * Don't forget to return!
	     */
	  	function order_confirmation_email($msg, $order) {
	    	return $msg;
	  	}
	  	
	  	/**
	     * Return any html you want to show on the confirmation screen after checkout. This
	     *  should be a payment details box and message.
	     *
	     * Don't forget to return!
	     */
	  	function order_confirmation_msg($content, $order) {
		    global $mp;
		    if ($order->post_status == 'order_received') {
		    	$content .= '<p>' . sprintf(__('Your payment via Mijireh for this order totaling %s is not yet complete. Here is the latest status:', 'patsatech-marketpress-mijireh'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total'])) . '</p>';
	      		$statuses = $order->mp_payment_info['status'];
		      	krsort($statuses); //sort with latest status at the top
		      	$status = reset($statuses);
		      	$timestamp = key($statuses);
		      	$content .= '<p><strong>' . date(get_option('date_format') . ' - ' . get_option('time_format'), $timestamp) . ':</strong> ' . htmlentities($status) . '</p>';
	    	} else {
	      		$content .= '<p>' . sprintf(__('Your payment via Mijireh for this order totaling %s is complete. The transaction number is <strong>%s</strong>.', 'patsatech-marketpress-mijireh'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total']), $order->mp_payment_info['transaction_id']) . '</p>';
	    	}
	    	return $content;
	  	}
	  	
	  	/**
	     * Runs before page load incase you need to run any scripts before loading the success message page
	    */
		function order_confirmation($order) {
			global $mp;
		}
	    
	  	/**
	     * Echo a settings meta box with whatever settings you need for you gateway.
	     *  Form field names should be prefixed with mp[gateways][plugin_name], like "mp[gateways][plugin_name][mysetting]".
	     *  You can access saved settings via $settings array.
	     */
	  	function gateway_settings_box($settings) {
	    	global $mp;
	    	
	    	$settings = get_option('mp_settings');
	    	
	    	?>
			<div id="mp_mijireh" class="postbox">
		    	<h3 class='handle'><span><?php _e('Mijireh Settings', 'patsatech-marketpress-mijireh'); ?></span></h3>
		      	<div class="inside">
		        	<span class="description"><?php _e('Mijireh Checkout provides a fully PCI Compliant, secure way to collect and transmit credit card data to your payment gateway while keeping you in control of the design of your site.', 'patsatech-marketpress-mijireh') ?></span>
		        	<table class="form-table">
		  				<tr>
							<th scope="row"><?php _e('Mijireh Access Key', 'patsatech-marketpress-mijireh') ?></th>
						    <td>
						        <p>
									<input value="<?php echo esc_attr($settings['gateways']['mijireh']['access_key']); ?>" size="30" name="mp[gateways][mijireh][access_key]" type="text" />
							    </p>	
						    </td>
						</tr>	
		          		<tr valign="top">
			        		<th scope="row"><?php _e('Mijireh Currency', 'patsatech-marketpress-mijireh') ?></th>
			        		<td>
				          		<span class="description"><?php _e('Selecting a currency other than that used for your store may cause problems at checkout.', 'patsatech-marketpress-mijireh'); ?></span><br />
			          			<select name="mp[gateways][mijireh][currency]">
				          		<?php
				          		$sel_currency = ($settings['gateways']['mijireh']['currency']) ? $settings['gateways']['mijireh']['currency'] : $settings['currency'];
				          		$currencies = array(
								              	'EUR' => 'EUR - Euro',
								              	'AUD' => 'AUD - Australian Dollar',
								              	'BRL' => 'BRL - Brazilian Real',
								              	'BGN' => 'BGN - Bulgarian Lev',
								              	'CAD' => 'CAD - Canadian Dollar',
								              	'CNY' => 'CNY - Chinese Yuan',
								              	'CZK' => 'CZK - Czech Koruna',
								              	'DKK' => 'DKK - Danish Krone',
								              	'CHF' => 'CHF - Swiss Franc',
								              	'GBP' => 'GBP - Pound Sterling',
								              	'ILS' => 'ILS - Israeli Shekel',
								              	'ISK' => 'ISK - Icelandic Króna',
								              	'INR' => 'INR - Indian Rupee',
								              	'KPW' => 'KPW - North Korean Won',
								              	'KRW' => 'KRW - South Korean Won',
								              	'LVL' => 'LVL - Latvian Lats',
								              	'LTL' => 'LTL - Lithuanian Litas',
								              	'RON' => 'RON - Romanian Leu',
								              	'ZAR' => 'ZAR - South African Rand',
								              	'HKD' => 'HKD - Hong Kong Dollar',
								              	'HUF' => 'HUF - Hungarian Forint',
								              	'JPY' => 'JPY - Japanese Yen',
								              	'MYR' => 'MYR - Malaysian Ringgits',
								              	'MXN' => 'MXN - Mexican Peso',
								              	'NOK' => 'NOK - Norwegian Krone',
								              	'NZD' => 'NZD - New Zealand Dollar',
								              	'PHP' => 'PHP - Philippine Pesos',
								              	'PLN' => 'PLN - Polish Zloty',
								              	'SEK' => 'SEK - Swedish Krona',
								              	'SGD' => 'SGD - Singapore Dollar',
								              	'TWD' => 'TWD - Taiwan New Dollars',
								              	'THB' => 'THB - Thai Baht',
												'TRY' => 'TRY - Turkish lira',
								              	'USD' => 'USD - U.S. Dollar'
												);
								
				          		foreach ($currencies as $k => $v) {
				              		echo '		<option value="' . $k . '"' . ($k == $sel_currency ? ' selected' : '') . '>' . wp_specialchars($v, true) . '</option>' . "\n";
				          		}
				          		?>
				          		</select>
			        		</td>
						</tr>
					</table>
		    	</div>
		    </div>
	    	<?php
	  	}
	  	
	  	/**
	   	 * Filters posted data from your settings form. Do anything you need to the $settings['gateways']['plugin_name']
	     *  array. Don't forget to return!
	     */
	  	function process_gateway_settings($settings) {
	    	return $settings;
	  	}
	  	
	  	/**
	     * IPN and payment return
	     */
	  	function process_ipn_return() {
	    	global $mp;
	    	$settings = get_option('mp_settings');
			
	    	if ( isset( $_GET['order_number'] ) ) {
				
				$this->init_mijireh();
				
				try {
					
		  			$mj_order 	= new Mijireh_Order( esc_attr( $_GET['order_number'] ) );
					
		  		    $order_id 	= $mj_order->get_meta_value( 'mp_order_id' );
					
					$timestamp = time();
					
					$payment_status = $mj_order->status;
					
					$status = __('Completed - The sender\'s transaction has completed.', 'patsatech-marketpress-mijireh');
		          	$paid = true;
		          	$payment_info['gateway_public_name'] = $this->public_name;
	      			$payment_info['transaction_id'] = $mj_order->order_number;  
			      	$payment_info['method'] = 'mijireh';
			        $payment_info['total'] = $mj_order->total;
			        $payment_info['currency'] = $settings['gateways']['mijireh']['currency'];
					
		      		//status's are stored as an array with unix timestamp as key
				  	$payment_info['status'][$timestamp] = $status;
					
		      		if ($mp->get_order($order_id)) {
		        		$mp->update_order_payment_status($order_id, $status, $paid);
		      		} else {
						$cart = get_transient('mp_order_' . $order_id . '_cart');
			  			$shipping_info = get_transient('mp_order_' . $order_id . '_shipping');
						$user_id = get_transient('mp_order_' . $order_id . '_userid');
					  	
		        		$success = $mp->create_order($order_id, $cart, $shipping_info, $payment_info, $paid, $user_id);
						
						//if successful delete transients
		        		if ($success) {
		        			delete_transient('mp_order_' . $order_id . '_cart');
	        				delete_transient('mp_order_' . $order_id . '_shipping');
							delete_transient('mp_order_' . $order_id . '_userid');
		        		}
						
		      		} 
					
					wp_redirect( mp_checkout_step_url('confirmation') ); exit;
					
		  		} catch (Mijireh_Exception $e) {
					
			    	$mp->cart_checkout_error( __('Mijireh Error : ', 'patsatech-marketpress-mijireh') . $e->getMessage() );
					
		  		}
				
		    }elseif( isset( $_POST['page_id'] ) ){
		    	if( isset( $_POST['access_key'] ) && $_POST['access_key'] == $this->access_key ) {
		        	wp_update_post( array( 'ID' => $_POST['page_id'], 'post_status' => 'private' ) );
		    	}
		    }
		}
	}
	
	mp_register_gateway_plugin( 'MP_Gateway_Mijireh', 'mijireh', __('Mijireh', 'patsatech-marketpress-mijireh') );
	
}

?>