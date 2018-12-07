protected function generate_payment_request_recurring($order)
    {
        // print_r($order);
        $post_data = array();

        $post_data['use_existing_customer'] = true;

        if (isset($_POST['qualpay_card_id'])) {
            $post_data['card_id'] = wc_clean($_POST['qualpay_card_id']);
        } else {
            $post_data['card_number'] = str_replace(' ', '', wc_clean($_POST['qualpay-card-number']));
            $exp_date = wc_clean($_POST['qualpay-card-expiry']);
            $exp_date = substr($exp_date, 0, 2) . substr($exp_date, 5, 2);
            $post_data['exp_date'] = $exp_date;
            $post_data['cvv2'] = wc_clean($_POST['qualpay-card-cvc']);
        }
        $billing_first_name = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_first_name : $order->get_billing_first_name();
        $billing_last_name = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_last_name : $order->get_billing_last_name();
        $post_data['cardholder_name'] = $billing_first_name . ' ' . $billing_last_name;
        $post_data['amt_tran'] = $order->get_total('edit');
        $post_data['purchase_id'] = $order->get_order_number();
        $post_data['tran_currency'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->get_order_currency() : $order->get_currency();

        $post_data['customer_email'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_email : $order->get_billing_email();
        $post_data['developer_id'] = 'Qualpay';
        $post_data['email_receipt'] = false;
        $post_data['tokenize'] = false;

        // TODO add customer array
        $customer = array();
        if (Qualpay_Cart::recurring_in_cart()) {

            $post_data['cart_amt_other'] = 0;
            // Recurring can be only 1 in cart. Get the first cart item then.
            foreach (WC()->cart->cart_contents as $cart_item) {
                if (Qualpay_Cart::is_product_recurring($cart_item['product_id'])) {
                    $post_data['product_id'] = $cart_item['product_id'];
                    $product_id = isset($post_data['product_id']) ? absint($post_data['product_id']) : 0;

                    if (!$product_id) {
                        return new WP_Error('qualpay_error', __('No Product Selected for a Subscription', 'qualpay'));
                    }

                    if (!isset($args['cart_amt_other'])) {
                        $args['cart_amt_other'] = 0;
                    }

                    $plan_object = get_post_meta($product_id, '_qualpay_plan_data', true);
                    if ($plan_object) {
                        $post_data['subscription_on_plan'] = true;
                        $post_data['interval'] = $plan_object->interval;
                        $post_data['plan_id'] = $plan_object->plan_id;
                        $post_data['plan_code'] = $plan_object->plan_code;
                        $post_data['plan_desc'] = $plan_object->plan_desc;
                        $post_data['amt_setup'] = floatval($plan_object->amt_setup) + $args['cart_amt_other'];
                        $post_data['amt_tran'] = $plan_object->amt_tran;
                        $post_data['plan_frequency'] = $plan_object->plan_frequency;
                    } else {
                        $post_data['plan_frequency'] = get_post_meta($product_id, '_qualpay_frequency', true);
                        $post_data['interval'] = get_post_meta($product_id, '_qualpay_interval', true);
                        $bill_unlimited = get_post_meta($product_id, '_qualpay_bill_until_cancelled', true);
                        if ('yes' === $bill_unlimited) {
                            $post_data['plan_duration'] = '-1';
                        } else {
                            $post_data['plan_duration'] = get_post_meta($product_id, '_qualpay_duration', true);
                        }
                        $setup_fee = (float) get_post_meta($product_id, '_qualpay_setup_fee', true);
                        if (!$setup_fee) {
                            $setup_fee = 0;
                        }
                        $post_data['amt_setup'] = $setup_fee + $args['cart_amt_other'];
                        $post_data['amt_tran'] = get_post_meta($product_id, '_qualpay_amount', true);
                        $post_data['subscription_on_plan'] = false;
                    }
                } else {
                    $post_data['cart_amt_other'] += $cart_item['data']->get_price();
                }
            }

            $current_user = wp_get_current_user();
            $customer_id = get_user_meta($current_user->ID, '_qualpay_customer_id', true);
            if (!$customer_id) {
                $customer_billing = $order->get_address();
                // Not yet on Qualpay, let's create it.
                $customer_args = array();
                $customer_args['auto_generate_customer_id'] = true;
                $customer_args['customer_first_name'] = $billing_first_name;
                $customer_args['customer_last_name'] = $billing_last_name;
                $customer_args['customer_email'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_email : $order->get_billing_email();
                //   $customer_args['customer_phone'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_phone : $order->get_billing_phone();
                //    $customer_args['comments'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->order_comments : $order->get_order_comments();

                $customer_args['billing_cards'] = array();
                $customer_args['billing_cards'][] = array(
                    'card_id' => isset($post_data['card_id']) ? $post_data['card_id'] : '',
                    'card_number' => isset($post_data['card_number']) ? $post_data['card_number'] : '',
                    'exp_date' => isset($exp_date) ? $exp_date : '',
                    'cvv2' => isset($post_data['cvv2']) ? $post_data['cvv2'] : '',
                    'billing_first_name' => $customer_billing['first_name'],
                    'billing_last_name' => $customer_billing['last_name'],
                    'billing_firm_name' => $customer_billing['company'],
                    'billing_add1' => $customer_billing['address_1'],
                    'billing_city' => $customer_billing['city'],
                    'billing_state' => $customer_billing['state'],
                    'billing_zip' => $customer_billing['postcode'],
                    'billing_country' => $customer_billing['country'],
                    'primary' => true,
                );

                /* $new_customer = Qualpay_API::create_customer($customer_args);
            if (is_wp_error($new_customer)) {
            $order->add_order_note($new_customer->get_error_message());
            throw new Exception($new_customer->get_error_message());
            }

            add_user_meta($current_user->ID, '_qualpay_customer_id', $customer_args['customer_id']);
            $customer_id = $customer_args['customer_id']; */

            }
           // $post_data['customer_id'] = $customer_id;
            $post_data['date_start'] = date('Y-m-d', current_time('timestamp') + DAY_IN_SECONDS);
            $post_data['use_existing_customer'] = true;
            $post_data['customer'] = $customer_args;


            $request = $post_data;
            $printting = json_encode( $request );

            print_r($printting);
            exit;
            // checking new api call for subscription and customers

            $endpoint = untrailingslashit( Qualpay_API::get_endpoint( 'platform/subscription' ) );

            $response = wp_safe_remote_post(
                $endpoint,
                array(
                    'headers'       => array(
                        'Authorization'  => 'Basic ' . base64_encode( '04c1245a7a5711e889e70ab59617ae12' . ':' ),
                        'Content-type'   => 'application/json',
                    ),
                    'body'       => json_encode( $request ),
                    'timeout'    => 70,
                    'user-agent' => self::get_user_agent(),
                )
            );

            return self::parse_response( $response );
        }

        // TODO add shipping array
        $shipping_address = array();

        // TODO add line_items

        /**
         * Filter the return value of the WC_Payment_Gateway_CC::generate_payment_request_recurring
         *
         * @since 3.1.0
         * @param array $post_data
         * @param WC_Order $order
         * @param object $source
         */
        print_r($post_data);
        exit;
        //return apply_filters('wc_qualpay_generate_payment_request_recurring', $post_data, $order);
    }
