<?php
include 'lib/APRS.php';
include 'lib/AprsMessageParser.php';

const SERVER = "aprs.glidernet.org";
const PORT = 14580;
const MYCALL = "GEZC0";
const PASSCODE = -1;
const FILTER = "r/52.06/5.94/50";

CONST DEBUG = false;

date_default_timezone_set("Europe/Amsterdam");
$date = date('d-m-Y');
$time = date('G:i');

echo "Date/Time: {$date}/{$time}\n";

$aprs = new APRS(SERVER, PORT, MYCALL, PASSCODE, FILTER);
$aprs->_debug = DEBUG;


$aprs->connect();  //should this be automatic in run???
while (1) {
  // handle any received APRS messages
  //$aprs->ioloop();
  $data_array = $aprs->run();
  foreach($data_array as $data) {
    debug($data);
    echo "$data".PHP_EOL;
    new AprsMessageParser($data);
    //parse $data

  }

  sleep(1);    // sleep for a second to prevent cpu spinning
  //debug("Done Sleeping".PHP_EOL);
}

function debug($str):void {
  if(DEBUG === true) {
    echo "$str".PHP_EOL;
  }
}
