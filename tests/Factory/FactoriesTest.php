<?php

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message\Tests\Factory;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Polymorphine\Message\Factory\RequestFactory;
use Polymorphine\Message\Factory\ResponseFactory;
use Polymorphine\Message\Factory\ServerRequestFactory;
use Polymorphine\Message\Factory\StreamFactory;
use Polymorphine\Message\Factory\UploadedFileFactory;
use Polymorphine\Message\Factory\UriFactory;
use Polymorphine\Message\Request;
use Polymorphine\Message\Response;
use Polymorphine\Message\ServerRequest;
use Polymorphine\Message\Stream;
use Polymorphine\Message\Tests\Doubles\FakeStream;
use Polymorphine\Message\UploadedFile;
use Polymorphine\Message\Uri;
use RuntimeException;


class FactoriesTest extends TestCase
{
    public function testRequestFactory()
    {
        $factory = new RequestFactory();
        $this->assertInstanceOf(Request::class, $factory->createRequest('GET', 'http://example.com'));
    }

    public function testServerRequestFactory()
    {
        $factory = new ServerRequestFactory();
        $this->assertInstanceOf(ServerRequest::class, $factory->createServerRequest('POST', 'http://example.com'));
    }

    public function testResponseFactory()
    {
        $factory = new ResponseFactory();
        $this->assertInstanceOf(Response::class, $factory->createResponse());
    }

    public function testStreamFactory()
    {
        $factory = new StreamFactory();
        $this->assertInstanceOf(Stream::class, $factory->createStream('contents'));
        $this->assertInstanceOf(Stream::class, $factory->createStreamFromFile('php://temp'));
        $this->assertInstanceOf(Stream::class, $factory->createStreamFromResource(fopen('php://temp', 'w+b')));
    }

    public function testInvalidStreamMode_ThrowsException()
    {
        $factory = new StreamFactory();
        $this->expectException(InvalidArgumentException::class);
        $this->assertInstanceOf(Stream::class, $factory->createStreamFromFile('someFile.txt', 'invalid'));
    }

    public function testInvalidStreamFilename_ThrowsException()
    {
        $factory = new StreamFactory();
        $this->expectException(RuntimeException::class);
        $this->assertInstanceOf(Stream::class, $factory->createStreamFromFile('not-A-File.txt', 'r'));
    }

    public function testUploadedFileFactory()
    {
        $factory = new UploadedFileFactory();
        $this->assertInstanceOf(UploadedFile::class, $factory->createUploadedFile(new FakeStream()));
    }

    public function testUnreadableFileStream_ThrowsException()
    {
        $stream = new FakeStream();
        $stream->readable = false;

        $factory = new UploadedFileFactory();
        $this->expectException(InvalidArgumentException::class);
        $factory->createUploadedFile($stream);
    }

    public function testUriFactory()
    {
        $factory = new UriFactory();
        $this->assertInstanceOf(Uri::class, $factory->createUri('https://www.example.com'));
    }

    public function testMalformedUri_ThrowsException()
    {
        $factory = new UriFactory();
        $this->expectException(InvalidArgumentException::class);
        $factory->createUri('http:///example.com');
    }


}
