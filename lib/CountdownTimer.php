<?php

class CountdownTimer {
  private int $seconds;

  private int $starting_timestamp;
  private DateTime $date_time;

  public function __construct(int $hours, int $minutes, int $seconds) {
    $this->seconds = $seconds + (60 * $minutes) + (3600 * $hours);
    $this->date_time = new DateTime("now");
  }

  public function current_timestamp() : int {
    return (new DateTime("now"))->getTimestamp();
  }
  public function start() : void {
    $this->date_time->modify("now");
    $this->starting_timestamp = $this->date_time->getTimestamp();
  }

  public function is_timer_expired() : bool {
    return ( $this->seconds + $this->current_timestamp() > $this->starting_timestamp );
  }
}