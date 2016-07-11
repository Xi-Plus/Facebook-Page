<?php
ini_set("display_errors",1);
chdir(__DIR__);
if(PHP_SAPI!="cli"){
	echo "No permission.";
	exit;
}
require_once(__DIR__.'/../global_config.php');
require_once(__DIR__.'/config.php');
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

$html=file_get_contents("http://www.dgpa.gov.tw/");
if ($html===false) {
	exit("get fail");
}
$log=json_decode(file_get_contents("log.txt"), true);
$html=str_replace(array("\r\n","\n","\t"),"",$html);
$message="";
foreach ($config["city_list"] as $city) {
	echo $city;
	if (preg_match("/".$city."<\/FONT><\/TD>    <TD vAlign=center align=left width=\"70%\".*?>(.*?)<\/TD>/", $html, $match)) {
		$text=strip_tags($match[1]);
		echo $text;
		if ($log[$city]!=$text) {
			$log[$city]=$text;
			echo " changed!";
			$message.=$city." 更新為「".$text."」\n";
		}
	} else {
		$log[$city]="";
		echo " 無停班停課訊息";
	}
	echo "\n";
}
file_put_contents("log.txt", json_encode($log, JSON_UNESCAPED_UNICODE));
echo $message."\n";
if($message!=""){
	$params = array(
		"message"=>"資料來源：http://www.dgpa.gov.tw/\n\n".
			$message.
			"\n本粉專依據行政院人事行政總處網站發布的內容轉載，一切以來源為準"
	);
	$response=$fb->post("/".$config['page_id']."/feed",$params,$page_token)->getDecodedBody();
	var_dump($response);
}
?>
