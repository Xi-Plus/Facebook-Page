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
require_once($config['get_redirect_url_path']);

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

$html=file_get_contents("http://www.tngs.tn.edu.tw/tngs/board/");
$html=iconv("BIG5", "UTF-8//IGNORE", $html);
$html=html_entity_decode($html);
$start=strpos($html, "一般訊息");
$html=substr($html, $start);
$html=str_replace(array("\r\n","\t",'<img src=file.png border=0 width=20 alt="有附件">'), "", $html);

$pattern='/<a href=index\.asp\?chid=.*?>(.*?)<\/FONT><BR>.*?<\/a><font size=1 .*?>(.*?)<td.*?><font.*?><a href=(.*?)><font.*?>(.*?)<\/a>.*?<td.*?><font.*?>.*?<td.*?>(\d*?)年(\d*?)月(\d*?)日/';
preg_match_all($pattern, $html ,$match);

$query=new query;
$query->table="tngsmessage";
$old=SELECT($query);
$list=array();
foreach ($old as $temp ){
	$list[]=$temp["url"];
}

$length=count($match[1]);
$count=0;
$postmessage="";
for ($i=0; $i < $length ; $i++) {
	$message=$match[6][$i]."/".$match[7][$i]." ".$match[1][$i]." ".$match[2][$i]."：".$match[4][$i]."  ".get_redirect_url("http://www.tngs.tn.edu.tw".$match[3][$i])->url;
	echo $message."\n";
	if(!in_array($match[3][$i], $list)){
		$count++;
		$postmessage.="\n\n".$message;
		echo "YES\n";
		$query=new query;
		$query->table="tngsmessage";
		$query->value=array(
			array("url",$match[3][$i]),
			array("time",date("Y-m-d H:i:s")),
			array("token",md5(uniqid(rand(),true)))
		);
		INSERT($query);
	}else echo "NO\n";
}

$postmessage=date("Y/m/d H")."時  有 ".$count." 則新公告".$postmessage;
echo $postmessage."\n";

if($count>0){
	$params = array(
		"message"=>$postmessage
	);
	// $response=$fb->post("/".$config['page_id']."/feed",$params,$page_token)->getDecodedBody();
	// var_dump($response);
}
?>