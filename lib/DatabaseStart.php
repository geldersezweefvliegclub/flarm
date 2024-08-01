<?php

class DatabaseStart {
  public ?int $id;
  public int $veld_id;
  public int $vliegtuig_id;
  public ?string $reg_call;
  public ?string $starttijd;
  public ?string $landingstijd;

  public function __construct() {
    $this->id                       = null;
    $this->veld_id                  = -1;
    $this->vliegtuig_id             = -1;
    $this->reg_call                 = '';
    $this->starttijd                = '';
    $this->landingstijd             = '';
  }

  public static function fromObject($obj) {
    $start = new self();

    $start->id                       = $obj->ID;
    $start->veld_id                  = $obj->VELD_ID;
    $start->reg_call                 = $obj->REG_CALL;
    $start->vliegtuig_id             = $obj->VLIEGTUIG_ID;
    $start->starttijd                = $obj->STARTTIJD;
    $start->landingstijd             = $obj->LANDINGSTIJD;

    return $start;
  }
}