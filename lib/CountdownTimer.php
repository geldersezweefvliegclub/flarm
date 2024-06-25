<?php

class CountdownTimer {
  private int $seconds_until_expiration;

  private int $starting_timestamp = 0;

  public function __construct(int $minutes) {
    $this->seconds_until_expiration = (60 * $minutes);
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