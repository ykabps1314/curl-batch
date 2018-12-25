<?php

namespace App\Library\Tools;

/**
 * Description：curl请求参数格式化类
 * Created by YuanKui
 * Date: 2018/12/12
 * Time: 9:44
 * @package App\Library\Tools
 */
class CurlRequest {

    /**
     * 请求url
     * @var string
     */
    public $url = '';

    /**
     * 请求方式
     * @var string
     */
    public $method = 'GET';

    /**
     * 请求参数
     * @var null|string
     */
    public $requestData = null;

    /**
     * 请求头信息
     * @var null|string
     */
    public $headers = null;

    /**
     * 协议(curl)头其它设置参数
     * @var array|null
     */
    public $options = null;

    public function __construct($url, $method = 'GET', $requestData = null, $headers = null, $options = null)
    {

        $this->url      = $url;
        $this->method   = strtoupper( $method );
        $this->requestData = $requestData;
        $this->headers  = $headers;
        $this->options  = $options;
    }

    public function __destruct()
    {
        unset ( $this->url, $this->method, $this->requestData, $this->headers, $this->options );
    }

}