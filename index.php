<?php
header('Content-Type:text/html;Charset=UTF-8');
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$redis->auth("aliddns-api");
   
//获取客户端真实ip地址
function get_real_ip(){
  static $realip;
  if(isset($_SERVER)){
    if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
      $realip=$_SERVER['HTTP_X_FORWARDED_FOR'];
    }else if(isset($_SERVER['HTTP_CLIENT_IP'])){
      $realip=$_SERVER['HTTP_CLIENT_IP'];
    }else{
      $realip=$_SERVER['REMOTE_ADDR'];
    }
  }else{
    if(getenv('HTTP_X_FORWARDED_FOR')){
      $realip=getenv('HTTP_X_FORWARDED_FOR');
    }else if(getenv('HTTP_CLIENT_IP')){
      $realip=getenv('HTTP_CLIENT_IP');
    }else{
      $realip=getenv('REMOTE_ADDR');
    }
  }
  return $realip;
}
    
$key=get_real_ip();
//限制次数为6 冗余2 也就是10秒一次
$limit = 8;
$check = $redis->exists($key);
if($check){
  $redis->incr($key);
  $count = $redis->get($key);
  if($count > 8){
    exit('请求太频繁，请稍后再试！');
  }
}else{
  $redis->incr($key);
  //限制时间为60秒
  $redis->expire($key,60);
}
$count = $redis->get($key);



//API开始
date_default_timezone_set('UTC'); //Aliyun API要求时间


//php 正则只保留 汉字 字母 数字 防止XSS
function match_safe($chars,$encoding='utf8')
{
 $pattern =($encoding=='utf8')?'/[\x{4e00}-\x{9fa5}a-zA-Z0-9.]/u':'/[\x80-\xFF]/';
 preg_match_all($pattern,$chars,$result);
 $temp =join('',$result[0]);
 return $temp;
}

$srvkey = "a7cc8678193ee9"; //通讯密钥

$domain = match_safe($_REQUEST["domain"]);  //根域名如 vas.ink(不包含www)
$type = match_safe($_REQUEST["type"]);  //记录类型(大写)如 A、NS、CNAME、AAAA
$sub = match_safe($_REQUEST["sub"]);  //主机记录 用于DDNS的子域名 如dsm 最终域名就是dsm.vas.ink
$AccessKeyId = match_safe($_REQUEST["keyid"]);  //阿里云域名权限的AccessKeyId
$AccessKeySecret = match_safe($_REQUEST["keysecret"]);  //阿里云域名权限的AccessKeySecret
$sign = match_safe($_REQUEST["sign"]);   //签名，计算方式为 md5(keyid+keysecret+domain+通讯密钥)
$custom = match_safe($_REQUEST["custom"]);  //1使用请求发起客户端的IP该选项type只能A或者AAAA 2自定义IP或地址
$address = match_safe($_REQUEST["address"]);  //当custom=2 自定义IP或地址时 需要传，如果为custom=1 则不需要这个参数

/*
* 传参校验
*/
if ($domain == null){
	echo "根域名为空，请注意检查哦~";
	exit();
}
if ($type == null){
	echo "记录类型(大写)如 A、NS、CNAME、AAAA...为空，请注意检查哦~";
	exit();
}
if ($sub == null){
	echo "主机记录为空，请注意检查哦~";
	exit();
}
if ($AccessKeyId == null){
	echo "阿里云域名权限的AccessKeyId为空，请注意检查哦~";
	exit();
}
if ($AccessKeySecret == null){
	echo "阿里云域名权限的AccessKeySecret为空，请注意检查哦~";
	exit();
}
if ($sign == null){
	echo "签名为空，请注意检查哦~";
	exit();
}
if ($custom == null){
	echo "custom为空，请注意检查哦~";
	exit();
}
if ($custom != '1' && $custom != '2'){
	echo "custom参数不正确哦，1使用请求发起客户端的IP该选项type只能A或者AAAA 2自定义IP或地址，请注意检查哦~";
	exit();
}

if ($custom == '2'){
   if ($address == null){
	   echo "custom=2 的时候 address为必须传，请注意检查哦~";
	   exit();
       }
}

if ($custom == '1'){
    $address = get_real_ip();
}

/*
* 验签 所有后续步骤需要验签通过才能继续 
*/
$srvsign=md5($AccessKeyId.$AccessKeySecret.$domain.$srvkey);
if($srvsign != $sign){
	echo "签名验证失败，请注意检查哦";
	exit();
}

//引入Aliyun 域名API
include_once 'alicloud-php-updaterecord/V20150109/AlicloudUpdateRecord.php';
use Roura\Alicloud\V20150109\AlicloudUpdateRecord;

//填写KeyId和KeySecret
$updater = new AlicloudUpdateRecord($AccessKeyId,$AccessKeySecret);

$updater->setDomainName($domain);
$updater->setRecordType($type);
$updater->setRR($sub);
$updater->setValue($address);

print_r($updater->sendRequest());



