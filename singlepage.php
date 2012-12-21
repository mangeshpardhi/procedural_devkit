<?php

require('includes/hostbill.php');

$client = hbm_logged_client(); //get information about logged in client

if($client) { //client is currently logged in

    $client_id = (int)$client['id'];
    
    //you can access HostBill database directly from your page:
    $result= mysql_query('SELECT id FROM hb_invoices WHERE client_id='.$client_id.' LIMIT 1');
    $invoice = mysql_fetch_assoc($result);

    hbm_render_page(
            'mypage.tpl',  //mypage.tpl should be located in /templates/[YOURDIR]/mypage.tpl
            array('invoice'=>$invoice['id']),  //you can access {$invoice} in your mypage.tpl
            _t('clientarea')  //you can translate your pages! http://dev.hostbillapp.com/additional-resources/using-languagestranslations-in-your-modulescustom-code/
    );

} else { //client is not logged in
    
    hbm_render_page(function(){
        //you dont need to use smarty templates - you can create simple closure like this!
        echo '<h1>Sorry, you need to be logged in!</h1>';
    });
}
