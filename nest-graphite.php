#! /usr/bin/php
<?php

require_once('nest.class.php');

// Your Nest username and password.
$username = 'ojilles@gmail.com';
$password = $_ENV['NESTPWD'];

// The timezone you're in.
// See http://php.net/manual/en/timezones.php for the possible values.
date_default_timezone_set('Europe/Amsterdam');

// Here's how to use this class:

$nest = new Nest($username, $password);

$devices_serials = $nest->getDevices();

$infos = $nest->getDeviceInfo($devices_serials[0]);
if ($_ENV['NESTDEBUG'])
	jlog($infos);

echo "Current temperature:\n";
printf("%.02f degrees %s\n", $infos->current_state->temperature, $infos->scale);
echo "----------\n\n";

$secs_since_last_connection = time() - strtotime($infos->network->last_connection);
if ($secs_since_last_connection < 0) { $secs_since_last_connection = 0; }

// Grab the interesting stuff together...
//$s = $infos->serial_number;
$s = strtolower(strtr($infos->where, " ", "_"));
$data[] = sprintf("nest.%s.current.temperature_%s %.02f %u".PHP_EOL, $s, $infos->scale, $infos->current_state->temperature, time());
$data[] = sprintf("nest.%s.current.battery_level %.03f %u".PHP_EOL, $s, $infos->current_state->battery_level, time());
$data[] = sprintf("nest.%s.current.humidity %u %u".PHP_EOL, $s, $infos->current_state->humidity, time());
$data[] = sprintf("nest.%s.current.seconds_since_last_connection %u %u".PHP_EOL, $s, $secs_since_last_connection, time());
$data[] = sprintf("nest.%s.target.temperature %.02f %u".PHP_EOL, $s, $infos->target->temperature, time());
$data[] = sprintf("nest.%s.target.time_to_target %u %u".PHP_EOL, $s, $infos->target->time_to_target ? $infos->target->time_to_target-time() : 0, time());
$data[] = sprintf("nest.%s.current.heat %u %u".PHP_EOL, $s, $infos->current_state->heat ? 1 : 0, time());

//var_dump($data);

// Send it off to graphite
try {
    $fp = fsockopen("tcp://127.0.0.1", 2003, $errno, $errstr);

    if (!empty($errno)) echo $errno;
    if (!empty($errstr)) echo $errstr;

    $message = "some.custom.metric.php 1 ".time().PHP_EOL;
    foreach($data as $d) {
       $bytes = fwrite($fp, $d);
       echo $d;
    }
} catch (Exception $e) {
    echo "\nNetwork error: ".$e->getMessage();
}

/*
{
  "current_state": {
    "mode": "heat",
    "temperature": 17.99,
    "humidity": 53,
    "ac": false,
    "heat": false,
    "alt_heat": false,
    "fan": false,
    "auto_away": 0,
    "manual_away": false,
    "leaf": true,
    "battery_level": 3.906
  },
  "target": {
    "mode": "heat",
    "temperature": 17,
    "time_to_target": 0
  },
  "serial_number": "02AA01AC371406UE",
  "scale": "C",
  "location": "a766a620-69ef-11e4-85c8-22000b0b9342",
  "network": {
    "online": true,
    "last_connection": "2014-12-22 16:27:03",
    "wan_ip": "83.163.237.78",
    "local_ip": "192.168.178.23",
    "mac_address": "18b43012f1a0"
  },
  "name": "Not Set",
  "where": "Living Room"
}
*/









/* Helper functions */

function json_format($json) { 
    $tab = "  "; 
    $new_json = ""; 
    $indent_level = 0; 
    $in_string = false; 

    $json_obj = json_decode($json); 

    if($json_obj === false) 
        return false; 

    $json = json_encode($json_obj); 
    $len = strlen($json); 

    for($c = 0; $c < $len; $c++) 
    { 
        $char = $json[$c]; 
        switch($char) 
        { 
            case '{': 
            case '[': 
                if(!$in_string) 
                { 
                    $new_json .= $char . "\n" . str_repeat($tab, $indent_level+1); 
                    $indent_level++; 
                } 
                else 
                { 
                    $new_json .= $char; 
                } 
                break; 
            case '}': 
            case ']': 
                if(!$in_string) 
                { 
                    $indent_level--; 
                    $new_json .= "\n" . str_repeat($tab, $indent_level) . $char; 
                } 
                else 
                { 
                    $new_json .= $char; 
                } 
                break; 
            case ',': 
                if(!$in_string) 
                { 
                    $new_json .= ",\n" . str_repeat($tab, $indent_level); 
                } 
                else 
                { 
                    $new_json .= $char; 
                } 
                break; 
            case ':': 
                if(!$in_string) 
                { 
                    $new_json .= ": "; 
                } 
                else 
                { 
                    $new_json .= $char; 
                } 
                break; 
            case '"': 
                if($c > 0 && $json[$c-1] != '\\') 
                { 
                    $in_string = !$in_string; 
                } 
            default: 
                $new_json .= $char; 
                break;                    
        } 
    } 

    return $new_json; 
}

function jlog($json) {
    if (!is_string($json)) {
        $json = json_encode($json);
    }
    echo json_format($json) . "\n";
}
