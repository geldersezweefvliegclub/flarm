<?php
/*
TODO: standardize $flarm_id to minimize strtolower if possible
TODO: clear on end of day the aircraft registered
TODO: stop running aprs at end of day. start at beginning of day.
*/

include 'lib/APRS.php';
include 'lib/AprsMessageParser.php';
include 'lib/DatabaseAircraft.php';
include_once 'include/config.php';
include_once 'include/constants.php';
include_once 'lib/Curl.php';
include_once 'lib/Debug.php';

$debug = new Debug();

//Grab current local time for time variant execution.
date_default_timezone_set("Europe/Amsterdam");
$date = date('d-m-Y');
$time = date('G:i');

//create an array of all aircraft we are concerned about indexed by flarm ID
$terlet_aircraft_array = load_aircraft();
$previously_checked_aircraft = array();

//connect to APRS
$aprs = new APRS(SERVER, PORT, MYCALL, PASSCODE, FILTER);


$aprs->connect();  //should this be automatic in run???
while (1) {
  // handle any received APRS messages
  $data_array = $aprs->run();
  foreach($data_array as $data) {
    $aprs_message_parser = new AprsMessageParser($data);
    $flarm_id = $aprs_message_parser->get_flarm_id();
    if ($flarm_id) {
      if (!array_key_exists(strtolower($flarm_id), $previously_checked_aircraft)) {
        if (isset($terlet_aircraft_array[strtolower($flarm_id)])) {
          $aircraft_db_id = $terlet_aircraft_array[strtolower($flarm_id)]->id;
          $result = register_aircraft($aircraft_db_id);
          $debug->echo($data);
          $debug->echo("REGISTERED ".$terlet_aircraft_array[strtolower($flarm_id)]->callsign);
          $previously_checked_aircraft[strtolower($flarm_id)] = $result;
        }
      }
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
  $response = $curl->exec_post(VLIEGTUIGEN_AANMELDEN, ["VLIEGTUIG_ID" => $aircraft_id ]);

  return $response;
}
