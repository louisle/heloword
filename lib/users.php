<?php
class Message {
  public $id;
  public $type = 'client';
  public $time;
  public $content;
  public $clientToken;
  public $browser;
  function __construct($content, $type, $clientToken){
    $this->time = time();
    $this->type = $type;
    $this->content = $content;
    $this->clientToken = $clientToken;
  }
  public function toArray(){
    return array(
      'type'          =>  $this->type,
      'time'          =>  $this->time,
      'content'       =>  $this->content,
      'clientToken'   =>  $this->clientToken
    );
  }
}
class WebSocketUser {
  public $createAt;
  public $updateAt;
  public $socket;
  public $id;
  public $headers = array();
  public $handshake = false;

  public $handlingPartialPacket = false;
  public $partialBuffer = "";

  public $sendingContinuous = false;
  public $partialMessage = "";
  
  public $hasSentClose = false;

  // Add properties for user
  public $type = 'client'; // admin, client
  public $token = '';
  public $email = '';
  public $username = '';
  public $messages = array();
  public $status = 'on';
  public $browser;
  public $location;
  function __construct($id, $socket) {
    $this->id = $id;
    $this->socket = $socket;
    $this->createAt = $this->updateAt = time();
  }

  public function stdout($message) {
    echo "$message\n";
  }

  public function toArray(){
    $_messages = array();
    foreach($this->messages as $index=>$message) {
      array_push($_messages, $message->toArray());
    }
    return array(
      'username'    =>  $this->username,
      'createAt'    =>  $this->createAt,
      'updateAt'    =>  $this->updateAt,
      'email'    =>  $this->email,
      'token'       =>  $this->token,
      'type'       =>  $this->type,
      'status'   =>   $this->status,
      'messages'   =>   $_messages,
      'browser'       =>  $this->browser,
      'location'       =>  $this->location
    );
  }
  public function addMessage($message){
    $this->messages[count($this->messages)] = $message;
    $this->updateAt = time();
    $this->stdout("saved: '$message->content'");
  }
}