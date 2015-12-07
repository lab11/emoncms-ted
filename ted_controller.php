<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function startsWith ($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function extract_value ($string, $start_key, $end_key) {
    $start_pos = strpos($string, $start_key);
    $end_pos = strpos($string, $end_key, $start_pos+strlen($start_key));
    $gateway = substr($string, $start_pos+strlen($start_key), $end_pos-$start_pos-strlen($start_key));
    return $gateway;
}

function check_device_key ($devicekey) {
    global $mysqli, $redis;

    include("Modules/device/device_model.php");
    $device = new Device($mysqli, $redis);
    $session = $device->devicekey_session($devicekey);
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

function extract_mtu ($xml) {

    $values = array();
    $remaining = $xml;

    while (True) {
        // Verify we still have an MTU to find
        $pos = strpos($remaining, '<MTU');
        if ($pos === False) {
            break;
        }

        $mtu = extract_value($remaining, '<MTU', '</MTU>');
        $mtuid = extract_value($mtu, 'ID=', ' ');
        // Get first cumulative
        $first = extract_value($mtu, '<cumulative', '/>');
        $watthfirst = (float) extract_value($first, 'watts="', '"');
        $timefirst = (float) extract_value($first, 'timestamp="', '"');
        // Get second cumulative
        $second = substr($mtu, strlen($first), strlen($mtu)-strlen($first));
        $watthsecond = (float) extract_value($first, 'watts="', '"');
        $timesecond = (float) extract_value($first, 'timestamp="', '"');

        // Calculate watts
        $watts = ($watthsecond - $watthfirst) / (($timesecond - $timefirst) / 3600);

        $values[$mtuid] = $watts;

        // Setup remaining for next iteration
        $remaining = substr($remaining, $pos+strlen($mtu), strlen($remaining)-$pos-strlen($mtu));
    }

    return $values;
}


function ted_controller() {
    global $mysqli, $redis, $user, $session, $route, $feed_settings;


    // Start by filtering by request path
    if ($route->action == 'post' && $route->format == 'text') {

        // Need to get the POST body
        $post = file_get_contents("php://input");

        // Look for an activation POST
        if (startsWith($post, '<ted5000Activation>')) {
            // Get the gateway and unique values
            $gateway = extract_value($post, '<Gateway>', '</Gateway>');
            $unique = extract_value($post, '<Unique>', '</Unique>');

            // Make sure this is permitted and that the user set up the device
            // in emoncms.
            $session = check_device_key($unique);

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
            '<PostURL>/ted/post.text</PostURL>' .
            "<AuthToken>$unique</AuthToken>" .
            '<PostRate>2</PostRate>' .
            '<HighPrec>T</HighPrec>' .
            '</ted5000ActivationResponse>';
        
        } else if (startsWith($post, '<ted5000 ')) {
            // Got data POST
            $nodeid = extract_value($post, 'GWID="', '"');
            $unique = extract_value($post, 'auth="', '"');

            // Setup variable we need to insert data
            // Need to get correct files so that we can make inputs
            require_once "Modules/feed/feed_model.php";
            $feed = new Feed($mysqli, $redis, $feed_settings);

            require_once "Modules/input/input_model.php";
            $input = new Input($mysqli, $redis, $feed);

            require_once "Modules/process/process_model.php";
            $process = new Process($mysqli, $input, $feed, $user->get_timezone($session['userid']));

            $session = check_device_key($unique);

            $userid = $session['userid'];
            $dbinputs = $input->get_inputs($session['userid']);

            // Make sure we can save this data.
            $validate_access = $input->validate_access($dbinputs, $nodeid);
            if (!$validate_access['success']) {
                header($_SERVER["SERVER_PROTOCOL"]." 401 Unauthorized");
                header('WWW-Authenticate: Bearer realm="Device KEY", error="invalid_nodeid", error_description="Invalid node"');
                print "Invalid node($nodeid) for that device key($unique)".$validate_access['message'].$dbinputs[$nodeid];
                exit();
            }

            // Get the MTU values from the POST data
            $values = extract_mtu($post);
            $time = time();

            // Actually insert data
            $tmp = array();
            foreach ($values as $name => $value) {
                $name = 'MTU' . $name;

                // Check if this is an existing field in this node or not
                if (!isset($dbinputs[$nodeid][$name])) {
                    // New field.
                    $inputid = $input->create_input($userid, $nodeid, $name);
                    $dbinputs[$nodeid][$name] = true;
                    $dbinputs[$nodeid][$name] = array('id'=>$inputid, 'processList'=>'');
                    $input->set_timevalue($dbinputs[$nodeid][$name]['id'], $time, $value);
                } else {
                    // Existing field, just insert
                    $input->set_timevalue($dbinputs[$nodeid][$name]['id'], $time, $value);

                    // If there are processes listening to this field, we need
                    // to pass the data to those as well
                    if ($dbinputs[$nodeid][$name]['processList']) {
                        $tmp[] = array('value'=>$value,
                                       'processList'=>$dbinputs[$nodeid][$name]['processList'],
                                       'opt'=>array('sourcetype'=>"WATTSUP",
                                                    'sourceid'=>$dbinputs[$nodeid][$name]['id']));
                    }
                }
            }

            // Actually insert all of the data to the process
            foreach ($tmp as $i) {
                $process->input($time, $i['value'], $i['processList'], $i['opt']);
            }

            $result = 'ok';

        } else {
            $result = 'Unknown';
        }

    }

    return array('content'=>$result);
}
