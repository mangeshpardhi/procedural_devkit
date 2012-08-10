<?php

hbm_create('Simple Namesilo', array(
    'description' => 'Namesilo registrar module for HostBill, using procedural DevKit',
    'version' => '1.0'
));

hbm_add_app_config('Api Key');
hbm_add_app_config('Test Mode', array('type' => 'checkbox'));


// TEST CONNECTION:
hbm_on_action('module.testconnection', function() {
            $out = Send_ToNamesilo('listDomains', '');
            if ($out)
                return true;
            return false;
});

// SYNCHRONIZE:
hbm_on_action('domain.synchronize',function($details) {
    $params = array(
            'domain' => $details['domain'],
        );

        $out = Send_ToNamesilo('getDomainInfo', $params);
        $return = array();

        if($out) {
            $out = json_decode(json_encode($out),true);

            $return['expires'] = $out['reply']['expires'];
            $return['status'] = $out['reply']['status'];

            $ns = array();
            foreach( $out['reply']['nameservers']['nameserver'] as $nameserver) {
                $ns[]=$nameserver;
            }
            $return['ns'] = $ns;
            $return['reglock'] = ($out['reply']['locked']=='Yes') ? true : false;
            $return['idprotection']= ($out['reply']['private'] == 'Yes') ? true : false;

        }
        return $return;
});

//REGISTER:
hbm_on_action('domain.register', function($details) {
        $domain = $details['domain'];
        $period = $details['period'];
        $registrant = $details['registrant'];

        $params = array(
            'domain' => $domain,
            'years' => $period,
            'auto_renew' => '0',
            'fn' => $registrant['firstname'],
            'ln' => $registrant['lastname'],
            'ad' => $registrant['address1'] . ' ' . $registrant['address2'],
            'cy' => $registrant['city'],
            'st' => $registrant['state'],
            'zp' => $registrant['postcode'],
            'ct' => $registrant['country'],
            'em' => $registrant['email'],
            'ph' => $registrant['phonenumber']
        );


        $nameservers = $details['nameservers'];
        if (!empty($nameservers)) {
            for ($i = 1; !empty($nameservers["ns{$i}"]) && $i < 14; $i++) {
                $params["ns{$i}"] = $nameservers["ns{$i}"];
            }
        }


        $out = Send_ToNamesilo('registerDomain', $params);

        if ($out) {
            return true;
        } else {
            hbm_error('There was a problem while registering the domain.');
            return false;
        }
});


//get extended registration attributes for tld
hbm_on_action('domain.attributes', function($details) {
    if ($details['tld'] == ".us") {
        $attributes = array();
        $attributes[] = array('name' => 'nexus_apppurpose',
            'description' => 'Application Purpose',
            'type' => 'select',
            'option' => array(array('value' => 'P1', 'title' => 'For Profit'),
                array('value' => 'P2', 'title' => 'Non-profit'),
                array('value' => 'P3', 'title' => 'Personal'),
                array('value' => 'P4', 'title' => 'Educational'),
                array('value' => 'P5', 'title' => 'Government')
            )
        );
        $attributes[] = array('description' => 'Nexus-Validator (2 letter code)',
            'name' => 'nexus_validator',
            'type' => 'input',
            'option' => false);
        return $attributes;
    }
});


/**
 * This is just helper function to connect with namesilo
 * @param <type> $action
 * @param <type> $params
 * @return DOMXpath
 */
function Send_ToNamesilo($action, $params) {
    if (hbm_get_config_option('Test Mode'))
        $url = 'http://sandbox.namesilo.com/api/' . $action;
    else
        $url = 'https://www.namesilo.com/api/' . $action;

    $url .= '?key=' . hbm_get_config_option('Api Key');


    if (is_array($params)) {
        foreach ($params as $key => $val) {
            $keyen = urlencode($key);
            $valen = urlencode($val);
            $url .= "&{$keyen}={$valen}";
        }
    }


    $curl = curl_init();                                // we are using cURL library here
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 90);
    $out = curl_exec($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);

    if ($out === false) {
        hbm_error(ucwords(curl_error($curl)));
        return false;
    }


    $doc = new DOMDocument();
    $doc->loadXML($out);
    $xpath = new DOMXpath($doc);
    $tmp = $xpath->query('/namesilo/reply/code');
    $code = $tmp->item(0)->nodeValue;

    $errtab = array(300, 301, 302);

    if ($code == 254) {
        hbm_error('You write incorrect NameServer');

        return false;
    }
    if (!in_array($code, $errtab)) {
        hbm_error('An error occured: namesilo returned error code:' . $code );

        return false;
    }

    return $xpath;
}