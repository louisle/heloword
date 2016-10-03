<?php
require_once('users.php');
class model{
  public $config;
  public $mysqli;
  function __construct($config){
    $this->config = $config;
  }
  private function create_connect(){
    $mysqli = new mysqli($this->config['host'], $this->config['username'], $this->config['password'], $this->config['database']);
    if ($mysqli->connect_errno) {
      echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
      return false;
    }
    return $mysqli;
  }
  private function destroy_connect(){
    mysqli_close ($this->mysqli);
  }

  public function getListUser(){
    $this->mysqli = $this->create_connect();
    $res = $this->mysqli->query("select * from user");
    $users = array();
    while ($user = mysqli_fetch_assoc($res)) {
      $userID = $user['userID'];
      $mRes = $this->mysqli->query("select * from message where userID='$userID'");
      $user['token'] = $user['userID'];
      $user['status'] = 'off';
      $user['messages'] = array();
      while ($msg = mysqli_fetch_assoc($mRes)) {
        $msg['clientToken'] = $msg['userID'];
        array_push($user['messages'], $msg);
      }
      array_push($users, $user);
    }
    $this->destroy_connect();
    return $users;
  }
  public function findUser($userID){
    if($this->mysqli){
      $queryStr = "SELECT * FROM user WHERE userID = '$userID'";
      $res = $this->mysqli->query($queryStr);
      while ($row = mysqli_fetch_assoc($res)) {
        return $row;
      }
      return false;
    }
  }
  public function addUser($user){
   if($this->mysqli){
      $userID = mysqli_real_escape_string($this->mysqli, $user->token);
      $username = mysqli_real_escape_string($this->mysqli, $user->username);
      $email = mysqli_real_escape_string($this->mysqli, $user->email);
      $createAt = $user->createAt;
      $updateAt = $user->updateAt;
      $queryStr = "INSERT INTO user(userID, username, email, createAt, updateAt) VALUES ('$userID', '$username', '$email', $createAt, $updateAt)";
      if($this->mysqli->query($queryStr)){
        return $userID;
      }
      return false;
    } 
  }
  public function updateLastUserActive($user){
   if($this->mysqli){
      $updateAt = $user->updateAt;
      $userID = mysqli_real_escape_string($this->mysqli, $user->token);
      $queryStr = "UPDATE user SET updateAt = $updateAt WHERE userID = '$userID'";
      $this->mysqli->query($queryStr);
    } 
  }
  public function saveUser($user){
    $this->mysqli = $this->create_connect();
    if($this->mysqli){
      $userID = mysqli_real_escape_string($this->mysqli, $user->token);
      $username = mysqli_real_escape_string($this->mysqli, $user->username);
      $email = mysqli_real_escape_string($this->mysqli, $user->email);
      $_user = $this->findUser($userID);
      if(!$_user){
        $this->addUser($user);
      } else{
        $this->updateLastUserActive($user);
      }
      foreach($user->messages as $i=>$msg){
        $type = mysqli_real_escape_string($this->mysqli, $msg->type);
        $content = mysqli_real_escape_string($this->mysqli, $msg->content);
        $time = mysqli_real_escape_string($this->mysqli, $msg->time);
        $queryStr = "INSERT INTO message(userID, type, content, time) VALUES ('$userID', '$type', '$content', '$time')";
        $this->mysqli->query($queryStr);
      }
      $this->destroy_connect();
    }
  }


}
?>