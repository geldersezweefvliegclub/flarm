<?php
include_once 'include/config.php';
include_once 'include/constants.php';

class Curl {
  private string $base_url;
  private string $auth_token;

  public function __construct() {
    $this->base_url = SERVER_URL;
    $this->auth_token = '';
  }

  public function login() : void {
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $this->base_url . LOGIN_URL);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_USERPWD, SERVER_USER_NAME . ":" . SERVER_PASSWORD);

    $response = curl_exec($curl);

    if ($response !== false) {
      $obj = json_decode($response);
      if ($obj) {
        $this->auth_token = "Authorization: Bearer " . $obj->TOKEN;
      }
    }

    curl_close($curl);
  }

  private function set_standard_options(CurlHandle $curl, string $url, array $headers) {
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
  }

  public function exec_get(string $url) : mixed {
    $response = false;

    $retries = 0;

    while (empty($this->auth_token) && ($retries < 1)) {
      $this->login();
      $retries++;
    }

    if (!empty($this->auth_token)) {
      $headers = array($this->auth_token);
      $full_url = $this->base_url . $url;

      $curl = curl_init();

      $this->set_standard_options($curl, $full_url, $headers);
      $response = curl_exec( $curl );

      curl_close($curl);
    }
    return json_decode($response);
  }

  public function exec_post(string $url, array $post_payload) : mixed {
    $response = false;

    $retries = 0;

    while (empty($this->auth_token) && ($retries < 1)) {
      $this->login();
      $retries++;
    }

    if (!empty($this->auth_token)) {
      $headers = array($this->auth_token, 'Content-Type:application/json');
      $full_url = $this->base_url . $url;

      $curl = curl_init();

      $this->set_standard_options($curl, $full_url, $headers);
      curl_setopt( $curl, CURLOPT_POST, true );
      curl_setopt( $curl, CURLOPT_POSTFIELDS, json_encode($post_payload) );
      $response = curl_exec( $curl );

      curl_close($curl);
    }
    return json_decode($response);
  }
}