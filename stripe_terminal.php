<?php 
/*
 * Plugin Name: Delimp Stripe Terminal Gateway
 * Plugin URI: #
 * Description: WooCommerce Stripe Terminal Gateway with locations
 * Author: Sudhanshu
 * Author URI: #
 * Version: 1.0.1
 */

if ( ! defined( 'TERMINAL_PLUGIN_DIR' ) ) {
	define( 'TERMINAL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'TERMINAL_PLUGIN_URL' ) ) {
	define( 'TERMINAL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
add_filter('jwt_auth_expire', function($exp, $user) {
    // Extend token expiration to 365 days
    return time() + (60 * 60 * 24 * 365); // 365 days
}, 10, 2);
add_filter( 'woocommerce_payment_gateways', 'misha_add_gateway_class' );
function misha_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Misha_Gateway'; // your class name is here
	return $gateways;
}

add_action( 'plugins_loaded', 'misha_init_gateway_class' );
function misha_init_gateway_class() {
    
	class WC_Misha_Gateway extends WC_Payment_Gateway {

 		/**
 		 * Class constructor, more about it in Step 3
 		 */
          public function __construct() {
            $this->id = 'delimpterminal'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Delimp Stripe Terminal';
            $this->method_description = 'Description of Misha payment gateway'; // will be displayed on the options page
        
            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );
        
            // Method with all the options fields
            $this->init_form_fields();
        
            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
            $this->webhook_secret = $this->testmode ? $this->get_option( 'test_webhook_secret' ) : $this->get_option( 'webhook_secret' );
        
            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        
            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            
            // You can also register a webhook here
            add_action( 'woocommerce_api_delimp_terminal', array( $this, 'webhook' ) );
            $this->includes_global();
         }

         public function includes_global() {
            //Load gateway library
            if ( file_exists( TERMINAL_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
                require_once TERMINAL_PLUGIN_DIR . 'vendor/autoload.php';
            }
            
        }

		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
          public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Delimp Stripe Terminal',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Credit Card',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your credit card via our super-cool payment gateway.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_publishable_key' => array(
                    'title'       => 'Test Publishable Key',
                    'type'        => 'text'
                ),
                'test_private_key' => array(
                    'title'       => 'Test Private Key',
                    'type'        => 'password',
                ),
                'test_webhook_secret' => array(
                    'title'       => 'Test Webhook Secret',
                    'type'        => 'text'
                ),
                'publishable_key' => array(
                    'title'       => 'Live Publishable Key',
                    'type'        => 'text'
                ),
                'private_key' => array(
                    'title'       => 'Live Private Key',
                    'type'        => 'password'
                ),
                'webhook_secret' => array(
                    'title'       => 'Live Webhook Secret',
                    'type'        => 'text'
                )
            );
        }

		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
 
            // ok, let's display some description before the payment form
            if( $this->description ) {
                // you can instructions for test mode, I mean test card numbers etc.
                if( $this->testmode ) {
                    $this->description .= 'Stripe Terminal';
                    $this->description  = trim( $this->description );
                }
                // display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }
            //$stripe = new \Stripe\StripeClient($this->private_key);
            //$readers = $stripe->terminal->readers->all(['limit' => 3]);
            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
            echo '<div class="clear"></div></fieldset>';
        }
	 	public function payment_scripts() {
            if (is_wc_endpoint_url('order-received')) {
                wp_enqueue_script( 'terminal', 'https://js.stripe.com/terminal/v1/' );
                wp_enqueue_script( 'custom-terminal-js', TERMINAL_PLUGIN_URL.'js/terminal.js',array('jquery'), time());
                wp_localize_script('custom-terminal-js', 'terminal_obj', array('ajaxurl' => admin_url('admin-ajax.php'))); 
            }
	 	}
		public function validate_fields() {

            return true;

		}
		public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            $data = $order->get_data();
            $stripe = new \Stripe\StripeClient($this->private_key);
            $customer = $stripe->customers->create([
				  'name' => $data['billing']['first_name'].' '.$data['billing']['last_name'],
				  'email'=>$data['billing']['email']
				]);
            $paymentIntent = $stripe->paymentIntents->create([
                'metadata' => ['order_id'=>$order_id], 
                'customer'=>  $customer->id, 
                'amount' => ($data['total']*100),
                'currency' => 'EUR',
                'capture_method'=>'manual',
                //'automatic_payment_methods' => ['enabled' => true,'allow_redirects'=>'never'],
                'payment_method_types'=>['card_present']
            ]);
            $this->add_terminal_order_meta($order_id, 'payment_intent_id', $paymentIntent->id);
            return array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $order )
			);
	 	}

         public function getSubscriptionDetail($product_id){
            $subscriptionInfo = [];
            $subscriptionProductMetaKeys = [
                'subscription'=> '_wps_sfw_product',
                'subscription_number' => 'wps_sfw_subscription_number',
                'subscription_interval' => 'wps_sfw_subscription_interval',
                'subscription_expiry_number' => 'wps_sfw_subscription_expiry_number',
                'subscription_expiry_interval' => 'wps_sfw_subscription_expiry_interval',
                'subscription_interval' => 'wps_sfw_subscription_interval',
                'subscription_initial_signup_price' => 'wps_sfw_subscription_initial_signup_price',
                'subscription_free_trial_number' => 'wps_sfw_subscription_free_trial_number',
                'subscription_free_trial_interval' => 'wps_sfw_subscription_free_trial_interval',
            ];
            foreach($subscriptionProductMetaKeys as $key=>$val){
                $subscriptionInfo[$key] = get_post_meta($product_id, $val, true);
            }
            return (object)$subscriptionInfo;
        }

		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {
            $endpoint_secret = trim($this->webhook_secret);
            $payload = @file_get_contents('php://input');
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
            $event = '';
           
            try {
                $event = \Stripe\Webhook::constructEvent(
                  $payload, $sig_header, $endpoint_secret
                );
              } catch(\UnexpectedValueException $e) {
                http_response_code(400);
                exit();
              } catch(\Stripe\Exception\SignatureVerificationException $e) {
                http_response_code(400);
                exit();
              }

              $stripe = new \Stripe\StripeClient($this->private_key);

              switch ($event->type) {
                case 'payment_intent.payment_failed':{
                    $object = $event->data->object;
                    $order_id = $object->metadata->order_id;
                    $order = wc_get_order( $order_id );
                    $order->update_status('failed');
                    $result = $order->save();
                    echo json_encode(array('order_id' => $order_id,'order'=>$order));
                    exit();
                } break;
                case 'payment_intent.succeeded':{
                    $object = $event->data->object;
                    $order_id = $object->metadata->order_id;
                    $amount_details = $object->amount_details;
                    $order = wc_get_order( $order_id );

                    /**
                     * Add item fee
                     */
                    // Tip fee amount
                    $fee_amount = ($amount_details->tip->amount)?$amount_details->tip->amount/100:0;                    
                    if($fee_amount>0){
                        // Create a fee line item
                        // $item = new WC_Order_Item_Fee();
                        // $item->set_name('Tip');
                        // $item->set_amount($fee_amount);
                        // $item->set_total($fee_amount);
                        // $item->set_tax_class('');
                        // $item->set_taxes(array());

                        // $order->add_item($item);
                        
                        $order->add_fee(array(
                            'name'       => 'Tip',
                            'amount'     => $fee_amount,
                            'tax_class'  => '',
                            'tax'        => 0, // Tax amount, if any
                        ));
                        
                        $order->calculate_totals();

                        $order->save();
                    }
                    echo json_encode(array('order_id' => $order_id,'tip'=>$fee_amount));
                    exit();
                } break;
                case 'payment_method.attached':{
                    $object = $event->data->object;
                    $stripe_payment_method = $object->id;
                    $stripe_customer_id = $object->customer;
                    $default_payment_method = '';
                     try{
                        $default_payment_method = $stripe->customers->update(
                            $stripe_customer_id,[
                            'invoice_settings' => ['default_payment_method' => $stripe_payment_method],
                            ]
                        );
                    }
                    catch (\Stripe\Exception\ApiErrorException $e) {
                        $default_payment_method = $e->getMessage();
                    }
                    echo json_encode(['stripe_payment_method'=>$stripe_payment_method,'stripe_customer_id'=>$stripe_customer_id,'default_payment_method'=>$default_payment_method]);
                    //echo json_encode(array('order_id' => $order_id,'items'=>$items));
                    exit();

                } break;
                case 'charge.captured':{
                     $object = $event->data->object;
                     $stripe_customer_id = $object->customer;
                     $stripe_payment_method = $object->payment_method;
                     $payment_intent = $object->payment_intent;
                     $paid = $object->paid;
                     
                    $order_id = $object->metadata->order_id;
                    $order = new WC_Order( $order_id );
                    $order_detail = $order->get_data();
                    $items = $order->get_items();
                    $customer_id = $order->get_user_id();
                    $customer = new WC_Customer($customer_id);
                    $customer_detail = $customer->get_data();

                    $items = $order->get_items();
                    if($paid=='true'){
                        $note = 'Payment has been done successfully # '.$payment_intent;
                        $order->add_order_note($note);
                        $order->update_status('processing');
                        $order->payment_complete();
                        $subArray = [];
                        foreach ($items as $key=> $item) {
                            $product_id = $item->get_product_id();
                            $subscriptionDetail = $this->getSubscriptionDetail($product_id);
                           
                            if($subscriptionDetail->subscription==='yes'){
                                $product_name = $item->get_name();
                                $product_slug = get_post_field('post_name', $product_id);
                                $product_quantity = $item->get_quantity();
                                $product_subtotal = $item->get_subtotal();
                                $product_total = $item->get_total();
                                $product_plan = $product_slug.'-'.$product_quantity.'-'.$product_total;
                                $subArray[$product_id]['product_plan'] = $product_plan;
                                $subArray[$product_id]['subscription_detail'] = $subscriptionDetail;
                                try{
                                    $stripe_product = $stripe->products->retrieve($product_slug);
                                }
                                catch (\Stripe\Exception\ApiErrorException $e) {
                                    //$result =  $e->getMessage();
                                    $stripe_product = $create_stripe_product = $stripe->products->create([
                                        'name' => $product_name,
                                        'description' => $product_plan,
                                        'id'=>$product_slug,
                                    ]);					
                                }
                                $stripe_product_id = $stripe_product->id;
                                try{
                                    $stripe_plan = $stripe->plans->retrieve($product_plan);
                                }
                                catch (\Stripe\Exception\ApiErrorException $e) {
                                    $trial_period_days = 0;
                                    switch($subscriptionDetail->subscription_free_trial_interval){
                                        case 'month':{
                                            $trial_period_days = $subscriptionDetail->subscription_free_trial_number*30;
                                        } break;
                                        case 'week':{
                                            $trial_period_days = $subscriptionDetail->subscription_free_trial_number*7;
                                        } break;
                                        case 'day':{
                                            $trial_period_days = $subscriptionDetail->subscription_free_trial_number*1;
                                        } break;
                                    }
                                    $subArray[$product_id]['trial_period_days'] = $trial_period_days;
                                    $stripe_plan = $stripe->plans->create([
                                        'id' => $product_plan,
                                        'amount' => $product_total*100,
                                        'currency' => $order_detail['currency'],
                                        'interval_count'=> $subscriptionDetail->subscription_number,
                                        'interval' => $subscriptionDetail->subscription_interval,
                                        'product' => $stripe_product_id,
                                    ]);
                                }
                                $stripe_plan_id = $stripe_plan->id;
                                try{
                                     $trial_period_days = 0;
                                    switch($subscriptionDetail->subscription_free_trial_interval){
                                        case 'month':{
                                            $trial_period_days = $subscriptionDetail->subscription_free_trial_number*30;
                                        } break;
                                        case 'week':{
                                            $trial_period_days = $subscriptionDetail->subscription_free_trial_number*7;
                                        } break;
                                        case 'day':{
                                            $trial_period_days = $subscriptionDetail->subscription_free_trial_number*1;
                                        } break;
                                    }
                                    
                                    $stripe_subscription = $stripe->subscriptions->create([
                                        'customer' =>  $stripe_customer_id,
                                        'items' => [[
                                                'price' => 'price_1PSh1k2eaZ05YrrhTZSoj2AU',
                                                'quantity' => $product_quantity
                                            ]],
                                        'trial_period_days' => $trial_period_days,
                                    ]);
                                    $subArray[$product_id]['stripe_subscription'] = $stripe_subscription;
                                    $subscription_note = 'Subscription has been done successfully # '.$stripe_subscription->id;
                                    $order->add_order_note($subscription_note);
                                    // print_r([
                                    //     'customer' =>  $stripe_customer_id,
                                    //     'items' => [['price' => $stripe_plan_id]],
                                    //     'trial_period_days' => $trial_period_days,
                                    // ]);die;
                                }
                                catch (\Stripe\Exception\ApiErrorException $e) {
                                    $stripe_subscription = $e->getMessage();
                                    $subArray[$product_id]['stripe_subscription'] = $stripe_subscription;
                                    // print_r([
                                    //     'error'=>$stripe_subscription,
                                    //     'customer' =>  $stripe_customer_id,
                                    //     'items' => [['price' => $stripe_plan_id]],
                                    //     'trial_period_days' => $trial_period_days,
                                    // ]);die;
                                }
                            }    
                        }
                    }
                   else{
                       $order->update_status('failed');
                   }
                   $result = $order->save();

                    echo json_encode(['items'=>count($items),'stripe_customer_id'=>$stripe_customer_id,'default_payment_method'=>$default_payment_method,'customer_id'=>$customer_id,'subArray'=>$subArray]);
                    //echo json_encode(array('order_id' => $order_id,'items'=>$items));
                    exit();
                } break;
              }
	 	}

        public function getPaymentClientSecret($orderNumber){
            $order = wc_get_order( $orderNumber );
            $data = $order->get_data();
            $response = [];
            if($data['payment_method']==='delimpterminal' || 1==1){
                $stripe = new \Stripe\StripeClient($this->private_key);
                $paymentIntentId = $this->get_terminal_order_meta($orderNumber,'payment_intent_id');
                $paymentIntentObj = $stripe->paymentIntents->retrieve($paymentIntentId, []);
                $response['status'] = 1;
                $response['paymentIntentObj'] = $paymentIntentObj;
                $response['data'] = $data;
            }
            else{
                $response['status'] = 0;
            }
            return json_encode($response);
        }

        public function capturePaymentIntent($paymentIntentId){
            $response = [];
            $response['status'] = 0;
            try{
                $stripe = new \Stripe\StripeClient($this->private_key);
                $capture_payment_intent = $stripe->paymentIntents->capture(
                    $paymentIntentId,
                    []
                );
                if($capture_payment_intent->metadata && $capture_payment_intent->metadata->order_id){
                    // $order_id = $capture_payment_intent->metadata->order_id;
                    // $order = wc_get_order( $order_id );
                    // $order->update_status('processing');
                    // $order->save();
                }
                $response['status'] = 1;
                $response['data'] = $capture_payment_intent;
            }
            catch(Exception $e) {
                $response['message'] = $e->getMessage();
            }
            return json_encode($response);
        }

        // Function to get meta value by meta key and order ID
        public function get_terminal_order_meta($order_id, $meta_key, $single = true) {
            global $wpdb;

            $table_name = $wpdb->prefix . 'terminal_ordermeta';
            $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM $table_name WHERE order_id = %d AND meta_key = %s",
                $order_id,
                $meta_key
            )
            );

            if ($single && is_serialized($result)) {
            return maybe_unserialize($result);
            }

            return $result;
        }

        // Function to update meta value by meta key and order ID
        public function update_terminal_order_meta($order_id, $meta_key, $meta_value) {
            global $wpdb;

            $table_name = $wpdb->prefix . 'terminal_ordermeta';
            $wpdb->update(
            $table_name,
            array('meta_value' => maybe_serialize($meta_value)),
            array('order_id' => $order_id, 'meta_key' => $meta_key)
            );
        }

        // Function to add new meta data for an order
        public function add_terminal_order_meta($order_id, $meta_key, $meta_value, $unique = false) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'terminal_ordermeta';

            if ($unique && get_terminal_order_meta($order_id, $meta_key) !== null) {
            // Meta key already exists for this order
            return false;
            }

            $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'meta_key' => $meta_key,
                'meta_value' => maybe_serialize($meta_value),
            )
            );

            return true;
        }

        // Function to delete meta data by meta key and order ID
        public function delete_terminal_order_meta($order_id, $meta_key) {
            global $wpdb;

            $table_name = $wpdb->prefix . 'terminal_ordermeta';
            $wpdb->delete($table_name, array('order_id' => $order_id, 'meta_key' => $meta_key));
        }
 	}
    
    add_action('wp_ajax_terminal_get_token', 'terminal_get_token');
    add_action('wp_ajax_nopriv_terminal_get_token', 'terminal_get_token');
    
    if (!function_exists('update_customer_payment_method')) {
        function terminal_get_token(){
            try{
                $instance = new WC_Misha_Gateway();
                $stripe = new \Stripe\StripeClient($instance->private_key);
                $token = $stripe->terminal->connectionTokens->create([]);
                echo json_encode(array('secret' => $token->secret));
                exit();
            }
            catch(Exception $e) {
                echo json_encode(array('message' => $e->getMessage()));
                exit();
            }
        }
    }

    if (!function_exists('create_payment_intent')) {
        function create_payment_intent(){
            try{
                $instance = new WC_Misha_Gateway();
                $stripe = new \Stripe\StripeClient($instance->private_key);

                $OrderNumber = (!empty($_POST['order_id']))?$_POST['order_id']:'';
                $order = new WC_Order( $OrderNumber );
                $order_detail = $order->get_data();

                $customer_id = $order->get_user_id();
                $customer = new WC_Customer($customer_id);

                $stripe_customer_id = get_user_meta( $customer_id, 'stripe_customer_id', true );

                if(!$stripe_customer_id){
                    $customer_detail = $customer->get_data();

                    $customer = $stripe->customers->create([
                        'name' => $customer_detail['first_name'].' '.$customer_detail['last_name'],
                        'email'=>$customer_detail['email'],
                        'address' => [
                            'line1' => $customer_detail['billing']['address_1'],
                            'line1' => $customer_detail['billing']['address_2'],
                            'city' => $customer_detail['billing']['city'],
                            'state' => $customer_detail['billing']['state'],
                            'postal_code' => $customer_detail['billing']['postcode'],
                            'country' => $customer_detail['billing']['country'],
                        ],
                        'shipping' => [
                            'name' => $customer_detail['shipping']['first_name'].' '.$customer_detail['shipping']['last_name'],
                            'address' => [
                                'line1' => $customer_detail['shipping']['address_1'],
                            'line1' => $customer_detail['shipping']['address_2'],
                            'city' => $customer_detail['shipping']['city'],
                            'state' => $customer_detail['shipping']['state'],
                            'postal_code' => $customer_detail['shipping']['postcode'],
                            'country' => $customer_detail['shipping']['country'],
                            ]
                        ]
                    ]);
                    
                    $stripe_customer_id = $customer->id;

                    update_user_meta( $customer_id, 'stripe_customer_id', $stripe_customer_id );
                }
                
                $paymentIntent = $stripe->paymentIntents->create([
                'payment_method_types'=> ['card_present'],
                'capture_method'=> 'manual',
                'amount' => ($order_detail['total']*100),
			    'currency' => $order_detail['currency'],
                'description' => 'charge payment',
                'metadata' => ['order_id'=>$OrderNumber], 
                'customer'=>  $stripe_customer_id,
                'setup_future_usage' => 'off_session',
                ]);
                // echo json_encode($paymentIntent);
                echo json_encode(['intent'=>$paymentIntent->id,'secret'=>$paymentIntent->client_secret]);
                exit();
            }
            catch(Exception $e) {
                echo json_encode(array('message' => $e->getMessage()));
                exit();
            }
        }
    }

    if (!function_exists('capture_payment_intent')) {
        function capture_payment_intent(){
            try{
                $instance = new WC_Misha_Gateway();
                $stripe = new \Stripe\StripeClient($instance->private_key);
                $payment_intent_id = (!empty($_POST['payment_intent_id']))?$_POST['payment_intent_id']:'474';
                

                $stripe = new \Stripe\StripeClient($instance->private_key);
                $capture_payment_intent = $stripe->paymentIntents->capture(
                    $payment_intent_id,
                    []
                );

                // echo json_encode($paymentIntent);
                echo json_encode(['intent'=>$capture_payment_intent->id,'secret'=>$capture_payment_intent->client_secret]);
                exit();
            }
            catch(Exception $e) {
                echo json_encode(array('message' => $e->getMessage()));
                exit();
            }
        }
    }
    
    add_action('wp_ajax_get_client_secret', 'get_client_secret');
    add_action('wp_ajax_nopriv_get_client_secret', 'get_client_secret');

    if (!function_exists('get_client_secret')) {
        function get_client_secret(){
            $instance = new WC_Misha_Gateway();
            $OrderNumber = $_POST['OrderNumber'];
            $order = wc_get_order( $OrderNumber );
			$data = $order->get_data();
            
			$stripe = new \Stripe\StripeClient($instance->private_key);
			$customer = $stripe->customers->create([
				'name' => $data['billing']['first_name'].' '.$data['billing']['last_name'],
				'email'=>$data['billing']['email']
			]);
           
			$paymentIntent = $stripe->paymentIntents->create([
			'metadata' => ['order_id'=>$OrderNumber], 
			'customer'=>  $customer->id, 
			'amount' => ($data['total']*100),
			'currency' => $data['currency'],
			'capture_method'=>'manual',
			'payment_method_types'=>['card_present']
			]);

            
           $res =  $instance->add_terminal_order_meta($OrderNumber, 'payment_intent_id', $paymentIntent->id);
            
            echo $instance->getPaymentClientSecret($OrderNumber);
            exit();
        }
    }

    add_action('wp_ajax_capture_paymentIntent', 'capture_paymentIntent');
    add_action('wp_ajax_nopriv_capture_paymentIntent', 'capture_paymentIntent');

    if (!function_exists('capture_paymentIntent')) {
        function capture_paymentIntent(){
            $payment_intent_id = $_POST['payment_intent_id'];
            $instance = new WC_Misha_Gateway();
            echo $instance->capturePaymentIntent($payment_intent_id);
            exit();
        }
    }

    
    add_action('rest_api_init', 'custom_api_init');
    function custom_api_init() {
        register_rest_route('custom-api/v1', '/connection_token/', array(
            'methods' => 'POST',
            'callback' => 'terminal_get_token',
            'permission_callback' => '__return_true' // Allow public access
        ));

        register_rest_route('custom-api/v1', '/create_payment_intent/', array(
            'methods' => 'POST',
            'callback' => 'create_payment_intent',
            'permission_callback' => '__return_true' // Allow public access
        ));

        register_rest_route('custom-api/v1', '/capture_payment_intent/', array(
            'methods' => 'POST',
            'callback' => 'capture_payment_intent',
            'permission_callback' => '__return_true' // Allow public access
        ));
    }
}
?>

