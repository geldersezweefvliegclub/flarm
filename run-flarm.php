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
include_once 'lib/Kalman.php';

include_once 'enum/AircraftStatus.php';

date_default_timezone_set('Europe/Amsterdam');

// 30 k/m is the minimum sneldheid om te vliegen
const MIN_SPEED = 30;

$debug = new Debug();
$db_aircraft_load_timer = new CountdownTimer(REFRESH_FROM_AIRCRAFT_DB_MINUTES);
$db_starts_load_timer = new CountdownTimer(REFRESH_FROM_STARTS_DB_MINUTES);
$check_lost_timer = new CountdownTimer(DELAYED_LANDING_MINUTES);

//create an array of all aircraft we are concerned about indexed by flarm ID
$db_aircraft_array = load_aircraft();
$db_aircraft_load_timer->start();

$airport = load_airports();

$db_starts_array = load_starts();
$db_starts_load_timer->start();

$check_lost_timer->start();
$previous_updates = array();            // array to store the last time we updated the aircraft in the helios database
$kalman_speed_array = array();          // array to store the kalman filter for the speed
$kalman_altitude_array = array();       // array to store the kalman filter for the altitude
$kalman_fpm_array = array();            // array to store the kalman filter for the climb rate

//connect to APRS
$aprs = new APRS(SERVER, PORT, MYCALL, PASSCODE, FILTER);
$program_timer = new ProgramTimer(PROGRAM_START_TIME_HOUR, PROGRAM_END_TIME_HOUR);
$last_data_record = null;


while (1) {
    if ($program_timer->can_run())
    {
        if ($db_aircraft_load_timer->is_timer_expired()) {
            $db_aircraft_array = load_aircraft();
            $db_aircraft_load_timer->start();
        }

        if ($db_starts_load_timer->is_timer_expired()) {
            $db_starts_array = load_starts();
            $db_starts_load_timer->start();
        }

        if ($check_lost_timer->is_timer_expired()) {
            check_lost();
            $check_lost_timer->start();
        }

        if (!$aprs->is_connected()) {
            $aprs->connect();
        }

        // handle any received APRS messages
        $data_array = $aprs->run();

        for ($i = 0; $i < count($data_array); $i++)
        {
            $data = isset($last_data_record) && ($i == 0) && substr($last_data_record, -1) != "\r" ? $last_data_record . $data_array[$i] : $data_array[$i];

            // sentence must contain a '>' character
            if ((strpos($data, '>') === false) || strpos($data, "\r") === false) {
                continue;
            }

            try
            {
                $aprs_message_parser = new AprsMessageParser($data);
            }
            catch (Exception $e)
            {
                continue;
            }

            $flarm_data = $aprs_message_parser->get_aircraft_properties();
            if ($flarm_data == null || $flarm_data->flarm_id === false) {
                continue;
            }

            $flarm_id = strtolower($flarm_data->flarm_id);

            // bepaal de kalman filter voor de snelheid en de hoogte
            if (!array_key_exists($flarm_id, $kalman_speed_array)) {
                $kalman_speed_array[$flarm_id]= new KalmanFilter();
            }
            $flarm_data->kalman_speed = $kalman_speed_array[$flarm_id]->filter($flarm_data->ground_speed);

            if (!array_key_exists($flarm_id, $kalman_altitude_array)) {
                $kalman_altitude_array[$flarm_id] = new KalmanFilter();
            }
            $flarm_data->kalman_altitude = $kalman_altitude_array[$flarm_id]->filter($flarm_data->altitude);

            if (!array_key_exists($flarm_id, $kalman_fpm_array)) {
                $kalman_fpm_array[$flarm_id] = new KalmanFilter();
            }
            $flarm_data->kalman_vertical_speed_fpm = $kalman_fpm_array[$flarm_id]->filter($flarm_data->vertical_speed_fpm);
            // done

            setGliderStatus($flarm_data);

            if ($flarm_data->status !== GliderStatus::NoIntrest)
                $previous_updates[$flarm_id] = $flarm_data;

            $reg_call = isset($flarm_data->reg_call) ? $flarm_data->reg_call : $flarm_data->flarm_id;
            $msg = sprintf("Ontvangen: %s start ID: %s  GS:%s|%s ALT:%s|%s  %s", $reg_call,  $flarm_data->start_id, $flarm_data->ground_speed, $flarm_data->kalman_speed, $flarm_data->altitude, $flarm_data->kalman_altitude, $flarm_data->status->value);
            $debug->echo($msg);
        }
        $last_data_record = count($data_array) > 0 ? $data_array[count($data_array) - 1] : null;
        sleep(1);    // sleep for a second to prevent cpu spinning
    }
    else
    {
        $debug->echo("Dark");
        if ($aprs->is_connected()) {
            $aprs->disconnect();
        }

        if (count($previous_updates) > 0)
            $previous_updates = array();

        if (count($kalman_speed_array) > 0)
            $kalman_speed_array = array();

        if (count($kalman_altitude_array) > 0)
            $kalman_altitude_array = array();

        sleep(600);    // sleep for 10 minutes
    }
}

function setGliderStatus($flarm_data) {
    global $db_starts_array;
    global $db_aircraft_array;
    global $previous_updates;

    $debug = new Debug();
    $flarm_id = strtolower($flarm_data->flarm_id);

    if (!isset($db_aircraft_array[$flarm_id]))
    {
        $flarm_data->status = GliderStatus::NoIntrest;
        return;
    }

    $aircraft_db_id = $db_aircraft_array[$flarm_id]->id;
    $flarm_data->reg_call = $db_aircraft_array[$flarm_id]->reg_call;
    $flarm_data->vliegtuig_id = $aircraft_db_id;
    $flarm_data->start_id = (array_key_exists($aircraft_db_id, $db_starts_array) ? $db_starts_array[$aircraft_db_id]->id : null);

    if (!array_key_exists($flarm_id, $previous_updates)) {
        $result = register_aircraft($aircraft_db_id);
        $debug->echo(sprintf("REGISTERED %s", $flarm_data->reg_call));
    }

    if (isset($flarm_data->kalman_speed) && isset($flarm_data->kalman_altitude))
    {
        if (($flarm_data->kalman_speed > MIN_SPEED) && ($flarm_data->kalman_altitude > (VLIEGVELD_HOOGTE + 250)))
        {
            $flarm_data->status = GliderStatus::Flying;
        }
        else if (array_key_exists($flarm_id, $previous_updates))
        {
            if (($flarm_data->kalman_speed > MIN_SPEED) &&
                ($flarm_data->kalman_altitude <= (VLIEGVELD_HOOGTE + 250)) &&
                ($flarm_data->kalman_altitude > (VLIEGVELD_HOOGTE + 50)) &&
                ($previous_updates[$flarm_id]->status == GliderStatus::Flying))
            {
                $flarm_data->status = GliderStatus::Circuit;
            }
            else if (($flarm_data->kalman_speed > MIN_SPEED) &&
                     ($flarm_data->kalman_altitude <= (VLIEGVELD_HOOGTE + 50)) &&
                        (($previous_updates[$flarm_id]->status == GliderStatus::Flying) ||
                         ($previous_updates[$flarm_id]->status == GliderStatus::Circuit)))
            {
                $flarm_data->status = GliderStatus::Landing;
            }
            else if (($flarm_data->kalman_speed > MIN_SPEED) && ($flarm_data->kalman_altitude > (VLIEGVELD_HOOGTE + 50)) &&
                     ($previous_updates[$flarm_id]->status == GliderStatus::On_Ground))
            {
                $flarm_data->status = GliderStatus::TakeOff;
            }
            else if ($flarm_data->kalman_speed <= MIN_SPEED && ($flarm_data->kalman_altitude <= (VLIEGVELD_HOOGTE + 50)))
            {
                // als flarm updates goed doorkomen dat is status Landing, echter als we updates gemiste hebben dan moeten we een fallback hebben
                // Wanneer vliegtuig flarm aanzet op het veld is de status Unknown
                // Logica: indien de snelheid constant is (waarschijnlijk 0) dan staat het vliegtuig met zekerheid aan de grond

                $staat_stil = ($flarm_data->kalman_speed == $previous_updates[$flarm_id]->kalman_speed);    // snelheid is constant, waarschijnlijk 0
                if (($previous_updates[$flarm_id]->status == GliderStatus::Circuit) ||
                    ($previous_updates[$flarm_id]->status == GliderStatus::Landing) || $staat_stil)
                {
                    if (($previous_updates[$flarm_id]->status == GliderStatus::Circuit) ||
                        ($previous_updates[$flarm_id]->status == GliderStatus::Landing)) {

                        if (isset($flarm_data->start_id))
                        {
                            $debug->echo(sprintf("------- LANDING: %s %s", $flarm_data->reg_call, $flarm_data->start_id));
                            register_landing($flarm_data->start_id);
                        } else {
                            $debug->echo(sprintf("------- LANDING: %s NO START", $flarm_data->reg_call));
                        }
                    }
                    $flarm_data->status = GliderStatus::On_Ground;
                }
            }
        }
    }
    // geen nieuwe status, neem de oude status over
    if (($flarm_data->status == GliderStatus::Unknown) && array_key_exists($flarm_id, $previous_updates))
        $flarm_data->status = $previous_updates[$flarm_id]->status;
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
    global $db_aircraft_array;
    global $airport;

    $debug = new Debug();
    $debug->echo("load_starts()");

    $starts = array();
    $curl = new Curl();
    $json_data = $curl->exec_get(STARTS_GET_ALL_OPEN_URL);
    if ($json_data) {
        $starts = array();
        foreach($json_data->dataset as $jsonstarts) {
            $json_start = DatabaseStart::fromObject( $jsonstarts );

            $t = $array = array_values($db_aircraft_array);
            $idx = array_search($json_start->vliegtuig_id, array_column($t, 'id'));

            $kist = ($idx === false) ? null : $t[$idx];
            $reg_call = isset($kist) ? $kist->reg_call : $json_start->vliegtuig_id;

            // Is deze start op het veld waarvoor we de data willen hebben?
            if (isset($json_start->veld_id) && isset($airport)) {
                if ($airport->ID != $json_start->veld_id)
                    continue;
            }

            if (!isset($json_start->starttijd)) {   // not yet started
                $debug->echo( sprintf("%s/%s nog niet gestart continue",  $json_start->id, $json_start->reg_call));
                continue;
            }

            if (isset($json_start->landingstijd)) {   // already landed
                $now = date("H") * 60 + date("i");
                $landingstijd = (explode(":", $json_start->landingstijd)[0])*60 + (explode(":", $json_start->landingstijd)[1])*1;

                if ($now - $landingstijd > 15) {
                    continue;
                }
            }

            // gebruik altijd de start met de laatste starttijd
            if (array_key_exists($json_start->vliegtuig_id, $starts)) {
                $debug->echo(sprintf ("Er is al een start voor %s %s/%s",
                    $reg_call,
                    $starts[$json_start->vliegtuig_id]->starttijd, $json_start->starttijd));

                // maak van tijd een numerieke waarde om te kunnen vergelijken
                $tijd1 = str_replace(":", "0", $starts[$json_start->vliegtuig_id]->starttijd) * 1;
                $tijd2 = str_replace(":", "0", $json_start->starttijd) * 1;

                if ($tijd1 > $tijd2)   // use latest start
                {
                    $debug->echo(sprintf("%s Start %s wordt overschreven door %s, starttijd %s is later",
                        $reg_call,
                        $starts[$json_start->vliegtuig_id]->id,
                        $json_start->id),
                        $json_start->starttijd);
                    continue;
                }
            }
            $starts[$json_start->vliegtuig_id] = $json_start;
        }

        foreach($starts as $s)
        {
            $t = $array = array_values($db_aircraft_array);
            $idx = array_search($s->vliegtuig_id, array_column($t, 'id'));

            $kist = ($idx === false) ? null : $t[$idx];
            $reg_call = isset($kist) ? $kist->reg_call : $json_start->vliegtuig_id;

            $debug->echo( sprintf("** %s  %s/%s", $reg_call, $s->starttijd, $s->landingstijd. " **"));
        }
    }

    return $starts;
}

function load_airports() : mixed {

    $curl = new Curl();
    $json_data = $curl->exec_get(AIRPORTS_URL);
    if ($json_data) {
        foreach($json_data->dataset as $airport) {
            if ($airport->CODE == VLIEGVELD_CODE) {
                return $airport;
            }
        }
    }
    return null;
}

function register_aircraft(string $aircraft_id) : mixed {
    $args = array("ID" => $aircraft_id);
    if (isset($airport)) {
        $args["VLIEGVELD_ID"] = $airport->id;
    }

    $curl = new Curl();
    return $curl->exec_post(VLIEGTUIGEN_AANMELDEN, $args);
}

function check_lost()
{
    global $previous_updates;
    global $kalman_speed_array;
    global $kalman_altitude_array;
    global $kalman_fpm_array;

    $debug = new Debug();
    $now = date("H") * 3600 + date("i") * 60 + date("s");

    $tobeRemoved = array();
    foreach ($previous_updates as $flarm_data)
    {
        // if we have not received an update for 1 minute and the aircraft is in the circuit or landing, we predict the altitude and check if it is below the airfield height
        // if so, we consider the aircraft as landed
        if (($now - $flarm_data->msg_received) >= 60)
        {
            $debug->echo(sprintf("Lost 1 min: %s %s", $flarm_data->reg_call, $flarm_data->status->value));

            if (($flarm_data->status == GliderStatus::Circuit || $flarm_data->status == GliderStatus::Landing))    // update received more than 1 minute ago)
            {
                $dt = ($now - $flarm_data->msg_received) / 60;      // in minutes
                $predicted_altitude = $flarm_data->altitude + ($flarm_data->kalman_vertical_speed_fpm * $dt) * 0.3048; // in meters
                $debug->echo(sprintf("last altitude: %s   vspeed_fpm: %d time: %d predicted altitude: %s",
                        $flarm_data->altitude,
                        $flarm_data->kalman_vertical_speed_fpm,
                        $dt,  $predicted_altitude));

                if ($predicted_altitude < (VLIEGVELD_HOOGTE))
                {
                    if (isset($flarm_data->start_id))
                    {
                        $debug->echo(sprintf("------- PREDICTED LANDING: %s %s", $flarm_data->reg_call, $flarm_data->start_id));
                        register_landing($flarm_data->start_id);
                    }
                    unset($previous_updates[$flarm_data->flarm_id]);
                    unset($kalman_speed_array[$flarm_data->flarm_id]);
                    unset($kalman_altitude_array[$flarm_data->flarm_id]);
                    unset($kalman_fpm_array[$flarm_data->flarm_id]);
                }
            }
        }

        if (($now - $flarm_data->msg_received) < 600)
            continue;       // update received less than 10 minutes ago

        // if we have not received an update for 10 minutes and the aircraft is in the circuit or landing, we consider the aircraft as landed
        // the vertical speed prediction did not work, so we have to rely on the time
        // as soon as the glider starts the circuit, we know that there is no way back and the glider will land
        if ($flarm_data->status == GliderStatus::Circuit || $flarm_data->status == GliderStatus::Landing)
        {
            if (isset($flarm_data->start_id))
            {
                $debug->echo(sprintf("------- DELAYED LANDING: %s %s", $flarm_data->reg_call, $flarm_data->start_id));

                $landingstijd = strtotime('-5 minutes');
                register_landing($start->id, date('H:i', $landingstijd));
            }
        }
        $tobeRemoved[] = strtolower($flarm_data->flarm_id);
    }

    // Remove the lost aircraft from the previous_updates array
    foreach ($tobeRemoved as $flarm_id)
    {
        $debug->echo(sprintf("Unset: %s %s", $flarm_id, $previous_updates[$flarm_id]->reg_call));

        unset($previous_updates[$flarm_id]);
        unset($kalman_speed_array[$flarm_data->flarm_id]);
        unset($kalman_altitude_array[$flarm_data->flarm_id]);
        unset($kalman_fpm_array[$flarm_data->flarm_id]);
    }

    // show the remaining aircraft in the previous_updates array
    $inMemory = "";
    foreach ($previous_updates as $flarm_data)
    {
        $reg_call = isset($flarm_data->reg_call) ? $flarm_data->reg_call : $flarm_data->flarm_id;
        if ($inMemory != "")
            $inMemory .= ",";

        $inMemory .= sprintf("%s", $reg_call);
    }

    if ($inMemory != "")
        $debug->echo(sprintf("previous_updates=[%s]", $inMemory));
    else
        $debug->echo("previous_updates is empty");
}

function register_landing(string $start_id, $landingstijd = null) : mixed {
    $debug = new Debug();

    $curl = new Curl();
    $start = $curl->exec_get(START_OPHALEN, ["ID" => $start_id]);

    // niet nog een keer registreren
    if (!isset($start->EXTERNAL_ID)) {
        $l = ($landingstijd == null) ? date("H:i") : $landingstijd;
        $debug->echo(sprintf("register_landing(%s, %s) -> %s", $start_id, $landingstijd, $l));

        return $curl->exec_put(START_OPSLAAN, ["ID" => $start_id, "EXTERNAL_ID" => $l]);
    }
    return null;
}

