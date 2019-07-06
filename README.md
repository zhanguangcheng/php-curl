# curl

面向对象风格的Curl操作库，版本要求：PHP >= 5.3

## 使用示例

初始化
```php
$curl = new Curl();
Curl::instance();
```

发送get请求
```php
$curl->get('http://example.com');

// 可使用数组形式的url
$curl->get(array('http://example.com/search', 'keywords' => 'grass'));
```

发送post请求
```php
$curl->post('http://example.com/login/', array(
    'username' => 'admin',
    'password' => '123456',
));
```

文件下载
```php
$curl->download('http://example.com/file.zip', '/path/to/file.zip');
```

文件上传
```php
$curl->addUploadFile('name', '/path/to/file.zip');
$curl->addUploadFile('img', '/path/to/demo.jpg');
$curl->post('http://example.com/upload.php');
```

转换结果为json格式
```php
$curl->asJson()->get('http://example.com');
```

发送其他请求
```php
$curl->put('http://api.example.com/user/', array(
    'name' => 'Grass',
));
$curl->put();
$curl->patch();
$curl->delete();
$curl->options();
$curl->request($url, 'HEAD');
```

获取结果
```php
$curl->curl;                // curl资源句柄
$curl->error_code;          // curl错误码
$curl->error_message;       // curl错误信息
$curl->request_url;         // 请求的url
$curl->request_header;      // 发送的请求头
$curl->request_body;        // 发送的请求体
$curl->request_cookie;      // 发送的cookie
$curl->upload_file;         // 上传的文件
$curl->response;            // 响应体
$curl->response_info;       // curl_getinfo()获取到的响应信息
$curl->response_header;     // 响应头
$curl->response_code;       // HTTP响应的状态码
```

连贯调用
```php
Curl::instance()->asJson()->get('http://example.com')->response;
```

多线程请求
```php
$curl1 = new Curl();
$curl2 = new Curl();
Curl::multiExec(array(
    $curl1->multi()->get('http://api.example.com'),
    $curl2->multi()->post('http://api.example.com'),
));
Curl::multiClose();
echo $curl1->response;
echo $curl2->response;
```

其他可用方法
```php
$curl->setOpt();
$curl->setHeader();
$curl->setCookie();
$curl->setAjax();
$curl->multi();
$curl->asText();
$curl->asJson();
$curl->reset();
```


## 批量请求示例

```php
$thread_num = 20;
$urls = array_fill(0, 100, 'https://example.com');

$url_chunks = array_chunk($urls, $thread_num);
$curls = array();
for ($i=0; $i < $thread_num; $i++) { 
    $curls[] = new Curl();
}
foreach ($url_chunks as $chunks) {
    $requests = array();
    foreach ($chunks as $i => $url) {
        $requests[] = $curls[$i]->reset()->multi()->get($url);
    }
    Curl::multiExec($requests);
    foreach ($requests as $i => $curl) {
        echo $curl->response_code, ' ';
    }
}
Curl::multiClose();
```


## 执行测试

```bash
composer install
phpunit
```