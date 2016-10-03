<?php
session_start();
// set_time_limit(5);
header("Access-Control-Allow-Origin: *");
function checkServiceOpened(){
  // $host="themeblack.net" ;
  $host="127.0.0.1" ;
  $port=9998;
  $timeout=5;
  $sk=fsockopen($host,$port,$errnum,$errstr,$timeout) ;
  if (!is_resource($sk)) {
      return false;
  } else {
    fclose($sk);
    return true;
      }
}

require_once('../lib/database.php');
switch ($_POST['act']) {
  case 'getUserToken':
    $token = md5(time() . session_id());
    echo json_encode(array('token' => $token));
    break;
  case 'starService':
    if(!checkServiceOpened()){
      exec('php listerningClientRequest.php');
    }
  default:
    echo NULL;
    break;
}
if(isset($_GET['u']))
  print_r ($_SERVER);
?>