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

$html=file_get_contents("http://www.tnfsh.tn.edu.tw/files/501-1000-1012-1.php");
$start=strpos($html, "日期");
$html=substr($html, $start);
$html=str_replace(array("\n","\t"), "", $html);

$pattern='/<tr.*?<td.*?>(\d*?)-(\d*?)-(\d*?) <\/td><td.*?<a.*?href="(.*?)".*?>(.*?)<\/a>.*?<td.*?>(.*?)<\/td.*?tr>/';
preg_match_all($pattern, $html ,$match);
$query=new query;
$query->table="tnfshmessage";
$old=SELECT($query);
$list=array();
foreach ($old as $temp ){
	$list[]=$temp["text"];
}

$length=count($match[1]);
$count=0;
$postmessage="";
for ($i=0; $i < $length ; $i++) {
	$message=$match[2][$i]."/".$match[3][$i]." ".$match[6][$i]."：".$match[5][$i]." ".$match[4][$i];
	$checktext=$match[1][$i]."-".$match[2][$i]."-".$match[3][$i]."_".$match[5][$i]."_".$match[4][$i];
	echo $message."\n";
	if(!in_array($checktext, $list)){
		$count++;
		$postmessage.=$message."\n";
		echo "YES\n";
		$query=new query;
		$query->table="tnfshmessage";
		$query->value=array(
			array("text",$checktext),
			array("time",date("Y-m-d H:i:s")),
			array("token",md5(uniqid(rand(),true)))
		);
		INSERT($query);
	}else echo "NO\n";
}

echo $postmessage."\n";

if($count>0){
	$params = array(
		"message"=>$postmessage
	);
	$response=$fb->post("/".$config['page_id']."/feed",$params,$page_token)->getDecodedBody();
	var_dump($response);
}
?>
