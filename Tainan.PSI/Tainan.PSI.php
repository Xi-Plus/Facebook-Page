<?php
ini_set("display_errors",1);
chdir(__DIR__);
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

$html=file_get_contents("http://taqm.epa.gov.tw/taqm/tw/PsiMap.aspx");
$html=str_replace(array("\n","\t"),"",$html);
$pattern="/jTitle='(.*?)' coords/";
preg_match_all($pattern,$html,$match);

foreach ($match[1] as $temp) {
	$data_temp=json_decode($temp);
	$data[$data_temp->SiteKey]=$data_temp;
}
$psilevelname=array(
	"PSI1" => "良好", 
	"PSI2" => "普通", 
	"PSI3" => "不良", 
	"PSI4" => "非常不良", 
	"PSI5" => "有害"
);
$pm25levelname=array(
	1 => "低", 
	2 => "低", 
	3 => "低", 
	4 => "中", 
	5 => "中", 
	6 => "中", 
	7 => "高", 
	8 => "高", 
	9 => "高", 
	10 => "非常高"
);
$followlist=array("Tainan","Annan","Shanhua");
$over=false;
$message=date("Y/m/d G")."時\n";
$log=file_get_contents("log.txt");
$log=json_decode($log,true);
function cmp($old,$new){
	if($new>$old)return "▲";
	else if($new<$old)return "▼";
	else return "●";
}
foreach ($followlist as $name) {
	if($data[$name]->PSI>=$config["PSI_over"]){
		$message.=$data[$name]->SiteName." PSI ".$data[$name]->PSI.cmp($log[$name]["PSI"],$data[$name]->PSI)." ".$psilevelname[$data[$name]->PSIStyle]."等級\n";
		$over=true;
	}
	$log[$name]["PSI"]=$data[$name]->PSI;
	if($data[$name]->FPMI>=$config["PM2.5_over"]){
		$message.=$data[$name]->SiteName." PM2.5 ".$data[$name]->PM25.cmp($log[$name]["PM25"],$data[$name]->PM25)." 第".$data[$name]->FPMI."級 分類".$pm25levelname[$data[$name]->FPMI]."\n";
		$over=true;
	}
	$log[$name]["PM25"]=$data[$name]->PM25;
}
file_put_contents("log.txt", json_encode($log));
echo $message."\n";
if($over){
	$params = array(
		"message"=>$message
	);
	$response=$fb->post("/".$config['page_id']."/feed",$params,$page_token)->getDecodedBody();
	var_dump($response);
}
?>
