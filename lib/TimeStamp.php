<?php

enum TIMESTAMP_TYPE {
  case DHM;
  case HMS;
  case MDHM;
}

class TimeStamp {
  public string $month;
  public string $day;
  public string $hours;
  public string $minutes;
  public string $seconds;
  public bool $isUTC;
  public string $original_time_stamp;
  public TIMESTAMP_TYPE $type;


  public function __construct() {
    $this->month = '';
    $this->day = '';
    $this->hours = '';
    $this->minutes = '';
    $this->seconds = '';
    $this->isUTC = true;
    $this->original_time_stamp = '';
  }

  public function toString() : string {
    $str = '';
    switch ($this->type) {
      case TIMESTAMP_TYPE::DHM:
        $str = $this->day . $this->hours . $this->minutes . ($this->isUTC ? 'z' : '/');
        break;
      case TIMESTAMP_TYPE::HMS:
        $str = $this->hours . $this->minutes . $this->seconds;
        break;
      case TIMESTAMP_TYPE::MDHM:
        $str = $this->month . $this->day . $this->hours . $this->minutes;
        break;
    }

    return $str;
  }

  public function parseDHM($dhmStr) : void {
    $lastChar = $dhmStr[strlen($dhmStr) - 1];
    if ($lastChar == '/') {
      $this->isUTC = false;
    }
    $this->day = substr($dhmStr,0,2);
    $this->hours = substr($dhmStr, 2, 2);
    $this->minutes = substr($dhmStr, 4, 2);
    $this->type = TIMESTAMP_TYPE::DHM;
  }

  public function parseHMS($hmsStr) : void {
    $this->hours = substr($hmsStr,0,2);
    $this->minutes = substr($hmsStr, 2, 2);
    $this->seconds = substr($hmsStr, 4, 2);
    $this->type = TIMESTAMP_TYPE::HMS;
  }

  public function parseMDHM($mdhmStr) : void {
    $this->month = substr($mdhmStr,0,2);
    $this->day = substr($mdhmStr, 2, 2);
    $this->hours = substr($mdhmStr, 4, 2);
    $this->minutes = substr($mdhmStr, 6, 2);
    $this->type = TIMESTAMP_TYPE::MDHM;
  }
}