# codecheck for webman
For webman, create a SMS verification code and verify
适用于webman, 创建一个短信验证码并验证, 短信验证码生成、缓存、验证类库, 基于 [https://github.com/LunziSTU/tp-sms](tpsms) 修改

## 主要特性
* 支持 7 种验证码类型
* 基于 ThinkCache 缓存
* 灵活的配置机制

## 安装
~~~php
composer require hzdad/codecheck
~~~php

## 配置
config/plugin/hzdad/codecheck/app.php
~~~php
return [
    'enable' => true,
    'expire' => 300,//过期时间
    'length' => 6,//验证码长度
    'chcktimes' => 3,//最多可以尝试次数
    'delafterok' => true,//验证后从缓存删除
];
~~~php

## 使用示例

~~~php
    public function createCode()
    {
        $checksms = new \Hzdad\Codecheck\Codecheck();
        $code = $checksms->mobile('18888888888')->scene('login')->create();
        echo $code;
    }


    public function checkCode()
    {
        $checksms = new \Hzdad\Codecheck\Codecheck();
        $res = $checksms->mobile('18888888888')->scene('login')->checktimes(3)->delafterok(false)->code('594093')->check();
        if(!$res){
            $msg = $checksms->getErrorMsg();
        }else{
            $msg = 'ojbk';
        }
        echo $msg;
    }
~~~php

## 版权信息
codecheck遵循Apache2开源协议发布，并提供免费使用。
