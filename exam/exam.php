<?php
if(PHP_SAPI!="cli"){
	echo "No permission.";
	exit;
}
require_once(__DIR__.'/../global_config.php');
require_once(__DIR__.'/config.php');
require_once($config['sql_path']);
require_once($config['facebook_sdk_path']);
date_default_timezone_set("Asia/Taipei");

$fb = new Facebook\Facebook([
	'app_id' => $config['app_id'],
	'app_secret' => $config['app_secret'],
	'default_access_token' => $config['access_token'],
	'default_graph_version' => 'v2.5',
]);
$response = $fb->get('/me/accounts')->getDecodedBody();
foreach($response["data"] as $temp){
	if($temp["id"]==$config['page_id']){
		$page_token=$temp["access_token"];
		break;
	}
}

$message="";
foreach($config['exam_list'] as $temp){
	$today = mktime(0,0,0);
	$tdehu = mktime(0,0,0,$temp["month"],$temp["date"],$temp["year"]);
	$diff = ($tdehu - $today) / 86400;
	if($diff>0){
		$temp["text"]=preg_replace("/\\\D/", $diff, $temp["text"]);
		$temp["text"]=preg_replace("/\\\W/", floor($diff/7), $temp["text"]);
		$message.=$temp["text"]."\n";
	}
}
$message.="\n".date("Y/m/d");
echo $message."\n";

$params = array(
	"message"=>$message
);
$response=$fb->post("/".$config['page_id']."/feed",$params,$page_token)->getDecodedBody();
var_dump($response);
?>