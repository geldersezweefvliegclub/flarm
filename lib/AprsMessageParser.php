<?php
/*
This parser ONLY parses the OGN-APRS message if it is a Sender Beacon.
https://github.com/svoop/ogn_client-ruby/wiki/SenderBeacon
Background:
https://github.com/svoop/ogn_client-ruby/wiki/OGN-flavoured-APRS
http://wiki.glidernet.org/wiki:ogn-flavoured-aprs

*/
include_once 'OgnSenderBeaconMessage.php';
include_once 'TimeStamp.php';
include_once 'utils/enums.php';

class AprsMessageParser {
  public OgnSenderBeaconMessage $ogn_sender_beacon_message;

  public function __construct($line_item) {
    $this->ogn_sender_beacon_message = new OgnSenderBeaconMessage($line_item);
    $this->parse_beacon_message($line_item);
  }

  private function parse_beacon_message(string $msg): void {
    do {
      if (str_starts_with($msg, "#")) {
        unset($this->ogn_sender_beacon_message);
        break;
      }
//TODO  unset on all breaks
      //TODO return values on all required or error producing parses
      $this->parse_origin($msg);
      if (!$this->parse_source($msg)) { break;}
      $this->parse_position_and_time_stamp($msg);
      $this->parse_heading_speed_altitude($msg);
      if (!$this->parse_id($msg)) { break; }
      if (!$this->parse_fpm($msg)) { break; }
      if (!$this->parse_turn_rate($msg)) { break; }
      $this->parse_flight_level($msg);
      if (!$this->parse_snr($msg)) { break; }
      if (!$this->parse_crc_error_rate($msg)) { break; }
      if (!$this->parse_frequency_offset($msg)) { break; }
      $this->parse_gps_signal_quality($msg);

    } while (false);  //only run this code once but allow breaks for easier reading/

  }

  private function parse_origin(string &$msg): void {
    $this->ogn_sender_beacon_message->origin = strstr($msg, '>', true);
    $msg = substr($msg, strlen($this->ogn_sender_beacon_message->origin)+1);
  }

  private function parse_source(string &$msg): bool {
    $sourceEndPos = strpos($msg,':');
    $source = strstr($msg, ':', true);
    $source_array = explode(',', substr($msg,0, $sourceEndPos));
    if (!in_array("TCPIP*", $source_array)) {
      $this->ogn_sender_beacon_message->registration = $source_array[count($source_array)-1];
    } else {
      unset($this->ogn_sender_beacon_message);
      return false;
    }
    $msg = substr($msg, $sourceEndPos, strlen($msg) - $sourceEndPos + 1);
    return true;
  }

  private function parse_position_and_time_stamp(string &$msg): void {
    if (str_starts_with($msg, ':/')) { //indicates position with timestamp will follow
      $position_and_time_stamp_and_more = substr($msg, 2, strlen($msg)-2);
      $this->parse_time_stamp($position_and_time_stamp_and_more);  //TODO make this responsible for altering msg so below code is contained,  this should take the msg.
      $time_stamp_length = strlen($this->ogn_sender_beacon_message->time_stamp->original_time_stamp);
      $msg = substr($msg, $time_stamp_length + 2, strlen($msg) - $time_stamp_length +2 + 1);
      $this->parse_position($msg);
    }

  }

  private function parse_position(&$msg): void {
    //Get Latitude
    $latitude_position = strpos($msg, 'N');
    if ($latitude_position === false) {
      $latitude_position = strpos($msg, 'S');
    }

    $this->ogn_sender_beacon_message->latitude = substr($msg, 0, $latitude_position + 1);

    $longitude_position = strpos($msg, 'E');
    if ($longitude_position === false) {
      $longitude_position = strpos($msg, 'W');
    }
    $this->ogn_sender_beacon_message->longitude = substr($msg, $latitude_position + 2, $longitude_position - $latitude_position - 1);

    //check for precision
    $precision_location = strpos($msg, "!W");
    if ($precision_location !== false) {
      $latitude_precision = substr($msg, $precision_location + 2, 1);
      $longitude_precision = substr($msg, $precision_location + 3, 1);
      if (str_ends_with($this->ogn_sender_beacon_message->latitude, 'N')) {
        $this->ogn_sender_beacon_message->latitude = str_replace('N', $latitude_precision.'N',$this->ogn_sender_beacon_message->latitude);
      } else {
        $this->ogn_sender_beacon_message->latitude = str_replace('S', $latitude_precision.'N',$this->ogn_sender_beacon_message->latitude);
      }

      if (str_ends_with($this->ogn_sender_beacon_message->longitude, 'E')) {
        $this->ogn_sender_beacon_message->longitude = str_replace('E', $longitude_precision.'N',$this->ogn_sender_beacon_message->longitude);
      } else {
        $this->ogn_sender_beacon_message->longitude = str_replace('W', $longitude_precision.'N',$this->ogn_sender_beacon_message->longitude);
      }

      $position_offset = strlen($this->ogn_sender_beacon_message->latitude) + strlen($this->ogn_sender_beacon_message->longitude);
      $msg = substr($msg,$position_offset, strlen($msg) - $position_offset);
      $searchString = "!W".$latitude_precision.$longitude_precision."! ";
      $msg = str_replace($searchString, "", $msg);
    } else {
      $position_offset = strlen($this->ogn_sender_beacon_message->latitude) + 1 + strlen($this->ogn_sender_beacon_message->longitude) + 1;
      $msg = substr($msg,$position_offset, strlen($msg) - $position_offset);
    }
  }

  private function parse_time_stamp($msg): void {
    //see http://www.aprs.org/doc/APRS101.PDF page 22
    //delimeter could be 'h', 'z', or '/' in the 7th character field
    //if none of the above, format will be an 8 character field
    $this->ogn_sender_beacon_message->time_stamp = new TimeStamp();

    $delimeter = substr($msg, 6, 1);
    if (!in_array($delimeter, array('h', 'z', '/'))) {
      $delimeter = '';
      $time_stamp_msg = substr($msg, 0, 8);
    } else {
      $time_stamp_msg = substr($msg, 0, 7);
    }

    switch ($delimeter) {
      case 'h':
        $this->ogn_sender_beacon_message->time_stamp->parseHMS($time_stamp_msg);
        break;
      case 'z':
      case '/':
        $this->ogn_sender_beacon_message->time_stamp->parseDHM($time_stamp_msg);
        break;
      default:
        $this->ogn_sender_beacon_message->time_stamp->parseMDHM($time_stamp_msg);
        break;
    }

    $this->ogn_sender_beacon_message->time_stamp->original_time_stamp = $time_stamp_msg;
  }

  private function parse_heading_speed_altitude(&$msg) : void {
    $heading_speed_altitude = substr($msg, 0, strpos($msg, ' ') + 1);
    $hsa_parts = explode('/', $heading_speed_altitude);
    if (count($hsa_parts) == 3) {
      $this->ogn_sender_beacon_message->heading = $hsa_parts[0];
      $this->ogn_sender_beacon_message->ground_speed = $hsa_parts[1];
      $this->parse_altitude($hsa_parts[2]);
    } else {
      $this->parse_altitude($hsa_parts[0]);
    }

    $msg = substr($msg, strlen($heading_speed_altitude), strlen($msg) - strlen($heading_speed_altitude) + 1);

  }

  private function parse_altitude($raw_altitude) : void {
    $this->ogn_sender_beacon_message->altitude = substr($raw_altitude, 0, 2);
  }

  private function parse_id(&$msg) : bool {
    $this->ogn_sender_beacon_message->flarm_id = substr($msg, 4, 6);
    $hex_data = substr($msg, 2, 2);
    $binary_data = base_convert($hex_data, 16, 2);
    $this->ogn_sender_beacon_message->stealth_mode = substr($binary_data, 0, 1);
    $this->ogn_sender_beacon_message->no_tracking = substr($binary_data, 1, 1);
    $aircraft_binary_type = substr($binary_data, 2, 4);
    $aircraft_decimal_type = base_convert($aircraft_binary_type, 2, 16);
    $this->ogn_sender_beacon_message->aircraft_type = AprsAircraftType::tryFrom($aircraft_decimal_type);
    $address_type = substr($binary_data, 7, 2);

    //"id" + 8 digits + space
    $msg = substr($msg, 11);

    if ($address_type != "10") { //not a FLARM id type
      return false;
    }
    return true;
  }

  private function parse_fpm(&$msg) : bool {
    $fpm_position = strpos($msg, "fpm");
    if (!$fpm_position) {
      return false;
    }
    $this->ogn_sender_beacon_message->vertical_speed_fpm = substr($msg, 0, $fpm_position);
    $msg = substr($msg, $fpm_position + 4);

    return true;
  }

  private function parse_turn_rate(&$msg) : bool {
    $rot_position = strpos($msg, "rot");
    if (!$rot_position) {
      return false;
    }
    $this->ogn_sender_beacon_message->turn_rate = substr($msg, 0, $rot_position);
    $msg = substr($msg, $rot_position + 4);
    return true;
  }

  private function parse_flight_level(&$msg) : void {
    $fl_position = strpos($msg, "FL");

    if ($fl_position !== false) {
      $this->ogn_sender_beacon_message->flight_level = substr( $msg, 0, strpos($msg, " ") );
      $msg = substr( $msg, strlen($this->ogn_sender_beacon_message->flight_level) +1);
    }
  }

  private function parse_snr(&$msg) : bool {
    $snr_position = strpos($msg, "dB");
    if (!$snr_position) {
      return false;
    }
    $this->ogn_sender_beacon_message->snr = substr($msg, 0, $snr_position);
    $msg = substr($msg, $snr_position + 3);
    return true;
  }

  private function parse_crc_error_rate(&$msg) : bool {
    $crc_position = strpos($msg, "e ");
    if (!$crc_position) {
      return false;
    }
    $this->ogn_sender_beacon_message->crc_error_rate = substr($msg, 0, $crc_position);
    $msg = substr($msg, $crc_position + 2);
    return true;
  }

  private function parse_frequency_offset(&$msg) : bool {
    $freq_offset_position = strpos($msg, "Hz");
    if (!$freq_offset_position) {
      return false;
    }
    $this->ogn_sender_beacon_message->frequency_offset = substr($msg, 0, $freq_offset_position+2);
    if (strpos($msg, " ") !== false) {
      //there is more data so can remove the space after the frequency offset
      $msg = substr($msg, $freq_offset_position + 3);
    } else {
      //no more data
      $msg = substr($msg, $freq_offset_position + 2);
    }

    return true;
  }

  private function parse_gps_signal_quality(&$msg) : void {
    $gps_offset_position = strpos($msg, "gps");
    if ($gps_offset_position !== false) {
      $end_of_gps = strpos($msg, " ");
      if ($end_of_gps !== false) {
        $gps_signal_quality = substr($msg, $gps_offset_position, $end_of_gps);
      } else {
        $gps_signal_quality = substr($msg, $gps_offset_position);
      }

      $gps_signal_quality_data = str_replace("gps", "", $gps_signal_quality);
      $gps_signal_quality_array = explode("x", $gps_signal_quality_data);
      $this->ogn_sender_beacon_message->gps_horizontal_accuracy = $gps_signal_quality_array[0];
      $this->ogn_sender_beacon_message->gps_vertical_accuracy = $gps_signal_quality_array[1];
      $msg = substr($msg, strlen($gps_signal_quality));
    }
  }
}