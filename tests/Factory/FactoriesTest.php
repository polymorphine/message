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

use PHPUnit\Framework\TestCase;
use Polymorphine\Message\Factory;
use Polymorphine\Message\Request;
use Polymorphine\Message\Response;
use Polymorphine\Message\ServerRequest;
use Polymorphine\Message\Stream;
use Polymorphine\Message\UploadedFile;
use Polymorphine\Message\NonSAPIUploadedFile;
use Polymorphine\Message\Tests\Doubles\FakeStream;
use Polymorphine\Message\Uri;
use InvalidArgumentException;
use RuntimeException;


class FactoriesTest extends TestCase
{
    public function testRequestFactory()
    {
        $factory = new Factory\RequestFactory();
        $this->assertInstanceOf(Request::class, $factory->createRequest('GET', 'http://example.com'));
    }

    public function testServerRequestFactory()
    {
        $factory = new Factory\ServerRequestFactory();
        $this->assertInstanceOf(ServerRequest::class, $factory->createServerRequest('POST', 'http://example.com'));
    }

    public function testResponseFactory()
    {
        $factory = new Factory\ResponseFactory();
        $this->assertInstanceOf(Response::class, $factory->createResponse());
    }

    public function testStreamFactory()
    {
        $factory = new Factory\StreamFactory();
        $this->assertInstanceOf(Stream::class, $factory->createStream('contents'));
        $this->assertInstanceOf(Stream::class, $factory->createStreamFromFile('php://temp'));
        $this->assertInstanceOf(Stream::class, $factory->createStreamFromResource(fopen('php://temp', 'w+b')));
    }

    public function testInvalidStreamMode_ThrowsException()
    {
        $factory = new Factory\StreamFactory();
        $this->expectException(InvalidArgumentException::class);
        $this->assertInstanceOf(Stream::class, $factory->createStreamFromFile('someFile.txt', 'invalid'));
    }

    public function testInvalidStreamFilename_ThrowsException()
    {
        $factory = new Factory\StreamFactory();
        $this->expectException(RuntimeException::class);
        $this->assertInstanceOf(Stream::class, $factory->createStreamFromFile('not-A-File.txt'));
    }

    public function testUploadedFileFactory()
    {
        $factory = new Factory\UploadedFileFactory();
        $this->assertInstanceOf(UploadedFile::class, $instance = $factory->createUploadedFile(new FakeStream()));
        $this->assertEquals(UploadedFile::class, get_class($instance));

        $factory = new Factory\UploadedFileFactory('apache2handler');
        $this->assertInstanceOf(UploadedFile::class, $instance = $factory->createUploadedFile(new FakeStream()));
        $this->assertEquals(UploadedFile::class, get_class($instance));

        $factory = new Factory\UploadedFileFactory('cli');
        $this->assertInstanceOf(UploadedFile::class, $instance = $factory->createUploadedFile(new FakeStream()));
        $this->assertEquals(NonSAPIUploadedFile::class, get_class($instance));
    }

    public function testUnreadableFileStream_ThrowsException()
    {
        $stream = new FakeStream();
        $stream->readable = false;

        $factory = new Factory\UploadedFileFactory();
        $this->expectException(InvalidArgumentException::class);
        $factory->createUploadedFile($stream);
    }

    public function testUriFactory()
    {
        $factory = new Factory\UriFactory();
        $this->assertInstanceOf(Uri::class, $factory->createUri('https://www.example.com'));
    }

    public function testMalformedUri_ThrowsException()
    {
        $factory = new Factory\UriFactory();
        $this->expectException(InvalidArgumentException::class);
        $factory->createUri('http:///example.com');
    }
}
