<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/4
 * Time: 19:20
 */

class Core
{

    //$api_url 为各个网站接口的地址
    private $api_url = array(
        'http://test2.aiku.fun/sso/callback.php',
    );
    //以下3个参数 2个核心类要一致
    //加密用RSA公钥 秘钥格式PKCS#1
    private $public_key = '';

    //加密用RSA私钥 秘钥格式PKCS#1
    private  $private_key = '';


    //code 加密用秘钥
    private $md5_key = '';


    /**
     * 初始化
     */
    public function __construct()
    {
        ini_set('error_reporting', -1); //关闭错误提示
        ini_set('display_errors', -1);  //关闭错误提示
        $this->public_key = openssl_pkey_get_public($this->public_key);//格式化秘钥
        $this->private_key =  openssl_pkey_get_private($this->private_key);//格式化秘钥
    }

    /**
     * 获取对象属性
     */
    function __get($property_name) {
        return isset($this->$property_name) ? $this->$property_name : null;
    }

    /**
     * 设置对象属性
     */
    function __set($property_name, $value) {
        $this->$property_name = $value;
    }

    /**
     * 加密方法  （可自定义 如果自定义那么公钥私钥也需自行修改）
     */
    function encryption($data){

        $data = json_encode($data);
        $encrypted = '';
        openssl_public_encrypt($data, $encrypted, $this->public_key);//公钥加密
        $encrypted = base64_encode($encrypted);// base64传输
        return $encrypted;
    }

    /**
     * 解密方法  （可自定义 如果自定义那么公钥私钥也需自行修改）
     */
    function decrypted($data){

        $decrypted = '';
        openssl_private_decrypt(base64_decode($data), $decrypted, $this->private_key);//私钥解密
        return json_decode($decrypted, true);
    }

    /**
     * get_cookie 获取cookie并解密  （可自定义）
     */
    function get_cookie($key=''){
        if(empty($key)){
            $list = array();
            foreach($_COOKIE as $k=>$v){
                if(empty($v)){
                    continue;
                }
                $list[$k]= $this->decrypted($v);
            }
            return $list;
        }else{
            if(empty($_COOKIE[$key])){
                return array();
            }else{
                return $this->decrypted($_COOKIE[$key]);
            }
        }
    }

    /**
     * $array
     * set_cookie 设置cookie并解密  （可自定义）
     */
    function set_cookie($info){
        if(empty($info)){
            return false;
        }
        foreach($info as $key=>$val){
            setcookie($key,$val,0,'/');
        }
        return true;
    }


    /**
     * $array
     * for_encryption 循环加密返回数组 （可自定义）
     */
    function for_encryption($info){
        $arr = array();
        foreach($info as $key=>$val){
            $arr[$key] = $val = $this->encryption($val);
        }
        return $arr;
    }

    /**
     * 生成code并将用户信息存到缓存数据库  （可自定义）
     */
    function code($url='',$info){
        $json = json_encode($info);

        $code = md5($json.$url);
        $key = md5($code.$this->md5_key);
        //实例化redis
        $redis = new Redis();
        //连接
        $redis->connect('127.0.0.1', 6379);
        $redis->setex($key,100,$json);//key=value，有效期为10秒
        return $code;
    }

    /**
     * 根据code查找缓存数据库 并返回信息  （可自定义）
     */
    function get_info($key){

        //实例化redis
        $redis = new Redis();
        //连接
        $redis->connect('127.0.0.1', 6379);
        $info = $redis->get($key);
        $redis->del($key);
        return $info;
    }


    /**
     * 将获取到的用户信息解密  （可自定义）
     */
    function get_user($info){
        $info = json_decode($info);
        $res = array();
        foreach ($info as $k => $v){
            $res[$k] = $this->decrypted($v);
        }
        return $res;
    }

    /**
     * 登陆通知  （可自定义）
     */
    function login($info){

        //通知全部网站接口登出
        foreach ($this->api_url as $url){
            $code = $this->code($url,$info);
            $time = time();
            $params = array('time'=>$time,'type'=>'login','code'=>$code);
            $sign = $this->sign($params);
            $params['sign'] = $sign;
            $url = $url.'?'.http_build_query($params);
            echo '<script src="'.$url.'" type="text/javascript"></script>';
        }
        //跳转到发起退出登录的网站
        echo '<script type="text/javascript">window.onload=function(){window.location.href = "'.$_GET['callback'].'";}</script>';
    }


    /**
     * 退出通知  （可自定义）
     */
    function logout(){
        //通知全部网站接口登出
        foreach ($this->api_url as $url){
            $time = time();
            $params = array('time'=>$time,'type'=>'logout');
            $sign = $this->sign($params);
            $params['sign'] = $sign;
            $js_url = $url.'?'.http_build_query($params);
            echo '<script src="'.$js_url.'" type="text/javascript"></script>';
        }
        //跳转到发起退出登录的网站
        echo '<script type="text/javascript">window.onload=function(){window.location.href = document.referrer;}</script>';
    }

    /**
     * 加密sign
     * @param $params
     * @return string
     */
    function sign($params)
    {
        ksort($params);
        $sign = '';
        foreach ($params as $key => $val) {
            $sign .= $key . $val;
        }
        $sign .= 'keysecret' . $this->md5_key;
        $sign = md5($sign);
        return $sign;
    }
}