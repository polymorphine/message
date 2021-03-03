<?php declare(strict_types=1);

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
use Polymorphine\Message\ServerData;
use Polymorphine\Message\ServerRequest;
use Polymorphine\Message\Tests\Doubles\FakeUploadedFile;
use Polymorphine\Message\UploadedFile;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use InvalidArgumentException;

require_once __DIR__ . '/Fixtures/request-factory-functions.php';


class ServerDataTest extends TestCase
{
    public static ?array $nativeCallResult = null;

    public function tearDown(): void
    {
        self::$nativeCallResult = null;
    }

    public function testInstantiation()
    {
        $this->assertInstanceOf(ServerData::class, $this->serverData());
    }

    public function testBasicIntegration()
    {
        $data    = $this->basicData();
        $request = ServerRequest::fromServerData($this->serverData($data));

        $this->assertInstanceOf(ServerRequestInterface::class, $request);
        $this->assertSame($data['server'], $request->getServerParams());
        $this->assertSame($data['get'], $request->getQueryParams());
        $this->assertSame($data['post'], $request->getParsedBody());
        $this->assertSame($data['cookie'], $request->getCookieParams());
        $this->assertSame('1.0', $request->getProtocolVersion());
    }

    public function testOverridingSuperglobals()
    {
        $_POST   = ['name' => 'overwritten value', 'original' => 'original value'];
        $_GET    = ['name' => 'overwritten value'];
        $_COOKIE = ['cookie' => 'original cookie'];
        $data    = $this->basicData();
        $request = ServerRequest::fromGlobals($data);

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
    public function testNormalizedHeaderNamesFromServerArray($serverKey, $headerName)
    {
        $data = $this->serverData(['server' => [$serverKey => 'value']]);
        $this->assertTrue(ServerRequest::fromServerData($data)->hasHeader($headerName));
    }

    public function normalizeHeaderNames(): array
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
        $this->assertFalse(ServerRequest::fromServerData($this->serverData())->hasHeader('Authorization'));
        $data['server'] = ['HTTP_AUTHORIZATION' => 'value'];
        $this->assertTrue(ServerRequest::fromServerData($this->serverData($data))->hasHeader('Authorization'));
        self::$nativeCallResult = ['Authorization' => 'value'];
        $this->assertTrue(ServerRequest::fromServerData($this->serverData())->hasHeader('Authorization'));
        self::$nativeCallResult = ['authorization' => 'value'];
        $this->assertTrue(ServerRequest::fromServerData($this->serverData())->hasHeader('Authorization'));
        self::$nativeCallResult = ['AUTHORIZATION' => 'value'];
        $this->assertFalse(ServerRequest::fromServerData($this->serverData())->hasHeader('Authorization'));
    }

    public function testUploadedFileSuperGlobalParameterStructure()
    {
        $serverData = $this->serverData(['files' => ['test' => $this->fileData('avatar.png')]]);
        $request    = ServerRequest::fromServerData($serverData);
        $this->assertInstanceOf(UploadedFileInterface::class, $request->getUploadedFiles()['test']);
        $this->assertInstanceOf(UploadedFileInterface::class, $serverData->uploadedFiles()['test']);
    }

    public function testUploadedFileFileMultipleFileSuperGlobalParameterStructure()
    {
        $files['single'] = $this->fileData('avatar.png');
        $nested = [];
        foreach ($files['single'] as $name => $value) {
            $nested[$name] = [0 => $value, 'nested' => $value, 'multi-nested' => [0 => $value, 'sub-nested' => $value]];
        }
        $files['multi'] = $nested;

        $uploadedFiles = $this->serverData(['files' => $files])->uploadedFiles();

        $this->assertInstanceOf(UploadedFile::class, $uploadedFiles['single']);
        $this->assertInstanceOf(UploadedFile::class, $uploadedFiles['multi'][0]);
        $this->assertInstanceOf(UploadedFile::class, $uploadedFiles['multi']['nested']);
        $this->assertInstanceOf(UploadedFile::class, $uploadedFiles['multi']['multi-nested'][0]);
        $this->assertInstanceOf(UploadedFile::class, $uploadedFiles['multi']['multi-nested']['sub-nested']);
    }

    public function testUploadedFileInstancesInNestedStructureParameter()
    {
        $files = [
            'first'  => new FakeUploadedFile(),
            'second' => ['subcategory' => new FakeUploadedFile()]
        ];
        $request = ServerRequest::fromServerData($this->serverData(['files' => $files]));
        $this->assertSame($files, $request->getUploadedFiles());
    }

    public function testSingleUploadedFileStructure()
    {
        $files['test'] = $this->fileData('test.txt');
        $request = ServerRequest::fromServerData($this->serverData(['files' => $files]));

        /** @var UploadedFileInterface[] $file */
        $file = $request->getUploadedFiles();
        $this->assertInstanceOf(UploadedFileInterface::class, $file['test']);
        $this->assertSame('test.txt', $file['test']->getClientFilename());
    }

    public function testMultipleUploadedFileStructure()
    {
        $files['test'] = $this->fileData(['testA.txt', 'testB.txt']);
        $request = ServerRequest::fromServerData($this->serverData(['files' => $files]));

        /** @var UploadedFileInterface[][] $file */
        $file = $request->getUploadedFiles();
        $this->assertInstanceOf(UploadedFileInterface::class, $file['test'][0]);
        $this->assertSame('testB.txt', $file['test'][1]->getClientFilename());
    }

    public function testMixedStructureUploadedFiles()
    {
        $files = [
            'test'      => $this->fileData(['testA.txt', 'testB.txt']),
            'multipleC' => ['deep' => [new FakeUploadedFile(), new FakeUploadedFile()]],
            'singleD'   => $this->fileData('testD.txt')
        ];

        $request = ServerRequest::fromServerData($this->serverData(['files' => $files]));

        /** @var UploadedFileInterface[] $file */
        $file = $request->getUploadedFiles();
        $this->assertInstanceOf(UploadedFileInterface::class, $file['test'][0]);
        $this->assertInstanceOf(UploadedFileInterface::class, $file['multipleC']['deep'][1]);
        $this->assertSame('testD.txt', $file['singleD']->getClientFilename());
    }

    public function testInvalidFileDataStructure_ThrowsException()
    {
        $server = $this->serverData(['files' => ['field' => 'filename.txt']]);
        $this->expectException(InvalidArgumentException::class);
        $server->params();
    }

    private function serverData(array $data = []): ServerData
    {
        return new ServerData($data);
    }

    private function basicData(): array
    {
        return [
            'post'   => ['name' => 'post value'],
            'get'    => ['name' => 'get value'],
            'cookie' => ['cookie' => 'cookie value'],
            'server' => $this->serverContext(),
            'files'  => []
        ];
    }

    private function fileData($name): array
    {
        $multi = is_array($name);
        $fill  = function ($value) use ($name) { return array_fill(0, count($name), $value); };

        return [
            'tmp_name' => $multi ? $fill('php://temp') : 'php://temp',
            'name'     => $name,
            'size'     => $multi ? $fill(10240) : 10240,
            'type'     => $multi ? $fill('text/plain') : 'text/plain',
            'error'    => $multi ? $fill(0) : 0
        ];
    }

    private function serverContext(): array
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
}
