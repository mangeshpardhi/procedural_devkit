<?php

 hbm_create('Simple cPanel',array(
            'description'=>'cPanel Module for HostBill, using procedural DevKit',
            'version'=>'1.0'
));

 //Configure server fields, visible under settings->apps
 hbm_add_app_config('ip');
 hbm_add_app_config('username');
 hbm_add_app_config('password',array(
        'type'=>'password'
     ));

 hbm_add_app_config('Custom field',array(
        'type'=>'password'
     ));

 //Configure account fields, visible under Accounts
 hbm_add_account_config('username'); //client username
 hbm_add_account_config('password'); //client password
 hbm_add_account_config('domain'); //client domain


 //Configure product fields, visible under Products
 hbm_add_product_config('package',array(
     'type'=>'select',
     'description'=>'Package to create account with',
     'default'=>function() {
         //get packages from cPanel server
         $packages = callCPanelApi('listpkgs');
         if(!isset($packages->package))
             return false;
         $return = array();
         foreach($packages->package as $pk) {
            $return[]=(string) $pk->name;
         }
         return $return;
    }
 ));


//test connection with cPanel by asking for its version
 hbm_on_action('module.testconnection',function() {
   $version = callCPanelApi('version');
   if(!isset($version->version))
           return false;

   return true;
 });

 // handle account creation
hbm_on_action('account.create',function($details) {
    $client_email = $details['client']['email'];
    
     $domain = hbm_get_account_config('domain');
     $username = hbm_get_account_config('username');
     $password = hbm_get_account_config('password');
     $package = hbm_get_product_config('package');
     //http://docs.cpanel.net/twiki/bin/view/SoftwareDevelopmentKit/CreateAccount

     $result=callCPanelApi('createacct',array(
         'username'=>$username,
         'domain'=>$domain,
         'password'=>$password,
         'plan'=>$package
     ));
     if((integer) $result->result->status == 1)
         return true;
     return false;
});

//add function available from clientarea
hbm_add_client_function('Test Function', function($request,$template){
    echo "Hello. I'm function of SimpleCPanel module available in client area.<br/><br/>
        Administrator can enable/disable client functions per package basis under Product configuration->Client functions";
});


//this is helper function, wraps curl
function callCPanelApi($function,$params=false) {

     $username = hbm_get_app_config('username');
     $password = hbm_get_app_config('password');
     $server_ip = hbm_get_app_config('ip') ;
     $login = urlencode($username) . ':' . urlencode($password) ;

        $url = 'https://'.$login.  '@' . $server_ip.':2086/xml-api/' . $function;


        //since we dont have cpanel:
        return true;
        
        if (is_array($params)) {
            $url .= '?';
            foreach ($params as $key => $value) {
                $key = urlencode($key);
                $value = urlencode($value);
                $url .= "{$key}={$value}&";
            }
        }
        $ch = curl_init();
        $chOptions = array(
            CURLOPT_URL => rtrim($url, '&'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        );
        
        curl_setopt_array($ch, $chOptions);
        $out = curl_exec($ch);
        if ($out === false) {
            hbm_error('Server issued empty response');
        }
        curl_close($ch);


         if (!$out)
            return false;

        @$a = simplexml_load_string($out);
        if ($a === FALSE) {
            return false;
        } else {
            $xml = new SimpleXMLElement($out);
            return $xml;
        }

 }

