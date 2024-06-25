<?php

class DatabaseStart {
  public ?int $id;
  public int $vliegtuig_id;
  public ?string $starttijd;

  public function __construct() {
    $this->id                       = null;
    $this->vliegtuig_id             = -1;
    $this->starttijd                = '';
  }

  public static function fromObject($obj) {
    $start = new self();

    $start->id                       = $obj->ID;
    $start->vliegtuig_id             = $obj->VLIEGTUIG_ID;
    $start->starttijd                = $obj->STARTTIJD;

    return $start;
  }
}