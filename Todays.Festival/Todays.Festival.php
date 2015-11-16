<?php
ini_set("display_errors",1);
if(PHP_SAPI!="cli"){
	echo "No permission.";
	exit;
}
require_once(__DIR__.'/../global_config.php');
require_once(__DIR__.'/config.php');
require_once($config['sql_path']);
require_once($config['facebook_sdk_path']);

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
$month=date("n");
$date=date("j");

$message=$month."月".$date."日的節假日和習俗有\n";

$html=file_get_contents("https://zh.wikipedia.org/zh-tw/".$month."月".$date."日");
$html=html_entity_decode($html);
$html=str_replace(array("\t","\n"),"",$html);
$start=strpos($html,"節假日和習俗");
$html=substr($html, $start);
$pattern='/<h2>.*?節假日和習俗.*?<\/h2><ul>(.*?)<\/ul>/';
if(!preg_match($pattern, $html ,$match))exit("No festival.\n");
$html=$match[1];
$pattern='/<li>(.*?)<\/li>/';
preg_match_all($pattern, $html ,$match);
foreach($match[1] as $temp){
	$temp=strip_tags($temp);
	$temp=preg_replace("/\[.*?\]/", "", $temp);
	$message.="\n".$temp;
}
$message.="\n\nhttps://zh.wikipedia.org/zh-tw/".$month."月".$date."日#.E8.8A.82.E5.81.87.E6.97.A5.E5.92.8C.E4.B9.A0.E4.BF.97";
echo $message."\n";
$params = array(
	"message"=>$message
);
$response=$fb->post("/".$config['page_id']."/feed",$params,$page_token)->getDecodedBody();
var_dump($response);
?>
