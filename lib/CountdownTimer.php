<?php

class CountdownTimer {
  private int $seconds_until_expiration;

  private int $starting_timestamp = 0;

  public function __construct(int $hours, int $minutes, int $seconds) {
    $this->seconds_until_expiration = $seconds + (60 * $minutes) + (3600 * $hours);
  }

  public function current_timestamp() : int {
    return (new DateTime("now"))->getTimestamp();
  }

  public function start() : void {
    $this->starting_timestamp = $this->current_timestamp();
  }

  public function is_timer_expired() : bool {
    return ( $this->current_timestamp() > $this->seconds_until_expiration + $this->starting_timestamp );
  }
}