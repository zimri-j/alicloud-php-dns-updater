# AliyunDDNS API

>AliDDNS API 可直接通过阿里云AccessKeyId和AccessKeySecret 直接操作域名解析，从而达到了DDNS动态域名解析效果，无需自建PHP服务器端。在路由器不支持AliDDNS或者效率低的情况下，使用本站AliDDNS API能得更高性能的解析速度。
>
> 需满足条件：
>1. 域名购买在Aliyun阿里云且开启AccessKeyId和AccessKeySecret并正确授权域名操作的所有权限，AccessKeyId和AccessKeySecret权限不要多给只需要域名操作全部权限即可
>2. 因该API没有权限新建域名解析，只能更改已存在的域名解析，所以需要预先新建好要作为DDNS的域名 如dsm.vas.ink A记录指向任意IP 如1.1.1.1 

## 部署要求：
WebServer：php71-74
Redis：可选;限制请求

## 请求地址：
下载项目后放置自定目录如/api/AliyunDDNS/ 则请求地址为：
```
https://youserverdomain/api/AliyunDDNS/
```
## 直接可用请求地址
```
https://www.vas.ink/api/AliyunDDNS/
```
请求方式：POST/GET 
请求限制(需Redis支持)：每分钟最大6次请求，也就是10秒一次，如果超出请求数会被限制60秒

>通讯密钥（示例）：a7cc8678193ee9
>校验签名（示例）：md5(keyid+keysecret+domain+通讯密钥) 
>
>参数示例（别名示例）：https://youserverdomain/api/AliyunDDNS/?domain=vas.ink&type=CNAME&sub=dsm&keyid=0VX3YaSnBW72yFPR&keysecret=upqZxR3qoaLLpPvKqW5nFUdfG3YYbo9C&sign=6b9260b1e02041a665d4e4a5117cfe16&custom=2&address=test.vas.ink

参数说明:

| 参数 | 参数类型 | 参数说明 |
|----|------|------|
|domain | 字符串  |根域名如 vas.ink(不包含www)|
|type | 字符串 |记录类型大写如 A、NS、CNAME、AAAA|
|sub | 字符串 |主机记录 用于DDNS的子域名 如dsm 最终域名就是dsm.vas.ink |
|keyid | 字符串 |阿里云域名权限的AccessKeyId |
|keysecret | 字符串 |阿里云域名权限的AccessKeySecret |
|sign | 字符串 |签名，计算方式为 md5(keyid+keysecret+domain+通讯密钥)|
|custom | 整数 |1使用请求发起客户端的IP该选项type只能A或者AAAA  2自定义IP或地址|
|address | 字符串 |当custom=2 自定义IP或地址时 需要传，如果为custom=1 则不需要这个参数|

注意：使用该API 需要有公网IP 城域IP或者内网IP的需要使用内网穿透

传参选项 custom=1 时 解析的IP为发起API请求的设备公网IP
当传参选项 custom=2时 需要增加一项传解析的IP地址。
以便适应各种环境，如：RouterOS、群晖DSM、各种有DDNS需求的NAS或者设备。



## 参数示例

### 场景1：群晖DSM有动态公网IP，由群晖计划任务发起一个curl请求来解析群晖所在的公网IP地址

>API参数示例：https://youserverdomain/api/AliyunDDNS/?domain=vas.ink&type=A&sub=dsm&keyid=0VX3YaSnBW72yFPR&keysecret=upqZxR3qoaLLpPvKqW5nFUdfG3YYbo9C&sign=6b9260b1e02041a665d4e4a5117cfe16&custom=1

群晖计划任务，每分钟中执行一次 注意culr &符号需要转义
```
curl https://youserverdomain/api/AliyunDDNS/\?domain=vas.ink\&type=A\&sub=dsm\&keyid=0VX3YaSnBW72yFPR\&keysecret=upqZxR3qoaLLpPvKqW5nFUdfG3YYbo9C\&sign=6b9260b1e02041a665d4e4a5117cfe16\&custom=1
```

### 场景2：RouterOS有PCC均衡负载 有多个公网IP

注意：多个IP 则需要对应多个子域名，一个IP对应一个域名。该方法原理为预先制作好API链接，解析类形`custom=2` 使用自定义地址，RouterOS获取WANIP 赋值变量`$ipadd`，对应到自定义地址`address=$ipadd`参数上

>IP1-API参数示例：https://youserverdomain/api/AliyunDDNS/?domain=vas.ink&type=A&sub=dsm1&keyid=0VX3YaSnBW72yFPR&keysecret=upqZxR3qoaLLpPvKqW5nFUdfG3YYbo9C&sign=6b9260b1e02041a665d4e4a5117cfe16&custom=2&address=1.1.1.1


>IP2-API参数示例：https://youserverdomain/api/AliyunDDNS/?domain=vas.ink&type=A&sub=dsm2&keyid=0VX3YaSnBW72yFPR&keysecret=upqZxR3qoaLLpPvKqW5nFUdfG3YYbo9C&sign=6b9260b1e02041a665d4e4a5117cfe16&custom=2&address=2.2.2.2

RouterOS计划任务-线路1，每分钟中执行一次
```
#PPPoE-out
:local pppoe "pppoe-out1"

:local ipaddr [/ip address get [/ip address find interface=$pppoe] address]
:set ipaddr [:pick $ipaddr 0 ([len $ipaddr] -3)]
:local result [/tool fetch url="https://youserverdomain/api/AliyunDDNS/\?domain=vas.ink&type=A&sub=dsm1&keyid=0VX3YaSnBW72yFPR&keysecret=upqZxR3qoaLLpPvKqW5nFUdfG3YYbo9C&sign=6b9260b1e02041a665d4e4a5117cfe16&custom=2&address=$ipadd" as-value output=user];
```
RouterOS计划任务-线路2，每分钟中执行一次
```
#PPPoE-out
:local pppoe "pppoe-out2"

:local ipaddr [/ip address get [/ip address find interface=$pppoe] address]
:set ipaddr [:pick $ipaddr 0 ([len $ipaddr] -3)]
:local result [/tool fetch url="https://youserverdomain/api/AliyunDDNS/\?domain=vas.ink&type=A&sub=dsm1&keyid=0VX3YaSnBW72yFPR&keysecret=upqZxR3qoaLLpPvKqW5nFUdfG3YYbo9C&sign=6b9260b1e02041a665d4e4a5117cfe16&custom=2&address=$ipadd" as-value output=user];
```
