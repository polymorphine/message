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
use Polymorphine\Message\Stream;
use Psr\Http\Message\StreamInterface;
use InvalidArgumentException;
use RuntimeException;

require_once __DIR__ . '/Fixtures/stream-functions.php';


class StreamTest extends TestCase
{
    /**
     * @var bool Force error responses from native function calls
     */
    public static bool $overrideFunctions = false;

    protected ?StreamInterface $stream = null;
    protected string           $testFilename = '';

    public function tearDown(): void
    {
        self::$overrideFunctions = false;
        if ($this->stream) { $this->stream->close(); }
        if (file_exists($this->testFilename)) { unlink($this->testFilename); }
    }

    public function testInstantiateWithStreamName()
    {
        $this->assertInstanceOf(StreamInterface::class, Stream::fromResourceUri('php://memory', 'a+b'));
        $this->assertInstanceOf(StreamInterface::class, Stream::fromResourceUri('php://memory', 'w'));
    }

    public function testInstantiateWithStreamResource()
    {
        $this->assertInstanceOf(StreamInterface::class, (new Stream(fopen('php://input', 'r+b'))));
    }

    public function testNonResourceConstructorArgument_ThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        new Stream('http://example.com');
    }

    public function testNonStreamResourceConstructorArgument_ThrowsException()
    {
        self::$overrideFunctions = true;
        $this->expectException(InvalidArgumentException::class);
        $this->stream();
    }

    public function testInvalidStreamMode_ThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        Stream::fromResourceUri('someFile.txt', 'invalid');
    }

    /**
     * @dataProvider validModes
     *
     * @param $mode
     */
    public function testInvalidStreamReference_ThrowsException($mode)
    {
        $this->expectException(RuntimeException::class);
        Stream::fromResourceUri('php://someFile.txt', $mode);
    }

    public function validModes(): array
    {
        return [['w+b'], ['wb+'], ['xt+'], ['r+t'], ['cb+']];
    }

    /**
     * @dataProvider metaKeys
     *
     * @param $key
     * @param $type
     */
    public function testGetMetaData_ReturnCorrectValueTypes($key, $type)
    {
        $meta = $this->stream('php://memory')->getMetadata();
        $this->assertSame($type, gettype($meta[$key]));
        $meta = $this->stream('php://memory')->getMetadata($key);
        $this->assertSame($type, gettype($meta));
    }

    public function metaKeys(): array
    {
        return [
            ['timed_out', 'boolean'],
            ['blocked', 'boolean'],
            ['eof', 'boolean'],
            ['unread_bytes', 'integer'],
            ['stream_type', 'string'],
            ['wrapper_type', 'string'],
            ['mode', 'string'],
            ['seekable', 'boolean'],
            ['uri', 'string']
        ];
    }

    public function testGetMetadataReturnsNullIfNoDataExistsForKey()
    {
        $this->assertNull($this->stream()->getMetadata('no_such_key'));
    }

    public function testDetachedStreamProperties()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isSeekable());
        $this->assertFalse($stream->isWritable());
        $this->assertSame([], $stream->getMetadata());
        $this->assertNull($stream->getMetadata('uri'));
    }

    public function testTell_ReturnsPointerPosition()
    {
        $this->assertSame(0, $this->stream(null, 'r')->tell());
        $this->assertSame(5, $this->streamWithPredefinedConditions('Hello World!', 5)->tell());
    }

    public function testTellDetachedStream_ThrowsException()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->expectException(RuntimeException::class);
        $stream->tell();
    }

    public function testTellError_ThrowsException()
    {
        $stream = $this->stream();

        self::$overrideFunctions = true;
        $this->expectException(RuntimeException::class);
        $stream->tell();
    }

    public function testSeekMovesPointerPosition()
    {
        $stream = $this->streamWithPredefinedConditions('Hello World!', 0);
        $this->assertSame(0, $stream->tell());
        $stream->seek(5);
        $this->assertSame(5, $stream->tell());
    }

    public function testSeekWhenceBehavior()
    {
        $stream = $this->streamWithPredefinedConditions('Hello World!', 3);
        $stream->seek(6);
        $this->assertSame(6, $stream->tell(), 'SEEK_SET offset resolves into absolute position');

        $stream = $this->streamWithPredefinedConditions('Hello World!', 3);
        $stream->seek(6, SEEK_CUR);
        $this->assertSame(9, $stream->tell(), 'SEEK_CUR offset resolves into position relative to current');

        $stream = $this->streamWithPredefinedConditions('Hello World!', 3);
        $stream->seek(-3, SEEK_END);
        $this->assertSame(9, $stream->tell(), 'SEEK_END offset resolves into position relative to end of stream');
    }

    public function testSeekNotSeekableStream_ThrowsException()
    {
        $stream = $this->stream('php://output', 'a');
        $this->assertFalse($stream->isSeekable());
        $this->expectException(RuntimeException::class);
        $stream->seek(1);
    }

    public function testSeekDetachedStream_ThrowsException()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->expectException(RuntimeException::class);
        $stream->seek(1);
    }

    public function testSeekError_ThrowsException()
    {
        $stream = $this->stream();
        $this->expectException(RuntimeException::class);
        $stream->seek(-1);
    }

    public function testRewindMovesPointerToBeginningOfTheStream()
    {
        $stream = $this->streamWithPredefinedConditions('Hello World!', 4);
        $stream->rewind();
        $this->assertSame(0, $stream->tell());
    }

    public function testRewindNotSeekableStream_ThrowsException()
    {
        $stream = $this->stream('php://output', 'a');
        $this->assertFalse($stream->isSeekable());
        $this->expectException(RuntimeException::class);
        $stream->rewind();
    }

    public function testRewindDetachedStream_ThrowsException()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->expectException(RuntimeException::class);
        $stream->rewind();
    }

    public function testGetSize_ReturnsSizeOfStream()
    {
        $this->assertSame(12, $this->streamWithPredefinedConditions('Hello World!', 0)->getSize());
        $this->assertSame(0, $this->stream(null, 'w+')->getSize());
    }

    public function testGetSizeOnDetachedResource_ReturnsNull()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->assertNull($stream->getSize());
    }

    public function testReadGetsDataFromStream()
    {
        $string = 'Hello World!';
        $stream = $this->streamWithPredefinedConditions($string, 6);
        $this->assertSame('World', $stream->read(5));
    }

    public function testReadUnreadableStream_ThrowsException()
    {
        $stream = $this->fileStream('w');
        $this->assertFalse($stream->isReadable());
        $this->expectException(RuntimeException::class);
        $stream->read(1);
    }

    public function testReadDetachedStream_ThrowsException()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->expectException(RuntimeException::class);
        $stream->read(1);
    }

    public function testReadError_ThrowsException()
    {
        $stream = $this->stream(null, 'w+b');

        self::$overrideFunctions = true;
        $this->expectException(RuntimeException::class);
        $stream->read(1);
    }

    public function testGetContents_ReturnsRemainingStreamContents()
    {
        $string = 'Hello World!';
        $stream = $this->streamWithPredefinedConditions($string, 6);
        $this->assertSame('World!', $stream->getContents());
    }

    public function testGetContentsOnUnreadableStream_ThrowsException()
    {
        $stream = $this->fileStream('w');
        $this->assertFalse($stream->isReadable());
        $this->expectException(RuntimeException::class);
        $stream->getContents();
    }

    public function testGetContentsFromDetachedStream_ThrowsException()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->expectException(RuntimeException::class);
        $stream->getContents();
    }

    public function testGetContentsError_ThrowsException()
    {
        $stream = $this->streamWithPredefinedConditions('Hello World!', 0);

        self::$overrideFunctions = true;
        $this->expectException(RuntimeException::class);
        $stream->getContents();
    }

    public function testEofOnRead()
    {
        $stream = $this->streamWithPredefinedConditions('hello world!', 11);
        $stream->read(1);
        $this->assertFalse($stream->eof());
        $stream->read(1);
        $this->assertTrue($stream->eof());
        $stream->seek(6);
        $this->assertFalse($stream->eof());
        $stream->getContents();
        $this->assertTrue($stream->eof());
    }

    public function testEofOnDetachedStream_ReturnsTrue()
    {
        $stream = $this->stream();
        $stream->detach();
        $this->assertTrue($stream->eof());
    }

    public function testWriteSendsDataToStream()
    {
        $stream = $this->stream(null, 'w+b');
        $data   = 'Hello World!';
        $stream->write($data);
        $this->assertSame($data, (string) $stream);
    }

    public function testWriteNotWritableStream_ThrowsException()
    {
        $stream = $this->stream();
        $this->assertFalse($stream->isWritable());
        $this->expectException(RuntimeException::class);
        $stream->write('hello world!');
    }

    public function testWriteIntoDetachedStream_ThrowsException()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->expectException(RuntimeException::class);
        $stream->write('hello world!');
    }

    public function testErrorOnWrite_ThrowsException()
    {
        $stream = $this->stream(null, 'w+b');

        self::$overrideFunctions = true;
        $this->expectException(RuntimeException::class);
        $stream->write('Hello World!');
    }

    public function testWrittenDataIsEqualToReadData()
    {
        $string = 'Hello World!';
        $stream = $this->stream(null, 'w+');
        $stream->write($string);
        $stream->rewind();
        $this->assertSame($string, $stream->read(strlen($string)));
        $stream->rewind();
        $this->assertSame($string, $stream->getContents());
        $this->assertSame($string, (string) $stream);
    }

    public function testToString_ReturnsFullStreamContents()
    {
        $string = 'Hello World!';
        $stream = $this->streamWithPredefinedConditions($string, 6);
        $this->assertSame($string, (string) $stream);
    }

    public function testToStringOnUnreadableStream_ReturnsEmptyString()
    {
        $stream = $this->fileStream('a', 'Hello World');
        $this->assertSame('', (string) $stream);
    }

    public function testToStringOnNotSeekableStream_ReturnsEmptyString()
    {
        $stream = $this->stream('php://output', 'a');
        $this->assertFalse($stream->isSeekable());
        $this->assertSame('', (string) $stream);
    }

    public function testWhenErrorOccurs_ToStringReturnsEmptyString()
    {
        $stream = $this->streamWithPredefinedConditions('Hello World!', 6);

        self::$overrideFunctions = true;
        $this->assertSame('', (string) $stream);
    }

    public function testInstantiateWithStringBody()
    {
        $stream = Stream::fromBodyString('Hello World!');
        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertSame('Hello', $stream->read(5));
    }

    private function stream($resource = null, $mode = null): Stream
    {
        $resource = $resource ?? 'php://memory';

        return $this->stream = is_resource($resource)
            ? new Stream($resource)
            : Stream::fromResourceUri($resource, $mode);
    }

    private function fileStream($mode = null, string $contents = ''): Stream
    {
        $this->testFilename = tempnam(sys_get_temp_dir(), 'test');
        if ($contents) { file_put_contents($this->testFilename, $contents); }

        return $this->stream($this->testFilename, $mode);
    }

    private function streamWithPredefinedConditions($contents, $position): Stream
    {
        $resource = fopen('php://memory', 'w+');
        fwrite($resource, $contents);
        fseek($resource, $position);

        return $this->stream($resource);
    }
}
