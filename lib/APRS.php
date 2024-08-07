<?php

include 'lib/Debug.php';

class APRS {
  private string $call_sign;
  private int $passcode;
  private string $filter;

  private Socket $socket;
  private bool $connected;
  

  private Debug $debug;

  private bool $socket_error;

  private ?int $last_connection_attempt;
  private int $conn_delay;
  private string $server;
  private int $port;
  private string $version;



  public function __construct($server, $port, $call_sign, $passcode, $filter = '')
  {
    //for connecting to OGN
    $this->server = $server;
    $this->port = $port;
    $this->call_sign = $call_sign;
    $this->passcode = $passcode;
    $this->version = "1.0";
    $this->filter = ' filter ' . $filter;

    $this->connected = false;
    $this->socket_error = false;

    $this->last_connection_attempt = null;
    $this->conn_delay = 5;

    $this->debug = new Debug();
  }

  /**
   * @return bool
   */
  public function is_connected(): bool {
    return $this->connected;
  }



  private function set_socket_error($err_msg): void {
    $this->debug->echo( $err_msg . socket_strerror(socket_last_error()));
    $this->socket_error = true;
  }

  private function clear_socket_error(): void{
    $this->socket_error = false;
    socket_clear_error();
  }

  private function check_for_socket_read_data(): bool {
    $read[] = $this->socket;

    $w = null;
    $e = null;
    $res = socket_select($read, $w, $e,0);
    if( $res === false )
    {
      $this->set_socket_error("Select error: ");
      return false;
    }
    elseif($res===0){
      // no messages
      $this->debug->echo("no messages");
      return false;
    }

    return true;
  }

  private function read_socket_data(): bool|array {
    $res = socket_recv($this->socket,$buffer,8096,0);
    if( $res === false )
    {
      $this->set_socket_error("Receive error: ");
      return false;
    }
    elseif ($res === 0)
    {
      $this->disconnect();
      $this->debug->echo( "Read 0 after select");
      return true;
    }
    return $this->parse_buffer_into_array_of_single_data_strings($buffer);
  }

  public function run(): array {
    $this->keep_alive();
    $data_array = $this->io_loop();
    if (!is_array($data_array)) {
      $data_array = array();
    }
    return $data_array;

  }

  private function keep_alive(): void
  {
    if ($this->last_connection_attempt) {
      $elapsed_time = time() - $this->last_connection_attempt;
      if ($elapsed_time >= 180) {
        $this->send("#Keep alive");
        $this->debug->echo("Keep alive sent after {$elapsed_time}s");
        $this->last_connection_attempt = time();
      }
    } else {
      $this->last_connection_attempt = time();
    }
  }

  private function parse_buffer_into_array_of_single_data_strings(string $buffer): array {
    $array = explode(PHP_EOL, $buffer);
    if (str_ends_with($buffer, PHP_EOL)) {
      array_pop($array);
    }

    return $array;
  }

  private function io_loop(): bool|array {
    if ($this->connected === false)
    {
      $this->debug->echo("Connection closed, trying to re-connect...");
      if(!$this->connect())
      {
        $this->debug->echo("Re-connection attempt failed");
        return false;
      };
      $this->debug->echo("Reconnected!");
    }

    if ($this->check_for_socket_read_data())
    {
      $data = $this->read_socket_data();
      if ($data === false)
      {
        if ($this->socket_error)
        {
          $this->disconnect();
          return false;
        }
        else
          return $data;

      }
      else
          return $data;
    }
    else
    {
      if ($this->socket_error)
        $this->disconnect();

      return false;
    }
  }

  public function connect(): bool {
    $this->debug->echo("Trying to connect...");

    if ($this->connected) {
      $this->debug->echo("Already connected!");
      return false;
    }

    $conn_interval = time() - $this->last_connection_attempt;

    if( $conn_interval < $this->conn_delay){
      $this->debug->echo("Last connection attempt was $conn_interval seconds ago, waiting..");
      return false;
    }

    $this->socket = socket_create(AF_INET,SOCK_STREAM,getprotobyname("tcp"));

    if ($this->socket === false) {
      $this->set_socket_error("Failed to create a socket. ");
      return false;
    }

    $this->last_connection_attempt = time();
    $res=socket_connect($this->socket,$this->server,$this->port);

    if( !$res ){
      socket_close($this->socket);
      $this->set_socket_error("Connection failed: $this->server: $this->port : ");
      return false;
    }

    $this->connected = true;
    $this->send("user ".$this->call_sign." pass ".$this->passcode." vers gezc\\phpaprs ".$this->version . $this->filter."\n");

    return true;
  }

  private function send($data): bool|int {
    $res=socket_send($this->socket,$data,strlen($data),0);
    if($res<=0)
    {
      $this->debug->echo("socket send returned $res");
      $this->disconnect();
    }
    else
    {
      $this->debug->echo("sent ($res): $data");
    }
    return($res);
  }


  // marks the connection as disconnected
  public function disconnect(): void {
    socket_shutdown($this->socket);
    socket_close($this->socket);
    $this->connected = false;
    $this->clear_socket_error();
  }

}