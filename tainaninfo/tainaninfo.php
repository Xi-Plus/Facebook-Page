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

$message=date("Y/m/d")."\n";

//氣象
$html=file_get_contents("http://www.cwb.gov.tw/V7/forecast/taiwan/Tainan_City.htm");
$start=strpos($html, "今明預報");
$end=strpos($html, "1週曲線圖");
$html=substr($html, $start ,$end-$start);
$html=str_replace(array("\t","\n"),"",$html);
$pattern='/row">(.*?) (\d*)\/(\d*) (\d*):(\d*)~(\d*)\/(\d*) (\d*):(\d*).*?>(\d*) ~ (\d*)<.*?title="(.*?)"><\/td><td>(.*?)<.*?>(\d*?) %/';
preg_match_all($pattern, $html ,$match);
$message.="氣象預報 來自 http://cwb.gov.tw\n";
for($i=0;$i<3;$i++){
	$message.=$match[1][$i]." ".$match[3][$i]."日".$match[4][$i]."時~".$match[7][$i]."日".$match[8][$i]."時 ".$match[10][$i]."~".$match[11][$i]."℃ ".$match[12][$i]." ".$match[13][$i]." 降雨機率".$match[14][$i]."％\n";
}

$message.="\n";

//水庫
$message.="水庫蓄水量 來自 http://www.wra.gov.tw\n";
$pattern="/依據<font color='red'>(\d*)-(\d*)-(\d*) (\d*):(\d*)<\/font>所獲資料，大壩水位為<font color='red'>(\d*\.\d*)<\/font>公尺，距離滿水位尚有(\d*\.\d*)公尺，其有效蓄水量為(\d*(?:\.\d*)*)萬立方公尺，佔水庫有效蓄水容量<font color='red'>(\d*\.\d*)%<\/font>/";
$pattern2="/依據<font color='red'>(\d*)-(\d*)-(\d*) (\d*):(\d*)<\/font>所獲資料，大壩水位為<font color='red'>(.*?)<\/font>公尺，已達滿水位，其有效蓄水量為(.*?)萬立方公尺，佔水庫有效蓄水容量<font color='red'>(.*?)%/";

$water=array(
	array("name"=>"烏山頭水庫","url"=>"http://www.wra.gov.tw/ct.asp?xItem=47852&CtNode=7221"),
	array("name"=>"曾文水庫","url"=>"http://www.wra.gov.tw/ct.asp?xItem=47852&CtNode=7221"),
	array("name"=>"南化水庫","url"=>"http://www.wra.gov.tw/ct.asp?xItem=19995&CtNode=4541")
);
foreach ($water as $temp) {
	$html=file_get_contents($temp["url"]);
	if(preg_match($pattern, $html ,$match)){
		$message.=$temp["name"]." ".$match[3]."日".$match[4]."時 蓄水量 ".$match[9]."％\n";
	}
	else if(preg_match($pattern2, $html ,$match)){
		$message.=$temp["name"]." ".$match[3]."日".$match[4]."時 蓄水量 ".$match[8]."％\n";
	}else {
		$message.=$temp["name"]." 無法取得資料\n";
	}
}

$message.="\n";

//地震
$html=file_get_contents("http://www.cwb.gov.tw/V7/modules/MOD_EC_Home.htm");
$html=str_replace(array("\n","\t",'<img src="/V7/images/icon/new.gif" alt="New" border="0" align="absmiddle" />'),"",$html);
$pattern='/<td nowrap="nowrap">(.*?)<\/td><td nowrap="nowrap">(\d*)\/(\d*) (\d*):(\d*)<\/td><td style="display:none">(\d*\.\d*)<\/td><td style="display:none">(\d*\.\d*)<\/td><td>(\d*\.\d*)<\/td><td>.*?(\d*\.\d*)<\/td><td>(.*?)  (\d*\.\d*)  公里 <br>\(位於(.*?)\).<\/td><td style="display:none">(.*?)<\/td>/';
preg_match_all($pattern, $html ,$match);

$query=new query;
$query->table="earthquake";
$old=SELECT($query);
$list=array();
foreach ($old as $temp ){
	$list[]=$temp["url"];
}

$message.="地震報告 來自 http://cwb.gov.tw\n";
$length=count($match[1]);
$count=0;
for ($i=0; $i < $length; $i++) {
	if(!in_array($match[13][$i], $list)){
		$html=file_get_contents("http://www.cwb.gov.tw/V7/earthquake/Data/".($match[1][$i]=="小區域"?"local/":"quake/").$match[13][$i]);
		if(strpos($html, "臺南")!==false){
			$count++;
			$message.=$match[1][$i]." ".$match[2][$i]."/".$match[3][$i]." ".$match[4][$i].":".$match[5][$i]." 規模".$match[8][$i]." 深度".$match[9][$i]."km ".$match[12][$i]." ".$match[6][$i]."°N ".$match[7][$i]."°E\n連結: http://www.cwb.gov.tw/V7/earthquake/Data/".($match[1][$i]=="小區域"?"local/":"quake/").$match[13][$i]."\n";
			$query=new query;
			$query->table="earthquake";
			$query->value=array(
				array("url",$match[13][$i]),
				array("time",date("Y-m-d H:i:s")),
				array("token",md5(uniqid(rand(),true)))
			);
			INSERT($query);
		}
	}
}
if($count==0)$message.="沒有新的報告\n";

echo $message."\n";

$params = array(
	"message"=>$message
);
$response=$fb->post("/".$config['page_id']."/feed",$params,$page_token)->getDecodedBody();
var_dump($response);
?>
