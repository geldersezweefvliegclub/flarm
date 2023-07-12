<?php
include 'lib/APRS.php';

const HOST = "aprs.glidernet.org";
const PORT = 14580;
const MYCALL = "GEZC0";
const PASSCODE = -1;
const FILTER = "r/52.06/5.94/50";

$aprs = new APRS(HOST, PORT, MYCALL, PASSCODE, FILTER);
$aprs->_debug = true;


while (1) {
  // handle any received APRS messages
  $aprs->ioloop();

  sleep(1);    // sleep for a second to prevent cpu spinning
  //debug("Done Sleeping".PHP_EOL);
}
