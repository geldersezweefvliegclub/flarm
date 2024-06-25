<?php

class DatabaseAircraft {
  public ?int $id;
  public string $reg_call;
  public string $flarmcode;

  public function __construct() {
    $this->id                       = null;
    $this->reg_call                 = '';
    $this->flarmcode                = '';
  }

  public static function fromObject($obj) {
    $aircraft = new self();
    foreach ($obj as $key => $value) {
      $lower_case_key = strtolower( $key );
      if ( $value === null ) {
        if ( gettype( $aircraft->{$lower_case_key} ) === "string" ) {
          $value = '';
        }
      }
      $aircraft->{$lower_case_key} = $value;
    }
    return $aircraft;
  }
}