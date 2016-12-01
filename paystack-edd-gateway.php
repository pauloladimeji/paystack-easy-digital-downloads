<?php 
/*
Plugin Name: Paystack.co Easy Digital Downloads Gateway
Plugin URI: http://www.gospelbox.com.ng/
Description: Accept payments using the Paystack.co Payment Gateway
Version: 1.0.0
Author: Paul Oladimeji
Author URI: http://pauloladimeji.me/
*/


if ( ! class_exists('EDD_Paystack') ):

/**
* @var instance - the main EDD_Paystack class.
* singleton
*/

class EDD_Paystack
{
	private static $instance;

	//Plugin file
	public $file;
	//Plugin path
	public $plugin_path;
	//Plugin URL
	public $plugin_url;
	// plugin version
	public $version;

	/**
	* implement singleton
	* for main EDD_Paystack instance
	**/
	public static function instance() {
		if ( !isset( self::$instance ) ) {
			self::$instance = new EDD_Paystack(__FILE__);
		}
		return self::$instance;
	}

	/**
	* Constructor - Setup Gateway default values
	* @since 1.0.0
	* @return void
	**/
	private function __construct( $file )
	{
		$this->version = '1.0.0';
		$this->file = $file;
		$this->plugin_url = trailingslashit( plugins_url( '', $plugin = $file ) );
		$this->plugin_path = trailingslashit( dirname( $file ) );

		$this->msg = null; //error notices init

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "Paystack.co", 'mm-paystack' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( "Securely take payments using local and foreign Mastercard, VISA and Verve cards via Paystack.co", 'mm-paystack' );

		/**
		 *	admin hooks
		 */
		if ( is_admin() ):
			//add payment gateway settings to EDD
			add_filter( 'edd_settings_gateways', array($this, 'add_gateway_settings') );
		endif;

		/**
		 *	client hooks
		 */

		//register gateway
		add_filter( 'edd_payment_gateways', array($this, 'register_gateway') );
		//enable naira currency on Easy Digital Downloads
		add_filter( 'edd_currencies', array($this, 'po_paystack_naira_currency') );
		// enable Paystack icon on checkout page
		add_filter( 'edd_accepted_payment_icons', array($this, 'po_paystack_payment_icon') );
		//credit card form
		add_filter( 'edd_paystack_cc_form', array($this, 'po_paystack_cc_form') );
		//process payment
		add_filter( 'edd_gateway_paystack', array($this, 'process_payment') );

		/**
		 *	Plugin warnings & notices
		 */

		// Check for SSL and send warning if not set
		add_action( 'admin_notices', array($this, 'do_ssl_check'));		

		//append Paystack scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	} //end __construct()

	/**
	 * payment_scripts function.
	 *
	 * Outputs JS & CSS used for Paystack payment
	 *
	 * @access public
	 */
	public function payment_scripts() {
		//don't run if page is not checkout page
		if (! edd_is_checkout() ) {
			return;
		}
		wp_enqueue_script( 'paystack-inline', 'https://js.paystack.co/v1/inline.js');
		wp_enqueue_script( 'edd-paystacker.js', plugins_url( 'assets/edd-paystacker.js',  __FILE__ ), array( 'paystack-inline' ), $this->version, true );
		wp_enqueue_script( 'jquery.growl.js', plugins_url( 'assets/jquery.growl.js',  __FILE__ ), array( 'paystack-inline' ), null, true );

		wp_enqueue_style('edd-paystacker.css', plugins_url( 'assets/edd-paystacker.css',  __FILE__ ));
		wp_enqueue_style('jquery.growl.css', plugins_url( 'assets/jquery.growl.css',  __FILE__ ));
	}

	/**
	*	add our icon on checkout page
	*	
	*	@access public
	*	
	*/
	
	public function po_paystack_payment_icon( $icons ) {
		$icons[plugins_url( 'assets/paystack-edd.png',  __FILE__ )] = 'Paystack';
		return $icons;
	} //end po_paystack_payment_icon()


	/**
	*	add Naira to EDD currencies
	*	
	*	@access public
	*/
	public function po_paystack_naira_currency($currencies) {
		$currencies['NGN'] = __('Naira (&#8358;)', 'po_paystack');
		return $currencies;
	} // end naira_currency

	/**
	*	register Paystack to EDD. $gateways object runs during hooks
	*	
	*	@access public
	*/
	public function register_gateway( $gateways ) {
		$gateways['paystack'] = array( 'admin_label' => 'Paystack.co Payment Gateway', 'checkout_label' => __('Paystack: Pay via credit/debit cards', 'po_paystack') );
		return $gateways;
	}

	/**
	 * custom CC form on checkout page
	 * 
	 * hidden fields retrieve params for Paystack
	 *
	 * @access public
	 */
	public function po_paystack_cc_form() {
		global $edd_options;
		$txc = $this->genRefCode();
		$checked = 1;
		$paystack_public = edd_is_test_mode() ? $edd_options['test_public_key'] : $edd_options['live_public_key'];
		?>
		<input id="txcode" name="txcode" type="hidden" value="<?php echo esc_attr($txc); ?>"/>
		<input id="cptd" name="cptd" type="hidden" value="0" />
		<div id="new_paystack" <?php if ( $checked === 1 ) : ?>style="display:none;"<?php endif; ?>
			data-key="<?php echo esc_attr($paystack_public); ?>"
			data-ref="<?php echo esc_attr($txc); ?>"
			data-amount="<?php echo (edd_get_cart_total())*100; ?>" <?php //Converted to kobo ?>
			>
		</div>
		<?php
	}

	/**
	 * init_form_fields function.
	 *
	 * Build the admin / settings fields for Paystack.co Gateway
	 *
	 * @access public
	 * 
	 * TODO
	 *	enable Paystack settings only when currency is NGN
	 */	
	public function add_gateway_settings( $settings ) {
		$paystack_settings = array(
			array(
				'id'	=>	'paystack_gateway_settings',
				'name'	=>	'<h3>' . __('Paystack.co Payment Gateway', 'po_paystack') . '</h3>',
				'desc'	=>	'<p>' . __('Paystack.co enables you to securely take payments using local and foreign Mastercard, VISA and Verve cards', 'po_paystack') . '</p>',
				'type'	=>	'header'
			),
			array(
				'id'	=>	'test_public_key',
				'name'	=>	__( 'Paystack.co Public Key', 'po_paystack' ),
				'desc'	=>	__( 'This is the Test Public Key provided by Paystack.co when you signed up for an account.', 'po_paystack' ),
				'type'	=>	'text',
			),
			array(
				'id'	=>	'test_secret_key',
				'name'	=>	__( 'Paystack.co Test Secret Key', 'po_paystack' ),
				'desc'	=>	__( 'This is the Test Secret Key provided by Paystack.co when you signed up for an account.', 'po_paystack' ),
				'type'	=>	'text',
			),
			array(
				'id'	=>	'live_public_key',
				'name'	=>	__( 'Paystack.co Live Public Key', 'po_paystack' ),
				'desc'	=>	__( 'This is the Live Public Key provided by Paystack.co when you activated your account.', 'po_paystack' ),
				'type'	=>	'text',
			),
			array(
				'id'	=>	'live_secret_key',
				'name'	=>	__( 'Paystack.co Live Secret Key', 'po_paystack' ),
				'desc'	=>	__( 'This is the Live Secret Key provided by Paystack.co when you activated your account.', 'po_paystack' ),
				'type'	=>	'text',
			),
		);
		
		return array_merge($settings, $paystack_settings);
	}

	/**
	 * process_payment function.
	 *
	 * Submit payment and handle response
	 *
	 * @access public
	 */
	public function process_payment( $purchase_data ) {
		//edd_options contains the values of the admin settings
		global $edd_options;

		if (edd_is_test_mode()) {
			$paystack_public = $edd_options['test_public_key'];
			$paystack_secret = $edd_options['test_secret_key'];
		} else {
			$paystack_public = $edd_options['live_public_key'];
			$paystack_secret = $edd_options['live_secret_key'];
		}

		//txcode POSTed from payment form
		$txcode = (isset( $_POST['txcode'] ) ) ? $_POST['txcode'] : null;


		/**
		 * check for checkout fields errors
		 *
		 */

		// check if there is a gateway name
		if ( !isset( $purchase_data['post_data']['edd-gateway'] ) ):
			return;
		endif;

		// get EDD errors
		$errors = edd_get_errors();

		// Paystack errors
		$paystack_error = null;

		/**
		 * end checkout fields error checks
		 */

		// if no errors
		if ( !$errors ):
			// record purchase summary
			$summary = edd_get_purchase_summary( $purchase_data, false );
			// cart quantity
			$quantity = edd_get_cart_quantity();

			/**
			 * setup the payment data
			 */
			$payment_data = array( 
				'price' => $purchase_data['price'], 
				'date' => $purchase_data['date'], 
				'user_email' => $purchase_data['user_email'],
				'purchase_key' => $purchase_data['purchase_key'],
				'currency' => $edd_options['currency'],
				'downloads' => $purchase_data['downloads'],
				'cart_details' => $purchase_data['cart_details'],
				'user_info' => $purchase_data['user_info'],
				'status' => 'pending'
			);
	 
			// record the pending payment
			$payment = edd_insert_payment($payment_data);
			$order_id = $payment;

			if ( !$payment ):
				// Record the error
				edd_record_gateway_error( __( 'Payment Error', 'po_paystack' ), sprintf( __( 'Payment creation failed before loading Paystack. Payment data: %s', 'po_paystack' ), json_encode( $payment_data ) ), $payment );
				// Problems? send back
				edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
			else:
				if ( !$order_id || !$paystack_public ):
					edd_record_gateway_error( __( 'Invalid transaction', 'po_paystack' ), sprintf( __( 'Invalid transaction; possible hack attempt. Payment data: %s', 'po_paystack' ), json_encode( $payment_data ) ), $payment );
					edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
				endif;
				if ( !$txcode ):
					$error = "Error! An invalid transaction code was reported.";
					edd_update_payment_status($order_id, 'failed');
					throw new Exception( __($error));
				else:
					$amount = ($payment_data['price'])*100; //convert to kobo
					if (intval($amount) < 100):
						$error = "Invalid transaction. Paystack cannot process orders under 100 kobo in value. Transaction code: ".$txcode;
						edd_update_payment_status($order_id, 'failed');
						throw new Exception( __($error));
					endif;

					$email = strtolower($payment_data['user_email']);
					require_once dirname(__FILE__) . '/paystack-class/Paystack.php';
					// Create the library object
					$paystack = new Paystack( $paystack_secret );
					list($headers, $body, $code) = $paystack->transaction->verify([
							'reference'=> $txcode
						  ]);
					
					$resp = $body;

					if (array_key_exists("status", $resp) && !$resp["status"]):
						$error = "Failed with message from Paystack: " . $resp["message"];
						edd_insert_payment_note( $order_id, __($error) );
						edd_update_payment_status( $order_id, 'failed' );
						throw new Exception( __($error));						
					elseif (strtolower($resp["data"]["customer"]["email"])!==$email):
						$error = "Invalid customer email associated with Transaction code:".$txcode." and Paystack reference: ".$resp["data"]['reference'].". Possible hack attempt.";
						edd_insert_payment_note( $order_id, __($error) );
						edd_update_payment_status( $order_id, 'failed' );
						throw new Exception( __($error));						
					else:
						// Authcode and Authdesc. To be used in future version, for recurrent billing
						$authcode = $resp["data"]["authorization"]["authorization_code"];
						$authdesc = $resp["data"]["authorization"]["description"];
						$paystackref = $resp["data"]["reference"];						

						// Complete the order. once a transaction is successful, set the purchase status to complete
						edd_update_payment_status( $payment, 'complete' );

						// record transaction ID, or any other notes you need
						edd_insert_payment_note( $payment, "Paystack.co payment completed (using ".strtoupper($authdesc)." and Transaction code:".$txcode.") with Paystack reference:".$paystackref );
						// go to the success page
						edd_send_to_success_page();
					endif;
				endif;
			endif;
		else: // errors present
			$fail = true;
		endif;

		if ( $fail !== false ):
			// if errors are present, send the user back to the purchase page so they can be corrected
			edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
		endif;
	}

	/**
	 * do_ssl_check function.
	 *
	 * Check if we are forcing SSL on checkout pages. Advised but not required by gateway.
	 *
	 * @access public
	 */	
	public function do_ssl_check() {
		// Check for SSL
		if( edd_is_ssl_enforced() ) {
			echo "<div class='error'><p>". sprintf( __( "<strong>%s</strong> payment gateway is enabled but Easy Digital Downloads is not forcing the SSL certificate on your checkout page.<br />For your security, please ensure that you have a valid SSL certificate and that you are forcing Checkout pages to be secured <a href=\"%s\">(check the box 'Force secure checkout').</a>" ), $this->method_title, admin_url( 'admin.php?page=edd-settings&tab=misc&section=checkout' ) ) ."</p></div>";	
			return false;
		}
		return true;		
	}

	/**
	 * is_valid_currency function
	 *
	 * Checks if the currency is valid (Nigerian Naira only), as required by gateway. Returns boolean.
	 *
	 * @access private
	 */	
	private function is_valid_currency(){
		if( edd_get_currency() !== 'NGN' ){
			$this->msg = 'Sorry, Paystack.co doesn\'t support your store currency, set it to Nigerian Naira (&#8358;) <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=edd-settings&tab=general&section=currency">here</a>';
			return false;
		}
		return true;
	}

	/**
	 * genRefcode function.
	 *
	 * Generates reference code for Paystack payment. Returns transaction reference code.
	 *
	 * @access private
	 */
	private function genRefcode(){
		$len = 24;
		$string = "";
		$characters = "0123456789ABCDEFGHJKLMNPQRTUVWXYZ";
		for ($i = 0; $i < $len; $i++):
			$string .= $characters[mt_rand(0, strlen($characters)-1)];
		endfor;
		$uid = preg_replace("/[^A-Z0-9 ]/", '', base64_encode(pack('H*', md5($string . uniqid("", true)))));
		while (strlen($uid) < $len)
			$uid = $uid . uniqid("", true);
		return str_shuffle(trim(substr(str_replace(array('I','L','S','O','1','5','0'), '', preg_replace("/[^A-Z0-9 ]/", '', $uid)), 0, $len)));
	}
}

endif;

function po_paystack_missing_edd() {
	echo '<div class="error"><p>' . sprintf( __( 'Please %sinstall &amp; activate Easy Digital Downloads%s to allow this plugin to work.' ), '<a href="' . admin_url( 'plugin-install.php?tab=search&type=term&s=easy+digital+downloads&plugin-search-input=Search+Plugins' ) . '">', '</a>' ) . '</p></div>';
}

//plugin instance
function edd_paystack() {
	return EDD_Paystack::instance();
}

// loader function for our plugin
function po_edd_paystack_init() {
	// check if Easy Digital Downloads is activated, if not alert user
	if (! class_exists('Easy_Digital_Downloads') ) {
		add_action( 'admin_notices', 'po_paystack_missing_edd' );
	} else {
		edd_paystack(); // load plugin class
	}
}

// tell WordPress to load our plugin
add_action( 'plugins_loaded', 'po_edd_paystack_init', 20 );


?>