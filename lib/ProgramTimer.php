<?php

class ProgramTimer {
  private int $start_time_hour;
  private int $end_time_hour;

  public function __construct(int $start_time_hour, int $end_time_hour) {
    $this->start_time_hour = $start_time_hour;
    $this->end_time_hour = $end_time_hour;
  }

  private function current_timestamp() : int {
    return (new DateTime("now"))->getTimestamp();
  }

  private function start_timestamp() : int {
    return (new DateTime("now"))->setTime($this->start_time_hour, 0)->getTimestamp();
  }

  private function end_timestamp() : int {
    return (new DateTime("now"))->setTime($this->end_time_hour, 0)->getTimestamp();
  }

  public function can_run() : bool {
    $current = $this->current_timestamp();
    if ($current >= $this->start_timestamp()  && $current < $this->end_timestamp()) {
      return true;
    } else {
      return false;
    }
  }

}