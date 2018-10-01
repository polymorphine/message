<?php

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message\Tests;

use PHPUnit\Framework\TestCase;
use Polymorphine\Message\ServerRequestFactory;
use Polymorphine\Message\Tests\Doubles\FakeUploadedFile;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use InvalidArgumentException;

require_once __DIR__ . '/Fixtures/request-factory-functions.php';


class ServerRequestFactoryTest extends TestCase
{
    public static $nativeCallResult;

    public function tearDown()
    {
        self::$nativeCallResult = null;
    }

    public function testInstantiation()
    {
        $this->assertInstanceOf(ServerRequestFactory::class, $this->factory());
    }

    public function testBasicIntegration()
    {
        $data    = $this->basicData();
        $factory = $this->factory($data);
        $this->assertInstanceOf(ServerRequestFactory::class, $factory);

        $request = $factory->create(['attr' => 'attr value']);
        $this->assertInstanceOf(ServerRequestInterface::class, $request);
        $this->assertSame($data['server'], $request->getServerParams());
        $this->assertSame($data['get'], $request->getQueryParams());
        $this->assertSame($data['post'], $request->getParsedBody());
        $this->assertSame($data['cookie'], $request->getCookieParams());
        $this->assertSame(['attr' => 'attr value'], $request->getAttributes());
        $this->assertSame('1.0', $request->getProtocolVersion());
    }

    public function testOverridingSuperglobals()
    {
        $_POST   = ['name' => 'overwritten value', 'original' => 'original value'];
        $_GET    = ['name' => 'overwritten value'];
        $_COOKIE = ['cookie' => 'original cookie'];
        $data    = $this->basicData();
        $request = ServerRequestFactory::fromGlobals($data);

        $this->assertInstanceOf(ServerRequestInterface::class, $request);
        $this->assertSame($data['server'] + $_SERVER, $request->getServerParams());
        $this->assertSame($data['get'], $request->getQueryParams());
        $this->assertSame($data['post'] + $_POST, $request->getParsedBody());
        $this->assertSame($data['cookie'], $request->getCookieParams());
        $this->assertSame([], $request->getAttributes());
    }

    /**
     * @dataProvider normalizeHeaderNames
     *
     * @param $serverKey
     * @param $headerName
     */
    public function testNormalizedHeadrNamesFromServerArray($serverKey, $headerName)
    {
        $data['server'] = [$serverKey => 'value'];
        $this->assertTrue($this->factory($data)->create()->hasHeader($headerName));
    }

    public function normalizeHeaderNames()
    {
        return [
            ['HTTP_ACCEPT', 'Accept'],
            ['HTTP_ACCEPT_ENCODING', 'Accept-Encoding'],
            ['HTTP_CONTENT_MD5', 'Content-MD5'],
            ['CONTENT_TYPE', 'Content-Type']
        ];
    }

    public function testResolvingAuthorizationHeader()
    {
        $this->assertFalse($this->factory()->create()->hasHeader('Authorization'));
        $data['server'] = ['HTTP_AUTHORIZATION' => 'value'];
        $this->assertTrue($this->factory($data)->create()->hasHeader('Authorization'));
        self::$nativeCallResult = ['Authorization' => 'value'];
        $this->assertTrue($this->factory()->create()->hasHeader('Authorization'));
        self::$nativeCallResult = ['authorization' => 'value'];
        $this->assertTrue($this->factory()->create()->hasHeader('Authorization'));
        self::$nativeCallResult = ['AUTHORIZATION' => 'value'];
        $this->assertFalse($this->factory()->create()->hasHeader('Authorization'));
    }

    public function testUploadedFileSuperglobalParameterStructure()
    {
        $files['test'] = [
            'tmp_name' => 'phpFOOBAR',
            'name'     => 'avatar.png',
            'size'     => 10240,
            'type'     => 'image/jpeg',
            'error'    => 0
        ];
        $request = $this->factory(['files' => $files])->create();
        $this->assertInstanceOf(UploadedFileInterface::class, $request->getUploadedFiles()['test']);
    }

    public function testUploadedFileNestedStructureParameter()
    {
        $files = [
            'first'  => new FakeUploadedFile(),
            'second' => ['subcategory' => new FakeUploadedFile()]
        ];
        $request = $this->factory(['files' => $files])->create();
        $this->assertSame($files, $request->getUploadedFiles());
    }

    public function testSingleUploadedFileStructure()
    {
        $files['test'] = $this->fileData('test.txt');
        $request = $this->factory(['files' => $files])->create();

        /** @var UploadedFileInterface[] $file */
        $file = $request->getUploadedFiles();
        $this->assertInstanceOf(UploadedFileInterface::class, $file['test']);
        $this->assertSame('test.txt', $file['test']->getClientFilename());
    }

    public function testMultipleUploadedFileStructure()
    {
        $files['test'] = $this->fileData(['testA.txt', 'testB.txt']);
        $request = $this->factory(['files' => $files])->create();

        /** @var UploadedFileInterface[][] $file */
        $file = $request->getUploadedFiles();
        $this->assertInstanceOf(UploadedFileInterface::class, $file['test'][0]);
        $this->assertSame('testB.txt', $file['test'][1]->getClientFilename());
    }

    public function testMixedStructureUploadedFiles()
    {
        $files = [
            'test'      => ['multiple' => $this->fileData(['testA.txt', 'testB.txt'])],
            'multipleC' => [new FakeUploadedFile(), new FakeUploadedFile()],
            'singleD'   => $this->fileData('testD.txt')
        ];

        $request = $this->factory(['files' => $files])->create();

        /** @var UploadedFileInterface[] $file */
        $file = $request->getUploadedFiles();
        $this->assertInstanceOf(UploadedFileInterface::class, $file['test']['multiple'][0]);
        $this->assertInstanceOf(UploadedFileInterface::class, $file['multipleC'][1]);
        $this->assertSame('testD.txt', $file['singleD']->getClientFilename());
    }

    public function testInvalidFileDataStructure_ThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->factory(['files' => ['field' => 'filename.txt']])->create();
    }

    private function factory(array $data = [])
    {
        return new ServerRequestFactory($data);
    }

    private function basicData()
    {
        return [
            'post'   => ['name' => 'post value'],
            'get'    => ['name' => 'get value'],
            'cookie' => ['cookie' => 'cookie value'],
            'server' => $this->serverContext(),
            'files'  => []
        ];
    }

    private function fileData($name)
    {
        $multi = is_array($name);
        $fill  = function ($value) use ($name) { return array_fill(0, count($name), $value); };

        return [
            'tmp_name' => $multi ? $fill('phpFOOBAR') : 'phpFOOBAR',
            'name'     => $name,
            'size'     => $multi ? $fill(10240) : 10240,
            'type'     => $multi ? $fill('text/plain') : 'text/plain',
            'error'    => $multi ? $fill(0) : 0
        ];
    }

    private function serverContext()
    {
        return [
            'SCRIPT_URL'           => '/',
            'SCRIPT_URI'           => 'http://server.local/',
            'HTTP_HOST'            => 'server.local',
            'HTTP_ACCEPT'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'pl,en;q=0.7,en-US;q=0.3',
            'HTTP_ACCEPT_ENCODING' => 'gzip, deflate',
            'HTTP_REFERER'         => 'http://server.local/',
            'HTTP_DNT'             => '1',
            'HTTP_CONNECTION'      => 'keep-alive',
            'HTTP_CACHE_CONTROL'   => 'max-age=0',
            'SERVER_NAME'          => 'server.local',
            'SERVER_ADDR'          => '127.0.0.1',
            'SERVER_PORT'          => '80',
            'REMOTE_ADDR'          => '127.0.0.1',
            'REQUEST_SCHEME'       => 'http',
            'REMOTE_PORT'          => '49847',
            'SERVER_PROTOCOL'      => 'HTTP/1.0',
            'REQUEST_METHOD'       => 'GET',
            'QUERY_STRING'         => '',
            'REQUEST_URI'          => '/',
            'SCRIPT_NAME'          => '/index.php',
            'PHP_SELF'             => '/index.php'
        ];
    }

    //TODO: parsed body use cases
}
