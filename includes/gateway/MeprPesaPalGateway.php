<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( !class_exists( 'MeprBaseRealGateway' ) ) {
	include_once MEPR_LIB_PATH . '/MeprBaseRealGateway.php';
}
class MeprPesaPalGateway extends MeprBaseRealGateway {

	public static $pesapal_plan_id_str = '_mepr_pesapal_plan_id';

	/**
	 * Consumer
	 *
	 * @var PesaPalOAuthConsumer
	 */
	public $consumer;

	/**
	 * Signature Method
	 *
	 * @var PesaPalOAuthSignatureMethod_HMAC_SHA1
	 */
	public $signature_method;

	/**
	 * Base URL
	 *
	 * @var string
	 */
	public $base_url = '';

	/**
	 * Token
	 * 
	 * @var string
	 */
	public $token;

	/**
	 * Params
	 * 
	 * @var string
	 */
	public $params;

	/**
	 * 
	 * @var string
	 */
	public $gatewayURL;

	/**
	 * 
	 * @var string
	 */
	public $QueryPaymentStatus;

	/**
	 * 
	 * @var string
	 */
	public $QueryPaymentStatusByMerchantRef;

	/**
	 * 
	 * @var string
	 */
	public $querypaymentdetails;

	public function __construct() {
		$this->name = __( 'PesaPal', 'memberpress');
		$this->icon = MPPPAL_ASSETS_URL . '/img/pesapal.png';
		$this->desc = __( 'Pay with PesaPal', 'memberpress' );
		$this->set_defaults();
		$this->has_spc_form = true;
		
		$this->capabilities = array(
			'process-credit-cards',
			'process-payments',
			'process-refunds',
			'create-subscriptions',
			'cancel-subscriptions',
			'update-subscriptions',
			'suspend-subscriptions',
			'resume-subscriptions',
			'send-cc-expirations'
		);
	
		// Setup the notification actions for this gateway
		$this->notifiers = array(
			'ipn' 		=> 'listener',
			'cancel' 	=> 'cancel_handler',
      		'return' 	=> 'return_handler'
		);
	}

	public function load( $settings ) {
		$this->settings = (object) $settings;
		$this->set_defaults();
	}

	protected function set_defaults() {
		if ( !isset( $this->settings ) ) {
			$this->settings = array();
		}

		$this->settings = (object) array_merge(
			array(
				'gateway' 				=> 'MeprPesaPalGateway',
				'id' 					=> $this->generate_id(),
				'label' 				=> '',
				'use_label' 			=> true,
				'use_icon' 				=> true,
				'use_desc' 				=> true,
				'email' 				=> '',
				'sandbox' 				=> false,
				'force_ssl' 			=> false,
				'debug' 				=> false,
				'test_mode' 			=> false,
				'use_pespal_checkout' 	=> false,
				'churn_buster_enabled' 	=> false,
				'churn_buster_uuid' 	=> '',
				'public_key'			=> '',
				'secret_key'			=> '',
				'connect_status' 		=> false,
				'service_account_id' 	=> '',
				'service_account_name' 	=> '',
			),
			(array) $this->settings
		);

		$this->id 					= $this->settings->id;
		$this->label 				= $this->settings->label;
		$this->use_label 			= $this->settings->use_label;
		$this->use_icon 			= $this->settings->use_icon;
		$this->use_desc 			= $this->settings->use_desc;
		$this->connect_status 		= $this->settings->connect_status;
		$this->service_account_id 	= $this->settings->service_account_id;
		$this->service_account_name = $this->settings->service_account_name;
		$this->has_spc_form 		= $this->settings->use_pespal_checkout ? false : true;
		//$this->recurrence_type = $this->settings->recurrence_type;

		$this->settings->public_key = trim( $this->settings->public_key );
		$this->settings->secret_key = trim( $this->settings->secret_key );
		if ( $this->is_test_mode() ) {
			$this->base_url	= 'http://demo.pesapal.com/';
		} else {
			$this->base_url	= 'https://www.pesapal.com/';
		}

		$this->consumer 						= new PesaPalOAuthConsumer( $this->settings->public_key, $this->settings->secret_key );
		$this->signature_method  				= new PesaPalOAuthSignatureMethod_HMAC_SHA1();
		$this->token 							= $this->params = NULL;
		
		//PesaPal End Points
		$this->gatewayURL 						= $this->base_url . 'api/PostPesapalDirectOrderV4';
		$this->QueryPaymentStatus 				= $this->base_url . 'API/QueryPaymentStatus';
		$this->QueryPaymentStatusByMerchantRef  = $this->base_url . 'API/QueryPaymentStatusByMerchantRef';
		$this->querypaymentdetails 				= $this->base_url . 'API/querypaymentdetails';
	}

	public function listener() {
		$_POST = wp_unslash( $_POST );
		$this->email_status( "PesaPal IPN Recieved\n" . MeprUtils::object_to_string($_POST, true) . "\n", $this->settings->debug );
		return $this->process_ipn();
	}

	public function display_options_form() {
		$mepr_options = MeprOptions::fetch();

		$public_key 	= trim( $this->settings->public_key );
		$secret_key 	= trim( $this->settings->secret_key );
		$sandbox      	= ( $this->settings->test_mode == 'on' || $this->settings->test_mode == true );
		$debug        	= ( $this->settings->debug == 'on' || $this->settings->debug == true );
		?>
		<table>
			<tr>
				<td><?php _e('Customer Key*:', 'memberpress'); ?></td>
				<td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][public_key]" value="<?php echo $public_key; ?>" /></td>
			</tr>
			<tr>
				<td><?php _e('Customer Secret*:', 'memberpress'); ?></td>
				<td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][secret_key]" value="<?php echo $secret_key; ?>" /></td>
			</tr>
			<tr>
				<td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][test_mode]"<?php echo checked($sandbox); ?> />&nbsp;<?php _e('Use PesaPal Sandbox', 'memberpress'); ?></td>
			</tr>
			<tr>
				<td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][debug]"<?php echo checked($debug); ?> />&nbsp;<?php _e('Send PesaPal Debug Emails', 'memberpress'); ?></td>
			</tr>
			<tr>
				<td><?php _e('PesaPal IPN URL:', 'memberpress'); ?></td>
				<td><?php MeprAppHelper::clipboard_input( $this->notify_url('ipn') ); ?></td>
			</tr>
			<?php MeprHooks::do_action('mepr-pesapal-options-form', $this); ?>
		</table>
		<?php
	}


	public function validate_options_form( $errors ) {
		$mepr_options = MeprOptions::fetch();
	
		if( !isset($_POST[$mepr_options->integrations_str][$this->id]['public_key']) ||
			empty( $_POST[$mepr_options->integrations_str][$this->id]['public_key']) ) {
			$errors[] = __( "PesaPal Customer Key field can't be blank.", 'memberpress' );
		} else if( !isset($_POST[$mepr_options->integrations_str][$this->id]['secret_key']) ||
			empty($_POST[$mepr_options->integrations_str][$this->id]['secret_key']) ) {
			$errors[] = __("PesaPal Customer Secret field can't be blank.", 'memberpress');
		}
	
		return $errors;
	 }

	private function process_ipn() {
		$reference 		= $_REQUEST['pesapal_merchant_reference'];
		$tracking_id 	= $_REQUEST['pesapal_transaction_tracking_id'];
		$notification 	= $_REQUEST['pesapal_notification_type'];
		$obj 			= MeprTransaction::get_one_by_trans_num( $reference );
		if ( $obj ) {
			$txn = new MeprTransaction();
			$txn->load_data( $obj );
			
			$this->complete_transaction( $reference, $tracking_id, $txn );
		}
		return "pesapal_notification_type=$notification&pesapal_transaction_tracking_id=$tracking_id&pesapal_merchant_reference=$reference";
	}

	/** Used to record a successful recurring payment by the given gateway. It
     * should have the ability to record a successful payment or a failure. It is
     * this method that should be used when receiving an IPN from PayPal or a
     * Silent Post from Authorize.net.
     */
	public function record_subscription_payment() { }


	public function record_payment_failure() { }

	public function record_payment() {
		$this->email_status( "Starting record_payment: " . MeprUtils::object_to_string($_REQUEST), $this->settings->debug );
		return false;
	}

	public function process_trial_payment($transaction) { }
  	public function record_trial_payment($transaction) { }

	public function process_refund( $txn) {}
	public function record_refund() {}
	public function process_create_subscription($transaction){}
	public function record_create_subscription(){}
	public function process_update_subscription($subscription_id){}

	public function record_update_subscription(){}

  	public function process_suspend_subscription($subscription_id){}

  	public function record_suspend_subscription(){}

  	public function process_resume_subscription($subscription_id){}

  	public function record_resume_subscription(){}

  	public function process_cancel_subscription($subscription_id){}


  	public function record_cancel_subscription(){}

 
  	public function enqueue_payment_form_scripts(){}


	public function validate_payment_form($errors){}
		
	public function display_update_account_form($subscription_id, $errors=array(), $message=""){}

 	public function validate_update_account_form($errors=array()){}

	public function process_update_account_form($subscription_id){}

  	public function is_test_mode(){
		return ( isset($this->settings->test_mode) && $this->settings->test_mode );
	}

  	public function force_ssl(){
	  return false;
  	}

	public function process_payment( $txn ) {
		if ( isset( $txn ) and $txn instanceof MeprTransaction) {
			$usr = $txn->user();
			$prd = $txn->product();
		} else {
			throw new MeprGatewayException( __('Payment was unsuccessful, please check your payment details and try again.', 'memberpress') );
		}

		$this->email_status( 'PesaPal Charge Happening Now ... ' . MeprUtils::object_to_string($args), $this->settings->debug );

		$mepr_options = MeprOptions::fetch();

		$reference 		= $_REQUEST['pesapal_merchant_reference'];
		$tracking_id 	= $_REQUEST['pesapal_transaction_tracking_id'];

		if ( $reference && $tracking_id ) {
			$this->complete_transaction( $reference, $tracking_id, $txn );
		}
		return false;
	}

	/** This gets called on the 'init' hook when the signup form is processed ...
	* this is in place so that payment solutions like paypal can redirect
	* before any content is rendered.
	*/
	public function process_signup_form($txn) {

	}

	public function display_payment_page($txn) {
		
	}

	public function display_payment_form( $amount, $user, $product_id, $txn_id ) {
		$mepr_options 	= MeprOptions::fetch();
		$prd 			= new MeprProduct( $product_id );
		$txn 			= new MeprTransaction( $txn_id );
		$invoice 		= MeprTransactionsHelper::get_invoice( $txn );
		
		echo $invoice;

		$order_xml 	= $this->generate_xml( 
				$amount, 
				$txn_id, 
				$user,
				$mepr_options->currency_code
		);

		$url = PesaPalOAuthRequest::from_consumer_and_token( $this->consumer, $this->token, "GET", $this->gatewayURL, $this->params );
		$url->set_parameter( "oauth_callback", $this->notify_url('return') );
		$url->set_parameter( "pesapal_request_data", $order_xml );
		$url->sign_request( $this->signature_method, $this->consumer, $this->token );
		?>
		<div class="mp_wrapper mp_payment_form_wrapper">
			<img class="pesapress_loading_preloader" src="<?php echo MPPPAL_ASSETS_URL; ?>/img/spinner.gif" alt="loading" style="position:absolute;"/>
			<iframe class="memberpress_pesapal_loading_frame" src="<?php echo $url; ?>" width="100%" height="700px"  scrolling="yes" frameBorder="0">
				<p><?php _e( 'Browser unable to load iFrame', 'memberpress' ); ?></p>
			</iframe>
		</div>
		<script>
			jQuery(document).ready(function () {
				jQuery('.memberpress_pesapal_loading_frame').on('load', function () {
					jQuery('.pesapress_loading_preloader').remove();
				});
			});
		</script>
		<?php
	}

	/**
	 * Complete transaction
	 */
	private function complete_transaction( $reference, $tracking_id, $txn ) {
		$transaction_status = $this->get_transaction_details( $reference, $tracking_id );
		$status 			= $transaction_status['status'];

		$this->email_status( 'PesaPal Charge: ' . $status . ' : tracking id ' . $tracking_id, $this->settings->debug );

		$txn->trans_num = $tracking_id;
		$txn->store();

		if ( $txn->status == MeprTransaction::$complete_str ) {
			return;
		}

		switch ( $status ) {
			case 'PENDING':
				$txn->status    = MeprTransaction::$pending_str;
				break;
			case 'COMPLETED':
				$txn->status    = MeprTransaction::$complete_str;
				break;
			case 'FAILED':
				$txn->status    = MeprTransaction::$failed_str;
				break;
			default:
				$txn->status    = MeprTransaction::$pending_str;
				break;
		}

		$upgrade = $txn->is_upgrade();
		$downgrade = $txn->is_downgrade();

		$event_txn = $txn->maybe_cancel_old_sub();
		$txn->store();

		$this->email_status("Standard Transaction\n" . MeprUtils::object_to_string($txn->rec, true) . "\n", $this->settings->debug);

		$prd = $txn->product();

		if( $prd->period_type=='lifetime' ) {
			if ( $upgrade ) {
				$this->upgraded_sub( $txn, $event_txn );
			} else if( $downgrade ) {
				$this->downgraded_sub( $txn, $event_txn );
			} else {
				$this->new_sub( $txn );
			}

			MeprUtils::send_signup_notices( $txn );
		}

		MeprUtils::send_transaction_receipt_notices( $txn );
		MeprUtils::send_cc_expiration_notices( $txn );
	}


	/**
	 * Generate Payment XML
	 */
	private function generate_xml( $total, $reference, $user, $currency_code, $phone = '' ) {
		$name = trim( $user->display_name );
		if ( ! $name || empty( $name ) ) {
			if ( $user->first_name ) {
				$name = $user->first_name . ' ' . $user->last_name;
			} else {
				$name = $user->user_login;
			}
			$name = ucwords( strtolower( $name ) );
		}
		$name_split = explode( " ", $name );
		$first_name = $name;
		$last_name 	= $name;
		if ( count( $name_split ) > 1 ) {
			$first_name = $name_split[0];
			$last_name 	= $name_split[1];
		}
		$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
			<PesapalDirectOrderInfo xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\"
			Amount=\"" . $total . "\"
			Description=\"Order from " . bloginfo('name') . ".\"
			Type=\"MERCHANT\"
			Reference=\"" . $reference . "\"
			FirstName=\"" . $first_name . "\"
			LastName=\"" . $last_name . "\"
			Email=\"" . $user->user_email . "\"
			PhoneNumber=\"" . $phone . "\"
			Currency=\"" . $currency_code . "\"
			xmlns=\"http://www.pesapal.com\" />";
		
		return htmlentities($xml);
	}

	/**
	 * Handle HTTP requests
	 * 
	 * @param string  $request_status
	 *
	 * @return array
	 **/
	private function do_http( $request_status ) {

		$response = wp_remote_get( $request_status, array(
			'sslverify' => false
		) );

		$response = wp_remote_retrieve_body( $response );
		return $response;
	}


	/**
	 * Get Transaction Details
	 */
	private function get_transaction_details( $merchant_reference, $tracking_id ) {

		$request_status = PesaPalOAuthRequest::from_consumer_and_token(
							$this->consumer, 
							$this->token, 
							"GET", 
							$this->querypaymentdetails, 
							$this->params
						);

		$request_status->set_parameter( "pesapal_merchant_reference", $merchant_reference );
		$request_status->set_parameter( "pesapal_transaction_tracking_id",$tracking_id );
		$request_status->sign_request( $this->signature_method, $this->consumer, $this->token );

		$responseData 		= $this->do_http( $request_status );
	  
		$pesapalResponse 	= explode( ",", $responseData );
		$response			= array(
								'tracking_id'			=> $pesapalResponse[0],
								'payment_method'		=> $pesapalResponse[1],
								'status'				=> $pesapalResponse[2],
								'merchant_reference'	=> $pesapalResponse[3]
						  	);
						 
		return $response;
	}

	private function status_request( $transaction_id, $merchant_ref ) {
		$request_status = PesaPalOAuthRequest::from_consumer_and_token( 
							$this->consumer, 
							$this->token, 
							"GET", 
							$this->gatewayURL, 
							$this->params
						);
		$request_status->set_parameter( "pesapal_merchant_reference", $merchant_ref );
		$request_status->set_parameter( "pesapal_transaction_tracking_id", $transaction_id );
		$request_status->sign_request( $this->signature_method, $this->consumer, $this->token );
	  
		return $this->check_transaction_status( $merchant_ref );
	}
	
	
	/**
	 * Check Transaction status
	 *
	 *
	 */
	private function check_transaction_status( $merchant_ref, $tracking_id = NULL ) {
		$query_url = ( $tracking_id ) ? $this->QueryPaymentStatus : $this->QueryPaymentStatusByMerchantRef;
  
		//get transaction status
		$request_status = PesaPalOAuthRequest::from_consumer_and_token(
							$this->consumer, 
							$this->token, 
							"GET", 
							$query_url, 
							$this->params
						);

		$request_status->set_parameter( "pesapal_merchant_reference", $merchant_ref );

		if ( $tracking_id )
			$request_status->set_parameter( "pesapal_transaction_tracking_id",$tracking_id );
  
		$request_status->sign_request( $this->signature_method, $this->consumer, $this->token );

		return $this->do_http( $request_status );
	}
}
?>