<?php

class APRS {
  private $callsign;
  private $passcode;
  private $filter;

  private $socket;
  private $_connected;	// true or false
  private $_conn_delay;	// connection try delays
  private $_lastconn_attempt;	// last connection attempt

  private $_timeout;

  private $_inputdat;
  private $_inputlen;

  private $_outbuffer;

  public bool $_debug;

  private $_version = '1.0';

  private $_maxtransmit;
  private $server;
  private $port;
  private $callbacks;

  public function __construct($host, $port, $callsign, $passcode, $filter = '')
  {
    $this->server = $host;
    $this->port = $port;
    $this->callsign = $callsign;
    $this->passcode = $passcode;
    $this->filter = $filter;

    $this->_timeout = 5;
    $this->_maxtransmit = 5;	// transmit a maximum of 5 times
    $this->_lastconn_attempt=1;
    $this->_conn_delay=5;

  }

  public function ioloop(): bool {  //look into do i need the socket select stuff???
    if(!$this->_connected) {
      $this->debug("Connection closed, trying to re-connect...");
      if(!$this->connect()) {
        $this->debug("Re-connection attempt failed");
        return false;
      };
      $this->debug("Reconnected!");
    }

    $read[] = $this->socket;
    $this->debug("before select");

    $w = null;
    $e = null;
    $res = socket_select($read, $w, $e,0);
    if( $res === false )
    {
      $this->debug( "select error: " . $this->socket_error_string());
      return false;
    }
    elseif($res===0){
      // no messages
      $this->debug("no messages");
      return false;
    }

    $res = socket_recv($this->socket,$buf,8096,0);
    if( $res === false ) {
      $this->debug( "Receive error: " . $this->socket_error_string());
      return false;
    }
    elseif($res===0){
      $this->_disconnect();
      $this->debug( "Read 0 after select");
      return true;
    }

    $this->debug("Buffer: " . $buf);

    return true;
  }

  public function connect(): bool {
    if ($this->_debug)
    {
      echo "trying to connect...";
    }

    if( $this->_connected ){
      $this->debug("Already connected!");
      return false;
    }

    $conn_interval = time() - $this->_lastconn_attempt;

    if( $conn_interval < $this->_conn_delay){
      $this->debug("Last connection attempt was $conn_interval seconds ago, waiting..");
      return false;
    }

    $this->socket = socket_create(AF_INET,SOCK_STREAM,getprotobyname("tcp"));

    if ($this->socket === false) {
      $this->debug("Failed to create a socket. " . $this->socket_error_string());
      return false;
    }

    $this->_lastconn_attempt = time();
    $res=socket_connect($this->socket,$this->server,$this->port);

    if( !$res ){
      socket_close($this->socket);
      $this->debug( "Connection failed: $this->server: $this->port : " . $this->socket_error_string());
      return false;
    }

    $this->_connected = true;
    if (!empty($this->filter)) {
      $this->filter = ' filter ' . $this->filter;
    }
    $this->_send("user ".$this->callsign." pass ".$this->passcode." vers gezc\\phpaprs ".$this->_version . $this->filter."\n");

    return true;
  }

  private function keep_alive(): void {
    $this->_send("#Keep alive");
  }

  private function _send($data): bool|int {
    $res=socket_send($this->socket,$data,strlen($data),0);
    if($res<=0)
    {
      $this->debug("socket send returned $res");
      $this->_disconnect();
    }
    else
    {
      $this->debug("sent ($res): $data");
    }
    return($res);
  }


  // marks the connection as disconnected
  private function _disconnect(): void {
    socket_shutdown($this->socket,2);
    socket_close($this->socket);
    $this->_connected = false;
  }

  private function debug($str): void {
    if($this->_debug === true) {
      echo "debug: $str\n";
    }
  }

  private function socket_error_string(): string {
    return socket_strerror(socket_last_error());
  }
}