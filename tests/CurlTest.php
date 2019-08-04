<?php

require __DIR__ . '/../src/Curl.php';

class CurlTest extends PHPUnit\Framework\TestCase
{
    public function testBuildUrl()
    {
        $curl = new Curl();
        $url = $this->getUrl('get');
        $expect = $url . '?a=b&c=d';
        $this->assertEquals($url, $curl->buildUrl($url));
        $this->assertEquals($expect, $curl->buildUrl([$url, 'a'=>'b','c'=>'d']));
        $this->assertEquals($expect, $curl->buildUrl([$url . '?a=b', 'c'=>'d']));
    }
    
    public function testPrepareData()
    {
        $curl = new Curl();
        $obj = new StdClass();
        $obj->a = 'b';
        $obj->c = 'd';
        $expect = 'a=b&c=d';
        $this->assertEquals($expect, $curl->prepareData('a=b&c=d'));
        $this->assertEquals($expect, $curl->prepareData(['a'=>'b', 'c'=>'d']));
        $this->assertEquals($expect, $curl->prepareData($obj));
    }
    
    public function testInstance()
    {
        $this->assertEquals(Curl::instance(), Curl::instance());
    }
    
    public function testGet()
    {
        $url = $this->getUrl('get');
        $this->assertEquals('b', Curl::instance()->reset()->asJson()->get([$url, 'a'=>'b'])->response->args->a);
    }
    
    public function testPost()
    {
        $url = $this->getUrl('post');
        $this->assertEquals('b', Curl::instance()->reset()->asJson()->post($url, ['a'=>'b'])->response->form->a);
    }
    
    public function testPut()
    {
        $url = $this->getUrl('put');
        $this->assertEquals('b', Curl::instance()->reset()->asJson()->put($url, ['a'=>'b'])->response->form->a);
    }
    
    public function testDelete()
    {
        $url = $this->getUrl('delete');
        $this->assertEquals('b', Curl::instance()->reset()->asJson()->delete($url, ['a'=>'b'])->response->form->a);
    }
    
    public function testPatch()
    {
        $url = $this->getUrl('patch');
        $this->assertEquals('b', Curl::instance()->reset()->asJson()->patch($url, ['a'=>'b'])->response->form->a);
    }
    
    public function testUploadFile()
    {
        $curl = Curl::instance()->reset()->asJson()->addUploadFile('file', __DIR__ . '/testfile.txt')->post($this->getUrl('post'));
        $this->assertEquals('text', $curl->response->files->file);
    }
    
    public function testDownload()
    {
        $ret = Curl::instance()->reset()->download($this->getUrl('get?a=b'), __DIR__ . '/testdownload.json');
        $this->assertEquals(true, $ret);
        $json = json_decode(file_get_contents(__DIR__ . '/testdownload.json'));
        $this->assertEquals('b', $json->args->a);
    }
    
    public function testMultiExec()
    {
        $curl1 = new Curl();
        $curl2 = new Curl();
        Curl::multiExec(array(
            $curl1->multi()->asJson()->get([$this->getUrl('get'), 'a'=>'b']),
            $curl2->multi()->asJson()->post($this->getUrl('post'), ['a'=>'b']),
        ));
        $this->assertEquals('b', $curl1->response->args->a);
        $this->assertEquals('b', $curl2->response->form->a);
    }
    
    public function testSetAjax()
    {
        $curl = Curl::instance()->reset()->asJson(true)->setAjax()->get($this->getUrl('get'));
        $this->assertEquals('XMLHttpRequest', $curl->response['headers']['X-Requested-With']);
    }
    
    public function testSetCookie()
    {
        $curl = Curl::instance()->reset()->asJson()->setCookie('a', 'b')->get($this->getUrl('get'));
        $this->assertEquals('a=b', $curl->response->headers->Cookie);
    }
    
    public function testSetUserAgent()
    {
        $curl = Curl::instance()->reset()->asJson(true)->setUserAgent('curl')->get($this->getUrl('get'));
        $this->assertEquals('curl', $curl->response['headers']['User-Agent']);
    }
    
    public function testSetContentTypeUrlencoded()
    {
        $curl = Curl::instance()->reset()->asJson(true)->setContentTypeUrlencoded()->post($this->getUrl('post'));
        $this->assertEquals('application/x-www-form-urlencoded', $curl->response['headers']['Content-Type']);
    }
    
    public function testSetContentTypeFormData()
    {
        $curl = Curl::instance()->reset()->asJson(true)->setContentTypeFormData()->post($this->getUrl('post'));
        $this->assertStringStartsWith('multipart/form-data', $curl->response['headers']['Content-Type']);
    }
    
    public function testSetContentTypeJson()
    {
        $curl = Curl::instance()->reset()->asJson(true)->setContentTypeJson()->post($this->getUrl('post'));
        $this->assertEquals('application/json', $curl->response['headers']['Content-Type']);
    }
    
    public function testSetContentTypeXml()
    {
        $curl = Curl::instance()->reset()->asJson(true)->setContentTypeXml()->post($this->getUrl('post'), '<xml>');
        $this->assertEquals('application/xml', $curl->response['headers']['Content-Type']);
    }
    
    public function testClose()
    {
        $this->assertEquals(false, is_resource(Curl::instance()->close()->curl));
    }
    
    public function getUrl($path)
    {
        return "http://httpbin.org/$path";
    }
}
