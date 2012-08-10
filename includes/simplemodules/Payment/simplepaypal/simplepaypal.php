<?php


/**
 * This is example of working Payment module for HostBill, using PayPal standard
 * Built using HostBill procedural devkit
 *
 * Module reacts on payment.displayform action to draw form to submit PayPal payment
 * It also creates route to callback in client interface using hbm_client_route and logs paypal return there
 */

 hbm_create('Simple PayPal',array(
            'description'=>'PayPal Module for HostBill, using procedural DevKit',
            'version'=>'1.0',
            'currencies'=>array('USD','EUR','GBP') //list of supported currencies
        ));

   hbm_add_config_option('PayPal Email',array(
        'type'=>'input',
        'default'=>'example@paypal.com',
        'description'=>'Please provide your paypal email address',
    ));

    hbm_add_config_option('Test Mode',array(
        'type'=>'check',
        'default'=>'0',
        'description'=>'Tick if you wish to use test mode'
    ));

    

    hbm_on_action('payment.displayform', function($details){

        $hostbill_details = hbm_get_hostbill_details();
        $paypal_url = 'https://www.paypal.com/cgi-bin/webscr';
        if(hbm_get_config_option('Test Mode')=='1') {
            $paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
        }
        $email=hbm_get_config_option('PayPal Email');


        //This will create url to callback route created below
        $callback_url = hbm_client_url('callback');
        

        $form =  "<form action='{$paypal_url}' method='post' name='payform'>";
        $form .=  '<input type="hidden" value="utf-8" name="charset">
            <input type="hidden" value="'.$hostbill_details['BusinessName'].'" name="bn">
            <input type="hidden" value="2" name="rm">
            <input type="hidden" value="'.$email.'" name="business">
            <input type="hidden" value="'.$callback_url.'" name="notify_url">
            <input type="hidden" value="'.$details['invoice']['currency'].'" name="currency_code">
            <input type="hidden" value="'.$details['invoice']['amount'].'" name="amount">
            <input type="hidden" value="'.$details['invoice']['id'].'" name="item_number">
            <input type="hidden" value="'.$details['invoice']['description'].'" name="item_name">
            <input type="hidden" value="'.$details['client']['country'].'" name="country">
            <input type="hidden" value="'.$details['client']['firstname'].'" name="first_name">
            <input type="hidden" value="'.$details['client']['lastname'].'" name="last_name">
            <input type="submit" value="Pay Now" >
            <input type="hidden" value="_xclick" name="cmd">';

        $form .="</form>";
        return $form;
    });

    //create route that paypal can send notifications to
    hbm_client_route('callback',function($params) {
         $req = 'cmd=_notify-validate';

        foreach ($_POST as $key => $value) {
            $value = urlencode(stripslashes($value));
            $req .= "&$key=$value";
        }

        $header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= "Content-Length: " . strlen($req) . "\r\n\r\n";

        if (hbm_get_config_option('Test Mode') == '1')
            $fp = fsockopen('www.sandbox.paypal.com', 80, $errno, $errstr, 30);
        else
            $fp = fsockopen('www.paypal.com', 80, $errno, $errstr, 30);

        $results = 'Failure';
        switch ($_POST['payment_status']) {
            case 'Completed': $results = 'Success'; break;
            case 'Pending': $results = 'Pending'; break;
        }
        hbm_log_callback($_POST,$results);

        if (!$fp) {
            // HTTP ERROR
            hbm_log_callback( array('Warning' => 'fsockopen returned a following error when trying to connect to PayPal', 'Error nr' => $errno, 'Error' => $errstr), 'Failure');
        } else {
            fputs($fp, $header . $req);
            while (!feof($fp)) {
                $res = fgets($fp, 1024);
                if (strcmp($res, "VERIFIED") == 0) {
                    if ($_POST['payment_status'] == 'Completed') {
                             hbm_add_transaction( $_POST['item_number'],$_POST['mc_gross'],array(
                                'description' => $_POST['item_name'],
                                'fee' => $_POST['mc_fee'],
                                'transaction_id' => $_POST['txn_id']
                            ));
                    }
                } 
            }
            fclose($fp);
        }
    });

