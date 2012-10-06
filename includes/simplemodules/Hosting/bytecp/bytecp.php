<?php
/**
 * ByteCP provisioning module for HostBill
 * Built using simple/procedural devkit : http://dev.hostbillapp.com/procedural-dev-kit/provisioning-modules/
 */

/**
 * Declare module
 */
hbm_create("ByteCP", array(
    'version' => '0.0.3',
    'description' => 'Provisioning module for ByteCP control panel - http://bytecp.com/'
));

/**
 * Let HostBill know what access details to API module need to collect
 */
hbm_add_app_config('ip');
hbm_add_app_config('API Key');


/**
 * Add fields that will define product
 */
hbm_add_product_config('Plan', array('type'=>'input','description'=>'Provide plan name from ByteCP, client account will be created with this plan'));


/**
 * Add fields into account configuration
 */
hbm_add_account_config('username'); //client username
hbm_add_account_config('password'); //client password
hbm_add_account_config('domain'); //client domain
hbm_add_account_config('Security Code'); //client security code, if none set use random


/**
 * Check whether we can connect to ByteCP with credentials provided
 */
hbm_on_action('module.testconnection', function() {
   $result = byte_senddata('test');
   if($result['info']=='ok')
       return true;

   if(isset($result['error'])) {
       hbm_error($result['error']);
   }
   return false;
});

/**
 * Create new hosting account
 */
hbm_on_action('account.create',function($details) {

     $client_email = $details['client']['email'];
     $domain = hbm_get_account_config('domain');
     $username = hbm_get_account_config('username');
     $password = hbm_get_account_config('password');
     $seccode = hbm_get_account_config('Security Code');
     $package = hbm_get_product_config('Plan');
     //make sure username is 8 char long
     if(strlen($username)!=8) {
         $mis1=$username .Utilities::generatePassword(8, true, false,true);
         $username = substr(preg_replace('/[^a-z0-9]/','', $mis1),0,8);
         hbm_set_account_config('username', $username);
     }
     if(!$seccode) {
         $seccode=Utilities::generatePassword(12, true, true, true);
         hbm_set_account_config('Security Code', $seccode);
     }

     $result=byte_senddata('createaccount',array(
         'username'=>$username,
         'domain'=>$domain,
         'password'=>$password,
         'seccode'=>$seccode,
         'planname'=>$package,
         'email'=> $client_email
     ));

     if($result['taskid']) { //assume it worked.
       return true;
     } else {
         return false;
     }
     
});


/**
 * Suspend hosting account
 */
hbm_on_action('account.suspend',function($details) {
     $username = hbm_get_account_config('username');

     $result=byte_senddata('suspendaccount',array(
         'username'=>$username
     ));

      if($result['taskid']) { //assume it worked.
       return true;
     } else {
         return false;
     }
});

/**
 * UnSuspend hosting account
 */
hbm_on_action('account.unsuspend',function($details) {
     $username = hbm_get_account_config('username');

     $result=byte_senddata('unsuspendaccount',array(
         'username'=>$username
     ));

      if($result['taskid']) { //assume it worked.
       return true;
     } else {
         return false;
     }
});



/**
 * Terminate hosting account
 */
hbm_on_action('account.terminate',function($details) {
     $username = hbm_get_account_config('username');

     $result=byte_senddata('removeaccount',array(
         'username'=>$username
     ));

     if($result['taskid']) {
       return true;
     } else {
         return false;
     }
});

/**
 * Add login to byteCp function into client interface.
 * This function can be turned on/off by admin in product details screen
 */
 hbm_add_client_function('Login to ByteCP', function($request) {
            //get info about logged in client
            $client = hbm_logged_client();

            $ip = hbm_get_app_config('ip');
            $username = hbm_get_account_config('username');
            $password = hbm_get_account_config('password');
            $seccode = hbm_get_account_config('Security Code');
            $domain = hbm_get_account_config('domain');
            $email=$client['email'];

            echo "
            <h3>You can login into ByteCP using credentails below:</h3>
            <table class=\"table table-bordered table-striped\">

            <thead>
              
            <tbody>
            <tr>
                <td>
                 <b>Email</b>
                </td>
                <td>$email</td>
              </tr>
              <tr>
                <td>
                 <b>Username</b>
                </td>
                <td>$username</td>
              </tr>
            <tr>
                <td>
                  <b>Security Code</b>
                </td>
                <td>$seccode</td>
              </tr>
              <tr>
                <td>
                 <b>Password</b>
                </td>
                <td>$password</td>
              </tr>
              
              <tr>
                <td>
                  <b>ByteCP</b>
                </td>
                <td><a href='https://{$ip}:4443/' target='_blank' >https://{$ip}:4443/</a></td>
              </tr>
            </tbody>
          </table>";
});

/**
 * Helper function to send data into ByteCp
 * @param string $action API function to call
 * @param array $data Associative data to post to ByteCP
 * @return array|false
 */
function byte_senddata($action, $data=array()) {


    $ip = hbm_get_app_config('ip');
    $key = hbm_get_app_config('API Key');

    $ch = curl_init("https://{$ip}:5443/api/v1/{$action}/key={$key}");
    if (!empty($data)) {
        $data_string = json_encode($data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data_string))
    );
    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if(!$result) {
        if(curl_errno($ch)) {
            hbm_error(curl_error($ch));
        }
        return false;
    }
    if($info['http_code']!='200') {
        hbm_error('Bad request, got HTTP code '.$info['http_code']);
        return false;
    }

    $res=json_decode($result,true);
    if(isset($res['error']) && count($res)==1) {
        hbm_error($res['error']);
        return false;
    }
    return $res;
}