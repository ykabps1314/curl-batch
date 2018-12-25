<?php

namespace App\Library\Tools;

/**
 * curl请求封装类
 * Created by YuanKui
 * Date: 2018/12/12
 * Time: 9:44
 * @package App\Library\Tools
 */
class CurlHandler
{
    /**
     * 请求url个数
     * @var int
     */
    private $size = 5;
    
    /**
     * 等待所有cURL批处理中的活动连接等待响应时间
     * @var int
     */
    private $timeout = 10;
    
    /**
     * 完成请求回调函数
     * @var string
     */
    private $callback = null;
    
    /**
     * cRUL配置
     * @var array
     */
    private $options = array (
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_CONNECTTIMEOUT => 30 
    );
    
    /**
     * 请求头
     * @var array
     */
    private $headers = array ();
    
    /**
     * 请求列队
     * @var array
     */
    private $requests = array ();
    
    /**
     * 请求列队索引
     * @var array
     */
    private $requestMap = array ();
    
    /**
     * 错误
     * @var array
     */
    private $errors = array ();
    
    /**
     * 初始化时制定回调函数
     * @param string $callback 回调函数
     * 该函数有4个参数($response,$info,$error,$request)
     * $response url返回的body
     * $info  cURL连接资源句柄的信息
     * $error  错误
     * $request  请求对象
     */
    public function __construct($callback = null)
    {
        $this->callback = $callback;
    }

    public function __set($name, $value)
    {
        if ($name == 'options' || $name == 'headers') {
            $this->{$name} = $value + $this->{$name};
        } else {
            $this->{$name} = $value;
        }
        return true;
    }

    public function __get($name)
    {
        return $this->{$name} ?? null;
    }

    public function __destruct()
    {
        unset( $this->size, $this->timeout, $this->callback, $this->options, $this->headers, $this->requests, $this->requestMap, $this->errors );
    }

    /**
     * 添加一个请求对象到列队
     * @param object $request
     * @return boolean
     */
    public function addRequest($request)
    {
        $this->requests[] = $request;
        return true;
    }

    /**
     * 创建一个请求对象并添加到列队
     * @param $url
     * @param string $method
     * @param null $requestData
     * @param null $headers
     * @param null $options
     * @return bool
     */
    public function innerRequest($url, $method = 'GET', $requestData = null, $headers = null, $options = null)
    {
        $this->requests[] = new CurlRequest( $url, $method, $requestData, $headers, $options );
        return true;
    }

    /**
     * 创建GET请求对象
     * @param $url
     * @param null $getData
     * @param null $headers
     * @param null $options
     * @return bool
     */
    public function createGet($url, $getData = null, $headers = null, $options = null)
    {
        return $this->innerRequest( $url, 'GET', $getData, $headers, $options );
    }
    /**
     * 创建一个POST请求对象
     * @access public
     * @param string $url
     * @param string $postData
     * @param string $headers
     * @param array $options
     * @return boolean
     */
    public function createPost($url, $postData = null, $headers = null, $options = null)
    {
        return $this->innerRequest( $url, 'POST', $postData, $headers, $options );
    }

    /**
     * 单个url请求
     * @access private
     * @return mixed|boolean
     */
    public function oneCurl()
    {
        $ch = curl_init();
        $request = array_shift( $this->requests );
        $options = $this->getOptions( $request );
        curl_setopt_array( $ch, $options );
        $output = curl_exec( $ch );
        $info = curl_getinfo( $ch );
        $error = curl_error( $ch );
        curl_close($ch);

        //如果设置了回到函数则执行回调函数
        if ($this->callback) {
            $callback = $this->callback;
            if (is_callable( $this->callback )) {
                call_user_func( $callback, $output, $info, $error, $request );
            }
        } else {
            return $output;
        }

        return true;
    }

    /**
     * 多个url请求
     * @access private
     * @param int $size 最大连接数
     * @return boolean
     */
    public function multiCurl($size = null)
    {
        if ($size) {
            $this->size = $size;
        } else {
            $this->size = count($this->requests);
        }

        if (sizeof( $this->requests ) < $this->size) {
            $this->size = sizeof( $this->requests );
        }

        if ($this->size < 2) {
            $this->setError( 'size must be greater than 1' );
        }

        $master = curl_multi_init();
        //添加cURL连接资源句柄到map索引
        for($i = 0; $i < $this->size; $i ++) {
            $ch = curl_init();
            $options = $this->getOptions( $this->requests[$i] );
            curl_setopt_array( $ch, $options );
            curl_multi_add_handle( $master, $ch );
            $key = (string)$ch;
            $this->requestMap[$key] = $i;
        }

        $active = $done = null;
        do {
            while( ($executeRun = curl_multi_exec( $master, $active )) == CURLM_CALL_MULTI_PERFORM );
            if ($executeRun != CURLM_OK) break;

            //有一个请求完成则回调
            while( $done = curl_multi_info_read( $master ) ) {
                //完成的请求句柄
                $info = curl_getinfo( $done['handle'] );
                $output = curl_multi_getcontent( $done['handle'] );
                //错误处理
                $error = curl_error( $done['handle'] );
                if($error)
                    $this->setError( $error );

                //调用回调函数,如果存在的话
                $callback = $this->callback;
                if (is_callable( $callback )) {
                    $key = (string)$done['handle'];
                    $request = $this->requests[$this->requestMap[$key]];
                    unset( $this->requestMap[$key] );
                    call_user_func( $callback, $output, $info, $error, $request );
                }
                curl_close( $done['handle'] );
                //从列队中移除已经完成的request
                curl_multi_remove_handle( $master, $done['handle'] );
            }
            //等待所有cURL批处理中的活动连接
            if ($active) {
                curl_multi_select( $master, $this->timeout );
            }
        } while( $active );
        //完成关闭
        curl_multi_close( $master );
        return true;
    }

    /**
     * 获取没得请求对象的cURL配置
     * @access private
     * @param object $request
     * @return array
     */
    private function getOptions($request)
    {
        //协议参数
        $options = $this->__get( 'options' );
        //请求url设置
        $options[CURLOPT_URL] = $request->url;
        if (ini_get( 'safe_mode' ) == 'Off' || ! ini_get( 'safe_mode' )) {
            $options[CURLOPT_FOLLOWLOCATION] = 1;
            $options[CURLOPT_MAXREDIRS] = 5;
        }
        if ($request->options) {
            $options = $request->options + $options;
        }

        //请求参数
        if ($request->requestData) {
            if(strtolower($request->method) == 'post') {
                $options[CURLOPT_POST] = 1;
                $options[CURLOPT_POSTFIELDS] = http_build_query($request->requestData);
            }elseif(strtolower($request->method) == 'get') {
                $connect = strpos($options[CURLOPT_URL], '?') === false ? '?' : '';
                $options[CURLOPT_URL] = $options[CURLOPT_URL] . $connect . urldecode(http_build_query($request->requestData));
            }
        }

        //获取头信息
        $headers = $this->__get( 'headers' ) ?: [
            'Content-Type: application/x-www-form-urlencoded'
        ];
        if ($headers) {
            $options[CURLOPT_HEADER] = 0;
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        //设置原生的输出流（Raw）
        $options[CURLOPT_RETURNTRANSFER] = 1;

        return json_decode(json_encode($options), true);
    }

    /**
     * 设置错误信息
     * @param string $msg
     */
    public function setError($msg)
    {
        if (!empty( $msg ))
            $this->errors[] = $msg;
    }

    /**
     * 获取错误信息
     * @param string $prefix
     * @param string $suffix
     * @return string
     */
    public function displayErrors($prefix = '', $suffix = '|')
    {
        $str = '';
        foreach ( $this->errors as $val ) {
            $str .= $prefix . $val . $suffix;
        }
        return rtrim($str, $suffix);
    }
}