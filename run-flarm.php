<?php
/*
TODO: standardize $flarm_id to minimize strtolower if possible
*/

if (!file_exists("include/config.php"))
{
    echo "No config file, no glory. Add 'include/config.php'";
    die;
}

include 'lib/APRS.php';
include 'lib/AprsMessageParser.php';
include 'lib/DatabaseAircraft.php';
include 'lib/DatabaseStart.php';
include_once 'include/config.php';
include_once 'lib/Curl.php';
include_once 'lib/Debug.php';
include_once 'lib/ProgramTimer.php';
include_once 'lib/CountdownTimer.php';

date_default_timezone_set('Europe/Amsterdam');

$debug = new Debug();
$db_aircraft_load_timer = new CountdownTimer(REFRESH_FROM_AIRCRAFT_DB_MINUTES);
$db_starts_load_timer = new CountdownTimer(REFRESH_FROM_STARTS_DB_MINUTES);
$check_delayed_landings_timer = new CountdownTimer(DELAYED_LANDING_MINUTES);

//create an array of all aircraft we are concerned about indexed by flarm ID
$db_aircraft_array = load_aircraft();
$db_aircraft_load_timer->start();

$db_starts_array = load_starts();
$db_starts_load_timer->start();

$check_delayed_landings_timer->start();
$previous_updates = array();              // array to store the last time we updated the aircraft in the helios database

//connect to APRS
$aprs = new APRS(SERVER, PORT, MYCALL, PASSCODE, FILTER);


$program_timer = new ProgramTimer(PROGRAM_START_TIME_HOUR, PROGRAM_END_TIME_HOUR);

while (1) {
    if ($program_timer->can_run()) {
        $debug->echo("Daylight");

        if ($db_aircraft_load_timer->is_timer_expired()) {
            $db_aircraft_array = load_aircraft();
            $db_aircraft_load_timer->start();
        }

        if ($db_starts_load_timer->is_timer_expired()) {
            $db_starts_array = load_starts();
            $db_starts_load_timer->start();
        }

        if ($check_delayed_landings_timer->is_timer_expired()) {
            check_delayed_landings($previous_updates, $db_starts_array);
            $check_delayed_landings_timer->start();
        }

        if (!$aprs->is_connected()) {
            $aprs->connect();
            $last_helios_update = array();
        }

        // handle any received APRS messages
        $data_array = $aprs->run();
        foreach($data_array as $data) {

            // sentence must contain a '>' character
            if ((strpos($data, '>') === false) || strpos($data, "\r") === false) {
                continue;
            }

            $aprs_message_parser = new AprsMessageParser($data);

            $flarm_data = $aprs_message_parser->get_aircraft_properties();
            if ($flarm_data == null || $flarm_data->flarm_id === false) {
                continue;
            }

            $flarm_id = strtolower($flarm_data->flarm_id);

            if (isset($db_aircraft_array[$flarm_id])) {

                $aircraft_db_id = $db_aircraft_array[$flarm_id]->id;
                $flarm_data->reg_call = $db_aircraft_array[$flarm_id]->reg_call;
                $flarm_data->vliegtuig_id = $aircraft_db_id;

                if (!array_key_exists($flarm_id, $previous_updates)) {
                    $result = register_aircraft($aircraft_db_id);
                    $debug->echo("REGISTERED " . $flarm_data->reg_call);
                }

                if ($flarm_data->ground_speed < 50 && $previous_updates[$flarm_id]->ground_speed > 50)     // 50 k/m is the minimum speed for a valid flight
                    if (array_key_exists($aircraft_db_id, $db_starts_array)) {
                    {
                        $start = $db_starts_array[$aircraft_db_id];
                        $debug->echo("------- LANDING:" . $flarm_data->reg_call . " " . $start->id);
                        register_landing($start->id);
                    }
                }
                $previous_updates[$flarm_id] = $flarm_data;
            }

            $str = isset($flarm_data->reg_call) ? $flarm_data->reg_call : $flarm_data->flarm_id;
            $debug->echo("RECEIVED:". $str . " GS:" . $flarm_data->ground_speed . " ALT:" . $flarm_data->altitude);
        }
    } else {
        $debug->echo("Dark");
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

function load_starts() : array {
    $starts = array();
    $curl = new Curl();
    $json_data = $curl->exec_get(STARTS_GET_ALL_OPEN_URL);
    if ($json_data) {
        $starts = array();
        foreach($json_data->dataset as $jsonstarts) {
            $database_start = DatabaseStart::fromObject( $jsonstarts );

            if (!isset($database_start->starttijd)) {   // not yet started
                continue;
            }

            if (array_key_exists($database_start->vliegtuig_id, $starts)) {     // previous flight is not landed
                $tijd1 = 60*explode($starts[$database_start->vliegtuig_id]->starttijd, ":")[0] + explode($starts[$database_start->vliegtuig_id]->starttijd, ":")[1];
                $tijd2 = 60*explode($$database_start->starttijd, ":")[0] + explode($database_start->starttijd, ":")[1];

                if ($tijd1 < $tijd2)   // use latest start
                    continue;
            }
            $starts[$database_start->vliegtuig_id] = $database_start;
        }
    }

    return $starts;
}

function register_aircraft(string $aircraft_id) : mixed {
    $curl = new Curl();
    return $curl->exec_post(VLIEGTUIGEN_AANMELDEN, ["VLIEGTUIG_ID" => $aircraft_id ]);
}

function check_delayed_landings($last_updates, $db_starts_array) {
    if ($db_starts_array == null || count($db_starts_array) == 0) {
        return;
    }
    $now = date("h") * 3600 + date("i") * 60 + date("s");

    foreach ($last_updates as $flarm_data) {
        if (($flarm_data->altitude < 250) && ($flarm_data->ground_speed > 50) && (($now - $flarm_data->msg_received) > 45)) {
            $aircraft_db_id = $flarm_data->vliegtuig_id;
            if (array_key_exists($aircraft_db_id, $db_starts_array)) {
                $start = $db_starts_array[$aircraft_db_id];

                register_landing($start->id);
            }
        }
    }
}

function register_landing(string $start_id) : mixed {
    $debug = new Debug();
    $debug->echo("ID:". $start_id);

    $curl = new Curl();
    $start = $curl->exec_get(START_OPHALEN, ["ID" => $start_id]);

    // niet nog een keer registreren
    if (!isset($start->EXTERNAL_ID)) {
        return $curl->exec_put(START_OPSLAAN, ["ID" => $start_id, "EXTERNAL_ID" => date("H:i")]);
    }
    return null;
}

