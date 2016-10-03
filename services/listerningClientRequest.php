<?php
require_once('../lib/config.php');
require_once('../lib/websockets.php');
require_once('../lib/database.php');
class listerner extends WebSocketServer {
  function log($msg){
  }
  //protected $maxBufferSize = 1048576; //1MB... overkill for an echo server, but potentially plausible for other applications.
  public function saveUserInfo($user, $userData){
    $this->users[$user->id]->type = $userData['type'];
    if($userData['type'] === 'admin'){
      $this->adminID[count($this->adminID)] = $user->id;
    }
    if($userData['type'] === 'client'){
      if(isset($this->tokenIdMap[$userData['token']])){
        $this->tokenIdMap[$userData['token']][count($this->tokenIdMap[$userData['token']])] = $user->id;
      }else{
        $this->tokenIdMap[$userData['token']] = array();
        $this->tokenIdMap[$userData['token']][count($this->tokenIdMap[$userData['token']])] = $user->id;
      }
    }
    if(isset($userData['location']) && isset($userData['location']['lat']) && isset($userData['location']['lng'])){
      $this->users[$user->id]->location = array(
        'lat' => $userData['location']['lat'],
        'lng' => $userData['location']['lng']
      );
    }
    $this->users[$user->id]->token = $userData['token']; 
    $this->users[$user->id]->email = $userData['email']; 
    $this->users[$user->id]->username = $userData['username']; 
  }
  public function saveBrowserInfo($user, $browserData){
    $this->users[$user->id]->browser = $browserData;
  }
  public function sendMessageToUser($userIDs, $message, $code){
    print_r($message);
    if(gettype($userIDs) == 'array'){
      if(count($userIDs) > 0){
        foreach($userIDs as $i=>$userID){
          if(isset($this->users[$userID])){
            $user = $this->users[$userID];
            $dataMsg = array(
              'code'  =>  $code,
              'message' =>  $message,
            );
            // json_decode (json_encode ($var), FALSE);
            $strMsg = json_encode(json_decode (json_encode ($dataMsg), FALSE));
            $this->send($user, $strMsg);
          }
        }
      }
    } else{
      if(isset($this->users[$userIDs])){
        $user = $this->users[$userIDs];
        $dataMsg = array(
          'code'  =>  $code,
          'message' =>  $message,
        );
        // json_decode (json_encode ($var), FALSE);
        $strMsg = json_encode(json_decode (json_encode ($dataMsg), FALSE));
        $this->send($user, $strMsg);
      }
    }
    
  }
  public function sendAdminUserInfo($id){
    $user  = $this->users[$id];
    foreach ($this->adminID as $index => $id) {
      $this->sendMessageToUser($id, $user->toArray(), 101);
    }
  }
  public function sendLocationToAdmin($location){
    foreach ($this->adminID as $index => $id) {
      $this->sendMessageToUser($id, $location, 105);
    }
  }
  public function sendTypingLogToAdmin($userData){
    foreach ($this->adminID as $index => $id) {
      $this->sendMessageToUser($id, $userData, 106);
    }
  }
  public function sendMessageToAdmin($msg){
    foreach ($this->adminID as $index => $id) {
      $this->sendMessageToUser($id, $msg, 200);
    }
  }
  public function sendAdminListUserInfo($adminID){
    //list user in db
    $model = new model($this->config);
    $clientArray = $model->getListUser();

    $dbUsers = array();
    foreach ($clientArray as $index=>$user) {
      $dbUsers[$index] = $user['token'];
    }
    foreach ($this->tokenIdMap as $token=>$IdArray) {
      if(count($IdArray) > 0){
        // echo "0\r\n";
        $id = $IdArray[0];
        $user = $this->users[$id];
        $serviceUserArray = $user->toArray();
        
        if($user->type === 'client'){
          $inDbUser = array_search($token, $dbUsers);
          // echo $inDbUser;
          if(!$inDbUser){
            array_push($clientArray, $serviceUserArray);
          }else{
            // echo "3\r\n";
            // echo "exist \r\n";
            $clientArray[$inDbUser]['status'] = 'on';
            if(intval($clientArray[$inDbUser]['updateAt']) < $user->updateAt){
              $clientArray[$inDbUser]['messages'] = array_merge($clientArray[$inDbUser]['messages'], $serviceUserArray['messages']);
            }
          }
        }
      }
    }


    // sort
    for($i = 0; $i <= count($clientArray) - 2; $i++){
      for($j = $i + 1; $j <= count($clientArray) - 1; $j++){
        // echo intval($clientArray[$i]['updateAt']) - intval($clientArray[$j]['updateAt']);
        if(intval($clientArray[$i]['updateAt']) <  intval($clientArray[$j]['updateAt'])){
          // echo "swap";
          $temp = $clientArray[$i];
          $clientArray[$i] = $clientArray[$j];
          $clientArray[$j] = $temp;
        }
      }
    }

    // echo "\r\n";
    // foreach($clientArray as $i=>$v){
    //   echo($v['updateAt']);
    // }

    
    $this->sendMessageToUser(array(0=>$adminID), $clientArray, 100);
  }
  public function requestAdminOffUser($user){
    foreach ($this->adminID as $index => $id) {
      $this->sendMessageToUser($id, $user->token, 102);
    }
  }
  public function requestAdminLogUser($user){
    foreach ($this->adminID as $index => $id) {
      $this->sendMessageToUser($id, $user->token, 103);
    }
  }
  public function syncMessageToClient($message, $user){
    $userIDs = $this->tokenIdMap[$user->token];
    foreach($userIDs as $index=>$uId){
      $u = $this->users[$uId];
      $u->addMessage($message);
      if($u->id != $user->id){
        $this->sendClientSyncMessage($message, $u);
      }
    }
  }
  public function syncMessageToAdmin($message, $user){
    foreach($this->adminID as $index=>$uId){
      $u = $this->users[$uId];
      if($u->id != $user->id){
        $this->sendMessageToUser($u->id, $message, 104);
      }
    }
  }
  public function sendClientSyncMessage($message, $user){
    $this->sendMessageToUser($user->id, $message->content, 104);
  }
  protected function process ($user, $message) {
    // parse message
    $data = json_decode($message, true);
    if($data){
      if(isset($data['code'])){
        switch ($data['code']) {
          //setting...
          case 100: // first time...
            $this->saveUserInfo($user, $data['user']);
            if($data['user']['type'] === 'client'){ //add user
              $this->saveBrowserInfo($user, $data['browser']); 
              $this->sendAdminUserInfo($user->id);
            }
            if($data['user']['type'] === 'admin'){
              $this->sendAdminListUserInfo($user->id);
            }

            break;
          case 101: // log typing
            $this->sendTypingLogToAdmin(array(
              'token' =>$user->token
            )); 
            break;
          case 102: // log typing
            // Send admin latlong user
            if( isset($data['location']) && isset($data['location']['lat']) && isset($data['location']['lng']) ){
              $this->sendLocationToAdmin(array(
                'token' =>$user->token,
                'lat' => $data['location']['lat'],
                'lng' => $data['location']['lng']
              )); 
              $user->location = array(
                'lat' => $data['location']['lat'],
                'lng' => $data['location']['lng']
                );
            }
            break;

            // ------------------------------------------------
          case 200://client to admin
            // Find admin
            $_msg = new Message($data['message'], 'client', $user->token);
            $this->sendMessageToAdmin($_msg);
            $this->syncMessageToClient($_msg, $user);
            // $user->addMessage($_msg);
            break;


            // ------------------------------------------------
          case 300://admin to client
            $userIDs = $this->tokenIdMap[$data['token']];
            $this->sendMessageToUser($userIDs, $data['message'], 301);
            // $this->sendMessageToUser($user->id, $this->tokenIdMap[$data['token']], 301);
            $_msg = new Message($data['message'], 'admin', $data['token']);
            foreach($userIDs as $index=>$userID){
              $this->users[$userID]->addMessage($_msg);
            }

            $this->syncMessageToAdmin($_msg, $user);

            break;

          case 1000://handle service from console
            die;
            break;

            // ------------------------------------------------
        }
      }
    }

  }
  
  protected function connected ($user) {
    console.log(1);
  }
  public function unsetUser($user){
    // unset ref user array
    if($user->type == 'client'){
      if(isset($this->tokenIdMap[$user->token])){
        $offset = array_search($user->id, $this->tokenIdMap[$user->token]);
        unset($this->tokenIdMap[$user->token][$offset]);
      }
    }else{
      $offset = array_search($user->id, $this->adminID);
      unset($this->adminID[$offset]);
    }
    
  }
  protected function closed ($user) {
    $this->stdout("User closed");
    if(isset($this->tokenIdMap[$user->token]) && count($this->tokenIdMap[$user->token]) == 1){
      $this->requestAdminOffUser($user);
      if(count($user->messages) == 0){
        $this->stdout("User Off");
      }else{ 
        $model = new model($this->config);
        $model->saveUser($user);
        // $this->requestAdminLogUser($user);
      }
    }
    $this->unsetUser($user);
  }
}

$listerner = new listerner($config['ip'],$config['port']);
$listerner->config = $config;
$listerner->run();
function loopInit($listerner){
  try {
    $listerner->run();
  }
  catch (Exception $e) {
    $listerner->stdout($e->getMessage());
    loopInit($listerner);
  } 
}

if($config['autorestart']){
  loopInit($listerner);
}else{
  $listerner->run();
}
