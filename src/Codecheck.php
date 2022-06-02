<?php

namespace Hzdad\CodeCheck;

use think\facade\Cache;

class Codecheck
{
    protected $config = [];

    protected $cachePrefix = 'smscode_';
    // 验证码字符池
    protected $character = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    // 验证码过期时间（s），默认 5 分钟
    protected $expire = 300;
    // 验证码位数
    protected $length = 6;
    // 验证码类型
    protected $type = 1;
    // 验证码
    protected $code = '';
    // 场景
    protected $scene = '';
    // 错误信息
    protected $error = '';
    // 手机号字段名
    protected $mobileName = 'mobile';
    // 验证码字段名
    protected $codeName = 'code';
//    手动传入手机号
    protected $_mobile;
//    手动传入验证码
    protected $_code;

    protected $chcktimes = 3;//最多可以尝试验证多少次,超过次数失效,需要重新获取并验证

    protected $delafterok = true;//验证成功后删除,为false时可以再有效期内无数次验证通过

    /**
     * 架构方法，动态配置
     * TpSms constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if (empty($config)) {
            $config = config('plugin.hzdad.codecheck.app');
        }
        $this->config = $config;
        foreach ($config as $key => $val) {
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }
    }

    /**
     * 设置场景值
     * @param string $scene
     * @return $this
     */
    public function scene(string $scene): Codecheck
    {
        $this->scene = $scene;
        return $this;
    }


    /**
     * 设置尝试次数
     * @param string $scene
     * @return $this
     */
    public function checktimes(int $chcktimes): Codecheck
    {
        $this->chcktimes = $chcktimes;
        return $this;
    }


    /**
     * 验证成功后是否删除, 默认删除
     * @param string $scene
     * @return $this
     */
    public function delafterok(bool $delafterok): Codecheck
    {
        $this->delafterok = $delafterok;
        return $this;
    }


    /**
     * 手动传入手机号
     * @param string $mobile
     * @return $this
     */
    public function mobile(string $mobile): Codecheck
    {
        $this->_mobile = $mobile;
        return $this;
    }

    /**
     * 手动传入验证码
     * @param string $code
     * @return $this
     */
    public function code(string $code): Codecheck
    {
        $this->_code = $code;
        return $this;
    }

    /**
     * 生成验证码
     * @return string
     * @throws \Exception
     */
    public function create(): string
    {
        $mobile = $this->_mobile ?? request()->input($this->mobileName);

        if (!$mobile) {
            $this->error = '未传入手机号';
            return false;

        }

        switch ($this->type){
            case 1:
//            纯数字型验证码
                $range = [0,9];
                break;
            case 2:
//                纯小写字母型验证码
                $range = [10,35];
                break;
            case 3:
//                纯大写字母型验证码
                $range = [36,61];
                break;
            case 4:
//                数字与小写字母混合型验证码
                $range = [0,35];
                break;
            case 5:
//                数字与大写字母混合型验证码
                $this->character = strtoupper($this->character);
                $range = [0,35];
                break;
            case 6:
//                小写字母与大写字母混合型验证码
                $range = [10,61];
                break;
            case 7:
//                数字、小写字母和大写字母混合型验证码
                $range = [0,61];
                break;
            default:
//                报错：不支持的验证码类型
                $this->error = '不支持的验证码类型';
                return false;
        }
//        拼接验证码
        for ($i = 0; $i < $this->length; $i++){
            $this->code .= $this->character[random_int($range[0],$range[1])];
        }
//        缓存
        $cacheKey = $this->cachePrefix.$this->scene.$mobile;
//        增加ip验证
        $ip = request()->getRealIp($safe_mode=true);
        $cacheVal = $this->code."_".ip2long($ip);
        Cache::set($cacheKey,$cacheVal,$this->expire);
        return $this->code;
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function getErrorMsg(): string
    {
        return $this->error;
    }

    /**
     * 验证码验证
     * @return bool
     */
    public function check(): bool
    {
        $mobile = $this->_mobile ?? request()->input($this->mobileName);
        if (!$mobile) {
            $this->error = '未传入手机号';
            return false;
        }
        $code = $this->_code ?? request()->input($this->codeName);
        if (!$code) {
            $this->error = '未传入验证码';
            return false;
        }

        $cache_key = $this->cachePrefix.$this->scene.$mobile;
        $times_key = $cache_key.'checktimes';

        //获取验证次数,最多3次
        $checktimes = Cache::get($times_key);
        if($checktimes && $checktimes>=$this->chcktimes){

            //清除验证码
            Cache::set($times_key,NULL);
            Cache::set($cache_key,NULL);
            $this->error = '验证码失效,请重新获取';
            return false;
        }


//        获取缓存验证码
        $cacheCode = Cache::get($cache_key);
        if($cacheCode){

//            增加ip验证
            $ip = request()->getRealIp($safe_mode=true);
            if ($cacheCode === $code."_".ip2long($ip)){

                if($this->delafterok){
                    //验证完毕,清除验证码缓存
                    Cache::set($times_key,NULL);
                    Cache::set($cache_key,NULL);
                }else{
                    //验证次数改成0
                    Cache::set($times_key,0);
                }

                return true;
            }

            //次数加1

            if (Cache::has($times_key)){
                $value  = Cache::get($times_key);
                $value  = $value+1;
            }else{
                $value  = 1;
            }
            Cache::set($times_key, $value, $this->expire);

            $this->error = '验证码不正确';
            return false;
        } else {
            $this->error = '验证码无效在或已过期';
            return false;
        }
    }

}