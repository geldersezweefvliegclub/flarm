<?php
include_once 'include/config.php';

class Debug {
  public function echo(string $str, bool $always_show=false): void {
    if(DEBUG === true || $always_show) {
      echo "$str\n";
    }
  }

}