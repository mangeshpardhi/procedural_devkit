<?php

/**
 * This is example of working Merchant module for HostBIll, based on NaviGate gateway.
 * Built using HostBill procedural devkit
 *
 * Module racts on payment.capture action to capture credit card payment
 */

hbm_create('Simple Navigate', array(
    'description' => 'NaviGate - Internet Merchant Processing by MerchantPlus, using procedural DevKit',
    'version' => '1.0',
     'currencies'=>array('USD') //list of supported currencies
));

hbm_add_config_option('NaviGate login');
hbm_add_config_option('Transaction key');
hbm_add_config_option('Test Mode', array(
    'type' => 'check',
    'default' => '0',
    'description' => 'Tick if you wish to use test mode'
));

//Capture payment
hbm_on_action('payment.capture', function($data) {
        $options = array();
        $options['x_login'] = hbm_get_config_option('NaviGate login');
        $options['x_tran_key'] = hbm_get_config_option('Transaction key');
        $options['x_test_request'] = hbm_get_config_option('Test Mode');
        $options['x_type'] = 'AUTH_CAPTURE';
        $options['x_version'] = '3.1';

        /* CREDIT CARD INFORMATION */
        $options['x_method'] = 'CC';
        $options['x_card_num'] = $data['creditcard']['cardnum'];
        $options['x_exp_date'] = $data['creditcard']['expdate'];
        $options['x_card_code'] = $data['creditcard']['cvv'];

        /* ORDER INFORMATION */
        $options['x_invoice_num'] = $d['invoice']['id'];
        $options['x_description'] = $d['invoice']['description'];
        $options['x_amount'] = $data['invoice']['amount'];

        /* CUSTOMER INFORMATION */
        $options['x_first_name'] = $data['client']['firstname'];
        $options['x_last_name'] = $data['client']['lastname'];
        $options['x_address'] = $data['client']['address1'];
        $options['x_city'] = $data['client']['city'];
        $options['x_state'] = $data['client']['state'];
        $options['x_zip'] = $data['client']['postcode'];
        $options['x_country'] = $data['client']['country'];
        $options['x_phone'] = $data['client']['phonenumber'];
        $options['x_email'] = $data['client']['email'];
        $options['x_cust_id'] = $data['client']['id'];

        $response = postRequest($options); //call helper, post data with curl

        if($response['code']==1) {
            hbm_log_callback($response, 'Success');

            //add payment
            hbm_add_transaction($response['invoice_id'], $response['amount']);
            return true;
        } else {
            hbm_log_callback($response, 'Failure');
            return false;
        }
});

/**
 * Helper function - wraps curl
 * @param arrya $data Data to post to gateway
 * @return array
 */
function postRequest($data) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://gateway.merchantplus.com/cgi-bin/PAWebClient.cgi');
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    $response = curl_exec($ch);
    curl_close($ch);

    $response = $this->postRequest($response);
    $array = explode(',', $response);

    return array('response' => $response, 'code' => $array[0], 'invoice_id' => $array[7], 'amount' => $array[9]);
}