<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function startsWith ($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function extract_value ($string, $start_key, $end_key) {
    $start_pos = strpos($post, $start_key);
    $end_pos = strpos($post, $end_key, $start_pos+strlen($start_key));
    $gateway = substr($post, $start_pos+strlen($start_key), $end_pos-$start_pos-strlen($start_key));
    return $gateway;
}

function check_device_key ($dkey) {
    global $mysqli, $redis;

    include("Modules/device/device_model.php");
    $device = new Device($mysqli, $redis);
    $session = $device->devicekey_session($dkey);
    if (empty($session)) {
        header($_SERVER["SERVER_PROTOCOL"]." 401 Unauthorized");
        header('WWW-Authenticate: Bearer realm="Device KEY", error="invalid_devicekey", error_description="Invalid device key"');
        print "Invalid device key";
        $log = new EmonLogger(__FILE__);
        $log->error("Invalid device key '" . $devicekey. "'");
        exit();
    }
    return $session;
}


function ted_controller() {
    global $mysqli, $redis, $user, $session, $route, $feed_settings;

    // Need to get the POST body
    $post = $HTTP_RAW_POST_DATA;

    // Look for an activation POST
    if (startsWith($post, '<ted5000Activation>')) {
        // Get the gateway and unique values
        $gateway = extract_value($post, '<Gateway>', '</Gateway>');
        $unique = extract_value($post, '<Unique>', '</Unique>');

        // Make sure this is permitted and that the user set up the device
        // in emoncms.
        $session = check_device_key($unique)

        // Need to get values for the response
        if (isset($_SERVER['HTTP_X_FORWARDED_SERVER'])) {
            $server = server('HTTP_X_FORWARDED_SERVER');
        } else {
            $server = server('HTTP_HOST');
        }
        $url = server('SCRIPT_NAME');

        $result = '<ted5000ActivationResponse>' .
        "<PostServer>$server</PostServer>" .
        '<UseSSL>F</UseSSL>' .
        '<PostPort>80</PostPort>' .
        "<PostURL>$url</PostURL>" .
        "<AuthToken>$unique</AuthToken>" .
        '<PostRate>1</PostRate>' .
        '<HighPrec>T</HighPrec>' .
        '</ted5000ActivationResponse>';
    
    } else if (startsWith($post, '<ted5000 ')) {
        // Got data POST

        $result = 'data';

    } else {
        $result = 'Unknown';
    }



    // // First up, a little hack.
    // // We need to include an API key with our POST data from the Watts Up?,
    // // but the stupid thing limits how long our POST location string can be.
    // // SO, we put the API key in the user agent string, cause why not.
    // $apikey = $_SERVER["HTTP_USER_AGENT"];

    // $session = $user->apikey_session($apikey);
    // if (empty($session)) {
    //     header($_SERVER["SERVER_PROTOCOL"]." 401 Unauthorized");
    //     header('WWW-Authenticate: Bearer realm="API KEY", error="invalid_apikey", error_description="Invalid API key"');
    //     print "Invalid API key";
    //     $log = new EmonLogger(__FILE__);
    //     $log->error("Invalid API key '" . $apikey. "'");
    //     exit();
    // }

    // // There are no actions in the input module that can be performed with less than write privileges
    // if (!$session['write']) return array('content'=>false);

    // $result = false;

    // // Need to get correct files so that we can make inputs
    // require_once "Modules/feed/feed_model.php";
    // $feed = new Feed($mysqli, $redis, $feed_settings);

    // require_once "Modules/input/input_model.php";
    // $input = new Input($mysqli, $redis, $feed);

    // require_once "Modules/process/process_model.php";
    // $process = new Process($mysqli, $input, $feed, $user->get_timezone($session['userid']));

    // // Process /wattsup/post.text messages from Watts Up? .net
    // if ($route->action == 'post' && $route->format == 'text') {
    //     // This looks like a correctly configured Watts Up? .net POST

    //     $valid = true;
    //     $error = '';
    //     $userid = $session['userid'];
    //     $dbinputs = $input->get_inputs($userid);

    //     // id is set to the Watts Up? device ID
    //     $nodeid = preg_replace('/[^\p{N}\p{L}_\s-.]/u', '', post('id'));

    //     // Make sure we can do this. Copied from input_controller.php
    //     $validate_access = $input->validate_access($dbinputs, $nodeid);
    //     if (!$validate_access['success']) {
    //         $valid = false;
    //         $error = $validate_access['message'];
    //     } else {
    //         // Insert this record into the emoncms format
    //         $time = time();

    //         // Array to store the relevant fields in
    //         $data = array();

    //         $watts    = post('w');
    //         $volts    = post('v');
    //         $amps     = post('a');
    //         $watth    = post('wh');
    //         $maxwatts = post('wmx');
    //         $maxvolts = post('vmx');
    //         $maxamps  = post('amx');
    //         $minwatts = post('wmi');
    //         $minvolts = post('vmi');
    //         $minamps  = post('ami');
    //         $pf       = post('pf');
    //         $pcy      = post('pcy');
    //         $freq     = post('frq');
    //         $voltamps = post('va');

    //         # Only include fields we actually got
    //         if (is_numeric($watts))    $data['watts']        = $watts / 10;
    //         if (is_numeric($volts))    $data['volts']        = $volts / 10;
    //         if (is_numeric($amps))     $data['amps']         = $amps / 1000;
    //         if (is_numeric($watth))    $data['watt_hours']   = $watth / 1000;
    //         if (is_numeric($maxwatts)) $data['max_watts']    = $maxwatts / 10;
    //         if (is_numeric($maxvolts)) $data['max_volts']    = $maxvolts / 10;
    //         if (is_numeric($maxamps))  $data['max_amps']     = $maxamps / 1000;
    //         if (is_numeric($minwatts)) $data['min_watts']    = $minwatts / 10;
    //         if (is_numeric($minvolts)) $data['min_volts']    = $minvolts / 10;
    //         if (is_numeric($minamps))  $data['min_amps']     = $minamps / 1000;
    //         if (is_numeric($pf))       $data['power_factor'] = $pf;
    //         if (is_numeric($pcy))      $data['power_cycle']  = $pcy;
    //         if (is_numeric($freq))     $data['freq']         = $freq / 10;
    //         if (is_numeric($voltamps)) $data['volt_amps']    = $voltamps / 10;

    //         // Iterate all new data items to insert
    //         $tmp = array();
    //         foreach ($data as $name => $value) {
    //             // Check if this is an existing field in this node or not
    //             if (!isset($dbinputs[$nodeid][$name])) {
    //                 // New field.
    //                 $inputid = $input->create_input($userid, $nodeid, $name);
    //                 $dbinputs[$nodeid][$name] = true;
    //                 $dbinputs[$nodeid][$name] = array('id'=>$inputid, 'processList'=>'');
    //                 $input->set_timevalue($dbinputs[$nodeid][$name]['id'], $time, $value);
    //             } else {
    //                 // Existing field, just insert
    //                 $input->set_timevalue($dbinputs[$nodeid][$name]['id'], $time, $value);

    //                 // If there are processes listening to this field, we need
    //                 // to pass the data to those as well
    //                 if ($dbinputs[$nodeid][$name]['processList']) {
    //                     $tmp[] = array('value'=>$value,
    //                                    'processList'=>$dbinputs[$nodeid][$name]['processList'],
    //                                    'opt'=>array('sourcetype'=>"WATTSUP",
    //                                                 'sourceid'=>$dbinputs[$nodeid][$name]['id']));
    //                 }
    //             }
    //         }

    //         // Actually insert all of the data to the process
    //         foreach ($tmp as $i) {
    //             $process->input($time, $i['value'], $i['processList'], $i['opt']);
    //         }
    //     }

    //     if ($valid) {
    //         $result = 'ok';
    //     } else {
    //         $result = "Error: $error\n";
    //     }
    // }


    return array('content'=>$result);
}
