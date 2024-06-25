<?php

class ProgramTimer {
  private int $start_time_hour;
  private int $end_time_hour;

  public function __construct(int $start_time_hour, int $end_time_hour) {
    $this->start_time_hour = $start_time_hour;
    $this->end_time_hour = $end_time_hour;
  }


  public function can_run() : bool {
    $localtime_assoc = localtime(time(), true);
    $h = $localtime_assoc['tm_hour'];
    if ($h >= $this->start_time_hour  && $h < $this->end_time_hour) {
      return true;
    } else {
      return false;
    }
  }

}