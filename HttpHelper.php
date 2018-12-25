<?php

namespace App\Library\Tools;

/**
 * 使用curl助手类
 * Date: 2018/12/12
 * Time: 9:40
 * @package App\Library\Tools
 */
class HttpHelper
{
    /**
     * 请求的信息集合
     * @var array
     */
    private static $allRequestData = [];

    /**
     * 回调函数
     * @var null
     */
    private static $callBack = null;

    /**
     * 批处理的并发数量
     * @var int
     */
    private static $curlCount = 5;

    /**
     * 单个以及批处理的方法
     * @var string
     */
    private static $requestMethod = 'oneCurl';

    /**
     * 请求处理对象
     * @var CurlHandler
     */
    private static $curlHandler;

    public function __construct($allRequestData, $callBack = null, $curlCount = 5)
    {
        static::$allRequestData = $allRequestData;
        static::$curlCount      = $curlCount;
        static::$callBack       = $callBack;

        static::$curlHandler    = new CurlHandler( $callBack );
    }

    /**
     * 使用get发送请求
     * @return mixed
     */
    public static function get()
    {
        self::initRequestData($method = 1);

        return static::$curlHandler->{static::$requestMethod}();
    }

    /**
     * 使用post发送请求
     * @return mixed
     */
    public static function post()
    {
        self::initRequestData($method = 2);

        return static::$curlHandler->{static::$requestMethod}(static::$curlCount);
    }

    /**
     * 初始化请求参数
     * @param int $method
     */
    public static function initRequestData($method = 1)
    {
        $method = $method == 1 ? 'GET' : 'POST';

        if(self::getDimensionality(static::$allRequestData) == 2) {
            foreach (static::$allRequestData as $item) {
                $request = new CurlRequest($item['url'], $method, $item['requestData'], $item['header'], $item['options']);
                static::$curlHandler->addRequest($request);
            }

            if(count(static::$allRequestData) >= 2) {
                static::$requestMethod  = 'multiCurl';
            }
        } else {
            $request = new CurlRequest(static::$allRequestData['url'], $method,
                static::$allRequestData['requestData'], static::$allRequestData['header'], static::$allRequestData['options']);

            static::$curlHandler->addRequest($request);

            static::$requestMethod  = 'oneCurl';
        }
    }

    /**
     * 判断请求参数是一维还是二维数组
     * @param $array
     * @return int
     */
    public static function getDimensionality($array)
    {
        return isset($array['url']) ? 1 : 2;
    }
}