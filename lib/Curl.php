<?php
include_once 'include/config.php';

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
    curl_setopt($curl, CURLOPT_HEADER, true);      // curl response bevat header info
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

      $info = curl_getinfo($curl);
      curl_close($curl);
    }
    return json_decode($response);
  }

    public function exec_post(string $url, array $payload) : mixed {
        return self::exec_putpost($url, $payload, CURLOPT_POST);
    }


    public function exec_put(string $url, array $payload) : mixed {
        return self::exec_putpost($url, $payload, CURLOPT_PUT);
    }

    public function exec_putpost(string $url, array $payload, $type) : mixed {
        $response = false;
        $retries = 0;

        while (empty($this->auth_token) && ($retries < 1)) {
            $this->login();
            $retries++;
        }

        switch ($type)
        {
            case CURLOPT_POST : $http_method = "POST"; break;
            case CURLOPT_PUT : $http_method = "PUT"; break;
        }

        if (!empty($this->auth_token))
        {
            $headers = array($this->auth_token, 'Content-Type:application/json');
            $full_url = $this->base_url . $url;

            $curl = curl_init();

            $this->set_standard_options($curl, $full_url, $headers);
            curl_setopt( $curl, $type, true );
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $http_method);
            curl_setopt( $curl, CURLOPT_POSTFIELDS, json_encode((object)$payload) );
            $response = curl_exec( $curl );
            $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE); //get status code
            list($header, $body) = self::returnHeaderBody($curl, $response);

            curl_close($curl);
            return json_decode($body);
        }
        return false;
    }

    function returnHeaderBody($curl_session, $response)
    {
        // extract header
        $headerSize = curl_getinfo($curl_session, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $header = self::getHeaders($header);

        // extract body
        $body = substr($response, $headerSize);
        return [$header, $body];
    }

    function getHeaders($respHeaders)
    {
        $headers = array();
        $headerText = substr($respHeaders, 0, strpos($respHeaders, "\r\n\r\n"));

        foreach (explode("\r\n", $headerText) as $i => $line) {
            if ($i === 0) {
                $headers['http_code'] = $line;
            } else {
                list ($key, $value) = explode(': ', $line);

                $headers[$key] = $value;
            }
        }
        return $headers;
    }
}
