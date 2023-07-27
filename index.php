<?php
/*
TODO: standardize $flarm_id to minimize strtolower if possible
*/

include 'lib/APRS.php';
include 'lib/AprsMessageParser.php';
include 'lib/DatabaseAircraft.php';
include_once 'include/config.php';
include_once 'lib/Curl.php';
include_once 'lib/Debug.php';
include_once 'lib/ProgramTimer.php';
include_once 'lib/CountdownTimer.php';

$debug = new Debug();
$db_aircraft_load_timer = new CountdownTimer(REFRESH_FROM_AIRCRAFT_DB_HOURS,REFRESH_FROM_AIRCRAFT_DB_MINUTES,REFRESH_FROM_AIRCRAFT_DB_SECONDS);

//create an array of all aircraft we are concerned about indexed by flarm ID
$db_aircraft_array = load_aircraft();
$db_aircraft_load_timer->start();
$previously_checked_aircraft = array();


//connect to APRS
$aprs = new APRS(SERVER, PORT, MYCALL, PASSCODE, FILTER);


$program_timer = new ProgramTimer(PROGRAM_START_TIME_HOUR, PROGRAM_END_TIME_HOUR);

$memory_check_timer = new CountdownTimer(0, 5, 0);
$starting_memory_usage = memory_get_usage();
$memory_usage = $starting_memory_usage;
echo "Starting: " . $memory_usage.PHP_EOL;
$memory_check_timer->start();

while (1) {
  if ($memory_check_timer->is_timer_expired()) {
    $current_memory_usage = memory_get_usage();
    $memory_difference = $current_memory_usage - $memory_usage;
    $total_difference = $current_memory_usage - $starting_memory_usage;
    echo $current_memory_usage." : (".$memory_difference.") ".$total_difference.PHP_EOL;
    $memory_usage = $current_memory_usage;
    $memory_check_timer->start();
  }

  if ($program_timer->can_run()) {
    $debug->echo("Can Run");
    if ($db_aircraft_load_timer->is_timer_expired()) {
      $db_aircraft_array = load_aircraft();
      $db_aircraft_load_timer->start();
    }

    if (!$aprs->is_connected()) {
      $aprs->connect();
      $previously_checked_aircraft = array();

    }

    // handle any received APRS messages
    $data_array = $aprs->run();
    foreach($data_array as $data) {
      $aprs_message_parser = new AprsMessageParser($data);
      $flarm_id = $aprs_message_parser->get_flarm_id();
      if ($flarm_id) {
        if (!array_key_exists(strtolower($flarm_id), $previously_checked_aircraft)) {
          if (isset($db_aircraft_array[strtolower($flarm_id)])) {
            $aircraft_db_id = $db_aircraft_array[strtolower($flarm_id)]->id;
            $result = register_aircraft($aircraft_db_id);
            $debug->echo($data);
            $debug->echo("REGISTERED ".$db_aircraft_array[strtolower($flarm_id)]->callsign);
            $previously_checked_aircraft[strtolower($flarm_id)] = $result;
          }
        }
      }
    }

  } else {
    $debug->echo("Can't Run");
    if ($aprs->is_connected()) {
      $aprs->disconnect();
    }
  }

  sleep(1);    // sleep for a second to prevent cpu spinning
}

function load_aircraft() : array {
  $aircraft = array();
  $curl = new Curl();
  $json_data = $curl->exec_get(VLIEGTUIGEN_GET_ALL_OBJECTS_URL);
  if ($json_data) {
    foreach($json_data->dataset as $jsonAircraft) {
      $database_aircraft = DatabaseAircraft::fromObject( $jsonAircraft );
      $aircraft[strtolower($database_aircraft->flarmcode)] = $database_aircraft;
    }
  }

  return $aircraft;
}

function register_aircraft(string $aircraft_id) : mixed {
  $curl = new Curl();
  return $curl->exec_post(VLIEGTUIGEN_AANMELDEN, ["VLIEGTUIG_ID" => $aircraft_id ]);
}
