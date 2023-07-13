<?php
include_once 'TimeStamp.php';
include_once 'utils/enums.php';

class OgnSenderBeaconMessage {
  public string $flarm_id;
  public string $registration;
  public string $model;
  public string $last_time;
  public string $latitude;
  public string $longitude;
  public string $altitude;
  public string $ground_speed;
  public string $heading;
  public string $vertical_speed_fpm;
  public string $turn_rate;
  public string $flight_level;
  public string $snr; //does not contain word "db"
  public string $crc_error_rate; //does not contain the "e" at the end
  public string $frequency_offset; //does contain the freq "kHz" at the end
  public string $gps_horizontal_accuracy;
  public string $gps_vertical_accuracy;

  public string $receiver;
  public string $stealth_mode;
  public string $no_tracking;
  public AprsAircraftType $aircraft_type;
  public string $origin;
  public string $original_message;
  public TimeStamp $time_stamp;

  /**
   * @param string $original_message
   */
  public function __construct( string $original_message ) {
    $this->original_message = $original_message;
  }


}