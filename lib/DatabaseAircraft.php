<?php

class DatabaseAircraft {
  public ?int $id;
  public string $registratie;
  public string $callsign;
  public ?int $zitplaatsen;
  public bool $clubkist;
  public string $flarmcode;
  public ?int $type_id;
  public bool $tmg;
  public bool $zelfstart;
  public bool $sleepkist;
  public ?int $volgorde;
  public bool $inzetbaar;
  public bool $trainer;
  public string $url;
  public ?int $bevoegdheid_lokaal_id;
  public ?int $bevoegdheid_overland_id;
  public string $opmerkingen;
  public bool $verwijderd;
  public ?DateTime $laatste_aanpassing;
  public string $reg_call;
  public string $vliegtuigtype;
  public string $bevoegdheid_lokaal;
  public string $bevoegdheid_overland;


  public function __construct() {
    $this->id                       = null;
    $this->registratie              = '';
    $this->callsign                = '';
    $this->zitplaatsen              = null;
    $this->clubkist                 = false;
    $this->flarmcode                = '';
    $this->type_id                  = null;
    $this->tmg                      = false;
    $this->zelfstart                = false;
    $this->sleepkist                = false;
    $this->volgorde                 = null;
    $this->inzetbaar                = false;
    $this->trainer                  = false;
    $this->url                      = '';
    $this->bevoegdheid_lokaal_id   = null;
    $this->bevoegdheid_overland_id = null;
    $this->opmerkingen              = '';
    $this->verwijderd              = false;
    $this->laatste_aanpassing       = null;
    $this->reg_call                 = '';
    $this->vliegtuigtype            = '';
    $this->bevoegdheid_lokaal       = '';
    $this->bevoegdheid_overland     = '';
  }

  public static function fromObject($obj) {
    $aircraft = new self();
    foreach ($obj as $key => $value) {
      $lower_case_key = strtolower( $key );
      if ( $lower_case_key === 'laatste_aanpassing' ) {
        $value = new DateTime( $value );
        if ( $value === false ) {
          $value = null;
        }
      }
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