<?php

/**
 * 面向对象风格的Curl操作库
 * https://github.com/zhanguangcheng/php-curl
 * 
 * ```php
 * // 初始化
 * $curl = new Curl();
 * 
 * // get请求
 * $curl->get('http://example.com');
 * $curl->get(array('http://example.com/search', 'keywords' => 'grass'));
 * 
 * // post请求
 * $curl->post('http://example.com/login/', array(
 *     'username' => 'admin',
 *     'password' => '123456',
 * ));
 * 
 * // 文件下载
 * $curl->download('http://example.com/file.zip', '/path/to/file.zip');
 * 
 * // 文件上传
 * $curl->addUploadFile('name', '/path/to/file.zip');
 * $curl->post('http://example.com/upload.php');
 * 
 * // 其他请求
 * $curl->put('http://api.example.com/user/', array(
 *     'name' => 'Grass',
 * ));
 * 
 * // 获取结果
 * $curl->error_code;
 * $curl->error_message;
 * $curl->request_url;
 * $curl->request_header;
 * $curl->request_body;
 * $curl->request_cookie;
 * $curl->upload_file;
 * $curl->response;
 * $curl->response_info;
 * $curl->response_header;
 * $curl->response_code;
 * 
 * // 连贯调用
 * Curl::instance()->asJson()->get('http://example.com')->response;
 * 
 * // 多线程请求
 * $curl1 = new Curl();
 * $curl2 = new Curl();
 * Curl::multiExec(array(
 *     $curl1->multi()->get('http://api.example.com'),
 *     $curl2->multi()->post('http://api.example.com'),
 * ));
 * Curl::multiClose();
 * echo $curl1->response;
 * echo $curl2->response;
 * 
 * ```
 */
class Curl
{
    /**
     * curl资源句柄
     * @var resource
     */
    public $curl = null;
    public $fp = null;
    public $multi = false;
    public $as_json = array();
    private $default_user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36';
    private static $instance = null;
    private static $multi_curl = null;

    /**
     * 错误码
     * @see https://curl.haxx.se/libcurl/c/libcurl-errors.html
     * @var integer
     */
    public $error_code = 0;
    public $error_message = '';
    public $verify_ssl = false;
    
    /**
     * request 相关
     */
    public $request_url = null;
    public $request_header = array();
    public $request_body = array();
    public $request_cookie = array();
    public $upload_file = array();
    public $request_content_type = 'application/x-www-form-urlencoded';
    
    /**
     * response 相关
     */
    public $response = null;
    public $response_origin = null;
    public $response_info = array();
    public $response_header = array();
    public $response_code = 0;

    public function __construct()
    {
        if (!extension_loaded('curl')) {
            throw new \ErrorException('cURL扩展尚未安装');
        }
        $this->curl = curl_init();
        $this->init();
    }
    
    public function __destruct()
    {
        $this->close();
    }
    
    public static function instance()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }
    
    public function init()
    {
        $this->setOpt(CURLOPT_RETURNTRANSFER, true);
        $this->setOpt(CURLINFO_HEADER_OUT, true);
        $this->setOpt(CURLOPT_TIMEOUT, 10);
        $this->setOpt(CURLOPT_AUTOREFERER, true);
        $this->setOpt(CURLOPT_FOLLOWLOCATION, true);
        $this->setOpt(CURLOPT_MAXREDIRS, 10);
        if (!$this->verify_ssl) {
            $this->setOpt(CURLOPT_SSL_VERIFYPEER, false);
            $this->setOpt(CURLOPT_SSL_VERIFYHOST, 0);
        }
        $this->setOpt(CURLOPT_HEADERFUNCTION, array($this, 'addResponseHeader'));
        $this->setUserAgent($this->default_user_agent);
        $this->setContentTypeUrlencoded();
        return $this;
    }
    
    public function get($url)
    {
        return $this->request($url, 'GET');
    }
    
    public function post($url, $data = array())
    {
        return $this->request($url, 'POST', $data);
    }
    
    public function put($url, $data = array())
    {
        return $this->request($url, 'PUT', $data);
    }
    
    public function patch($url, $data = array())
    {
        return $this->request($url, 'PATCH', $data);
    }
    
    public function delete($url, $data = array())
    {
        return $this->request($url, 'DELETE', $data);
    }
    
    public function options($url, $data = array())
    {
        return $this->request($url, 'OPTIONS', $data);
    }
    
    public function download($url, $save_file)
    {
        $this->setDownloadFile($save_file)->get($url);
        return $this->response === true;
    }

    public function setDownloadFile($save_file)
    {
        $this->fp = fopen($save_file, 'w');
        $this->setOpt(CURLOPT_FILE, $this->fp);
        return $this;
    }
    
    public function setProgressCallback($callback)
    {
        return $this->setOpt(CURLOPT_NOPROGRESS, false)->setOpt(CURLOPT_PROGRESSFUNCTION, $callback);
    }

    public function addUploadFile($field, $upload_file)
    {
        if (class_exists('CURLFile')) {
            $this->setOpt(CURLOPT_SAFE_UPLOAD, true);
            $this->upload_file[$field] = new \CURLFile($upload_file);
        } else {
            $this->upload_file[$field] = "@$upload_file";
        }
        return $this;
    }
    
    public function multi()
    {
        $this->multi = true;
        return $this;
    }
    
    public function asText()
    {
        $this->as_json = array();
        return $this;
    }
    
    public function asJson($assoc = false, $depth = 512)
    {
        $this->as_json = array($assoc, $depth);
        return $this;
    }
    
    public function request($url, $method = 'GET', $data = array())
    {
        $method = strtoupper($method);
        if ($method === 'GET') {
            $this->setOpt(CURLOPT_HTTPGET, true);
        } elseif ($method === 'POST') {
            $this->setOpt(CURLOPT_POST, true);
            if ($this->upload_file) {
                $this->setContentTypeFormData();
                $data = array_merge($this->upload_file, $data);
            }
            $this->setOpt(CURLOPT_POSTFIELDS, $this->prepareData($data));
        } else {
            $this->setOpt(CURLOPT_CUSTOMREQUEST, $method);
            $this->setOpt(CURLOPT_POSTFIELDS, $this->prepareData($data));
        }
        
        $this->request_url = $this->buildUrl($url);
        $this->request_body = $data;
        $this->setOpt(CURLOPT_URL, $this->request_url);
        if (!$this->multi) {
            $this->exec();
        }
        return $this;
    }
    
    public static function multiExec($instancees)
    {
        if (is_null(static::$multi_curl)) {
            static::$multi_curl = curl_multi_init();
        }
        $mh = static::$multi_curl;
        foreach ($instancees as $instance) {
            curl_multi_add_handle($mh, $instance->curl);
            $instance->response_header = array();
        }
        $running = null;
        do {
            if (curl_multi_select($mh) == -1) {
                usleep(1000);
            }
            curl_multi_exec($mh, $running);
        } while($running > 0);
        foreach ($instancees as $instance) {
            $instance->response_origin = curl_multi_getcontent($instance->curl);
            $instance->response = $instance->toJson($instance->response_origin);
            $instance->exec();
            curl_multi_remove_handle($mh, $instance->curl);
        }
        return $instancees;
    }
    
    public static function multiClose()
    {
        if (is_resource(static::$multi_curl)) {
            curl_multi_close(static::$multi_curl);
        }
    }

    public function exec()
    {
        if (!$this->multi) {
            $this->response_header = array();
            $this->response_origin = curl_exec($this->curl);
            $this->response = $this->toJson($this->response_origin);
        }
        $this->error_code = curl_errno($this->curl);
        $this->error_message = curl_error($this->curl);
        $this->response_info = curl_getinfo($this->curl);
        $this->response_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        $this->request_header = array_filter(explode("\r\n", curl_getinfo($this->curl, CURLINFO_HEADER_OUT)));
        if (is_resource($this->fp)) {
            fclose($this->fp);
        }
        return $this;
    }
    
    public function reset()
    {
        $this->request_url = null;
        $this->request_header = array();
        $this->request_body = array();
        $this->request_cookie = array();
        $this->upload_file = array();
        $this->response = null;
        $this->response_origin = null;
        $this->response_info = array();
        $this->response_header = array();
        $this->response_code = 0;
        $this->multi = false;
        $this->as_json = array();
        $this->error_code = 0;
        $this->error_message = '';
        $this->verify_ssl = false;
        curl_reset($this->curl);
        $this->init();
        return $this;
    }
    
    public function setOpt($option, $value)
    {
        curl_setopt($this->curl, $option, $value);
        return $this;
    }
    
    /**
     * 设置请求头
     * 
     * ```php
     * $curl->setHeader('X-Requested-With', 'XMLHttpRequest');
     * ```
     * @param string $key
     * @param string $value
     */
    public function setHeader($key, $value)
    {
        $this->request_header[$key] = $key.': '.$value;
        $this->setOpt(CURLOPT_HTTPHEADER, array_values($this->request_header));
        return $this;
    }
    
    public function setContentTypeUrlencoded()
    {
        $this->request_content_type = 'application/x-www-form-urlencoded';
        return $this->setHeader('Content-Type', $this->request_content_type);
    }
    
    public function setContentTypeFormData()
    {
        $this->request_content_type = 'multipart/form-data';
        return $this->setHeader('Content-Type', $this->request_content_type);
    }
    
    public function setContentTypeJson()
    {
        $this->request_content_type = 'application/json';
        return $this->setHeader('Content-Type', $this->request_content_type);
    }
    
    public function setContentTypeXml()
    {
        $this->request_content_type = 'application/xml';
        return $this->setHeader('Content-Type', $this->request_content_type);
    }
    
    public function setCookie($key, $value)
    {
        $this->request_cookie[$key] = $value;
        $this->setOpt(CURLOPT_COOKIE, http_build_query($this->request_cookie, '', '; '));
        return $this;
    }

    public function setAjax()
    {
        $this->setHeader('X-Requested-With', 'XMLHttpRequest');
        return $this;
    }
    
    public function setUserAgent($value)
    {
        $this->setOpt(CURLOPT_USERAGENT, $value);
        return $this;
    }
    
    public function close()
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
        if (is_resource($this->fp)) {
            curl_close($this->fp);
        }
        return $this;
    }
    
    public function toJson($data)
    {
        if ($this->as_json) {
            return json_decode($data, $this->as_json[0], $this->as_json[1]);
        }
        return $data;
    }
    
    /**
     * 编译url
     * @param  string|array $url url字符串，或者数组形式：[url, 'param1'=>'value'...]
     * @return string 处理好的url
     */
    public function buildUrl($url)
    {
        if (is_array($url)) {
            $url_str = array_shift($url);
            return $url_str . (strpos($url_str, '?') ? '&' : '?') . http_build_query($url);
        }
        return $url;
    }
    
    /**
     * 预处理一下请求数据
     * @param  array $data 待处理的数组数据
     * @return string 处理好的字符串数据
     */
    public function prepareData($data)
    {
        if (is_array($data) || is_object($data)) {
            switch ($this->request_content_type) {
                case 'application/x-www-form-urlencoded': return http_build_query($data);
                case 'application/json': return json_encode($data);
                case 'multipart/form-data': return $data;
                default:
                    throw new \InvalidArgumentException("数据错误或者Content Type 错误：$this->request_content_type");
            }
        }
        return $data;
    }
    
    /**
     * 设置响应头的回调函数
     * @param resource $curl cURL的资源句柄
     * @param string $header  header 数据
     * @return integer 返回已写入的数据大小
     */
    protected function addResponseHeader($curl, $header)
    {
        $trimmed_header = trim($header, "\r\n");
        if (!($trimmed_header === "" || strtolower($trimmed_header) === 'http/1.1 100 continue')) {
            $this->response_header[] = $trimmed_header;
        }
        return strlen($header);
    }
}
