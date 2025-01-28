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

    protected function tearDown(): void
    {
        self::$overrideFunctions = false;
        if ($this->stream) { $this->stream->close(); }
        if (file_exists($this->testFilename)) { unlink($this->testFilename); }
    }

    public function test_Instantiation_WithStreamName()
    {
        $this->assertInstanceOf(StreamInterface::class, Stream::fromResourceUri('php://memory', 'a+b'));
        $this->assertInstanceOf(StreamInterface::class, Stream::fromResourceUri('php://memory', 'w'));
    }

    public function test_Instantiation_WithStreamResource()
    {
        $this->assertInstanceOf(StreamInterface::class, new Stream(fopen('php://input', 'r+b')));
    }

    public function test_NonResourceConstructorArgument_ThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        new Stream('http://example.com');
    }

    public function test_NonStreamResourceConstructorArgument_ThrowsException()
    {
        self::$overrideFunctions = true;
        $this->expectException(InvalidArgumentException::class);
        $this->stream();
    }

    public function test_InvalidStreamMode_ThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        Stream::fromResourceUri('someFile.txt', 'invalid');
    }

    /** @dataProvider validModes */
    public function test_InvalidStreamReference_ThrowsException(string $mode)
    {
        $this->expectException(RuntimeException::class);
        Stream::fromResourceUri('php://someFile.txt', $mode);
    }

    /** @dataProvider metaKeys */
    public function test_GetMetaData_ReturnCorrectValueTypes(string $key, string $type)
    {
        $meta = $this->stream('php://memory')->getMetadata();
        $this->assertSame($type, gettype($meta[$key]));
        $meta = $this->stream('php://memory')->getMetadata($key);
        $this->assertSame($type, gettype($meta));
    }

    public function test_GetMetadata_ForNotExistingKey_ReturnsNull()
    {
        $this->assertNull($this->stream()->getMetadata('no_such_key'));
    }

    public function test_DetachedStreamProperties()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isSeekable());
        $this->assertFalse($stream->isWritable());
        $this->assertSame([], $stream->getMetadata());
        $this->assertNull($stream->getMetadata('uri'));
    }

    public function test_Tell_ReturnsPointerPosition()
    {
        $this->assertSame(0, $this->stream(null, 'r')->tell());
        $this->assertSame(5, $this->streamWithPredefinedConditions('Hello World!', 5)->tell());
    }

    public function test_Tell_DetachedStream_ThrowsException()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->expectException(RuntimeException::class);
        $stream->tell();
    }

    public function test_Tell_OnError_ThrowsException()
    {
        $stream = $this->stream();

        self::$overrideFunctions = true;
        $this->expectException(RuntimeException::class);
        $stream->tell();
    }

    public function test_Seek_MovesPointerPosition()
    {
        $stream = $this->streamWithPredefinedConditions('Hello World!', 0);
        $this->assertSame(0, $stream->tell());
        $stream->seek(5);
        $this->assertSame(5, $stream->tell());
    }

    public function test_Seek_WhenceBehavior()
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

    public function test_Seek_NotSeekableStream_ThrowsException()
    {
        $stream = $this->stream('php://output', 'a');
        $this->assertFalse($stream->isSeekable());
        $this->expectException(RuntimeException::class);
        $stream->seek(1);
    }

    public function test_Seek_DetachedStream_ThrowsException()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->expectException(RuntimeException::class);
        $stream->seek(1);
    }

    public function test_Seek_OnError_ThrowsException()
    {
        $stream = $this->stream();
        $this->expectException(RuntimeException::class);
        $stream->seek(-1);
    }

    public function test_Rewind_MovesPointerToBeginningOfTheStream()
    {
        $stream = $this->streamWithPredefinedConditions('Hello World!', 4);
        $stream->rewind();
        $this->assertSame(0, $stream->tell());
    }

    public function test_Rewind_NotSeekableStream_ThrowsException()
    {
        $stream = $this->stream('php://output', 'a');
        $this->assertFalse($stream->isSeekable());
        $this->expectException(RuntimeException::class);
        $stream->rewind();
    }

    public function test_Rewind_DetachedStream_ThrowsException()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->expectException(RuntimeException::class);
        $stream->rewind();
    }

    public function test_GetSize_ReturnsSizeOfStream()
    {
        $this->assertSame(12, $this->streamWithPredefinedConditions('Hello World!', 0)->getSize());
        $this->assertSame(0, $this->stream(null, 'w+')->getSize());
    }

    public function test_GetSize_OnDetachedResource_ReturnsNull()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->assertNull($stream->getSize());
    }

    public function test_Read_GetsDataFromStream()
    {
        $string = 'Hello World!';
        $stream = $this->streamWithPredefinedConditions($string, 6);
        $this->assertSame('World', $stream->read(5));
    }

    public function test_Read_UnreadableStream_ThrowsException()
    {
        $stream = $this->fileStream('w');
        $this->assertFalse($stream->isReadable());
        $this->expectException(RuntimeException::class);
        $stream->read(1);
    }

    public function test_Read_DetachedStream_ThrowsException()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->expectException(RuntimeException::class);
        $stream->read(1);
    }

    public function test_ReadError_ThrowsException()
    {
        $stream = $this->stream(null, 'w+b');

        self::$overrideFunctions = true;
        $this->expectException(RuntimeException::class);
        $stream->read(1);
    }

    public function test_GetContents_ReturnsRemainingStreamContents()
    {
        $string = 'Hello World!';
        $stream = $this->streamWithPredefinedConditions($string, 6);
        $this->assertSame('World!', $stream->getContents());
    }

    public function test_GetContents_OnUnreadableStream_ThrowsException()
    {
        $stream = $this->fileStream('w');
        $this->assertFalse($stream->isReadable());
        $this->expectException(RuntimeException::class);
        $stream->getContents();
    }

    public function test_GetContents_FromDetachedStream_ThrowsException()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->expectException(RuntimeException::class);
        $stream->getContents();
    }

    public function test_GetContentsError_ThrowsException()
    {
        $stream = $this->streamWithPredefinedConditions('Hello World!', 0);

        self::$overrideFunctions = true;
        $this->expectException(RuntimeException::class);
        $stream->getContents();
    }

    public function test_Eof_OnRead()
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

    public function test_Eof_OnDetachedStream_ReturnsTrue()
    {
        $stream = $this->stream();
        $stream->detach();
        $this->assertTrue($stream->eof());
    }

    public function test_Write_SendsDataToStream()
    {
        $stream = $this->stream(null, 'w+b');
        $data   = 'Hello World!';
        $stream->write($data);
        $this->assertSame($data, (string) $stream);
    }

    public function test_Write_NotWritableStream_ThrowsException()
    {
        $stream = $this->stream();
        $this->assertFalse($stream->isWritable());
        $this->expectException(RuntimeException::class);
        $stream->write('hello world!');
    }

    public function test_Write_IntoDetachedStream_ThrowsException()
    {
        $stream = $this->stream();
        fclose($stream->detach());
        $this->expectException(RuntimeException::class);
        $stream->write('hello world!');
    }

    public function test_WriteError_ThrowsException()
    {
        $stream = $this->stream(null, 'w+b');

        self::$overrideFunctions = true;
        $this->expectException(RuntimeException::class);
        $stream->write('Hello World!');
    }

    public function test_WrittenDataIsEqualToReadData()
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

    public function test_ToString_ReturnsFullStreamContents()
    {
        $string = 'Hello World!';
        $stream = $this->streamWithPredefinedConditions($string, 6);
        $this->assertSame($string, (string) $stream);
    }

    public function test_ToString_OnUnreadableStream_ReturnsEmptyString()
    {
        $stream = $this->fileStream('a', 'Hello World');
        $this->assertSame('', (string) $stream);
    }

    public function test_ToString_OnNotSeekableStream_ReturnsEmptyString()
    {
        $stream = $this->stream('php://output', 'a');
        $this->assertFalse($stream->isSeekable());
        $this->assertSame('', (string) $stream);
    }

    public function test_ToString_WhenErrorOccurs_ReturnsEmptyString()
    {
        $stream = $this->streamWithPredefinedConditions('Hello World!', 6);

        self::$overrideFunctions = true;
        $this->assertSame('', (string) $stream);
    }

    public function test_Instantiation_WithStringBody()
    {
        $stream = Stream::fromBodyString('Hello World!');
        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertSame('Hello', $stream->read(5));
    }

    public static function validModes(): iterable
    {
        return [['w+b'], ['wb+'], ['xt+'], ['r+t'], ['cb+']];
    }

    public static function metaKeys(): iterable
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

    private function stream($resource = null, $mode = null): Stream
    {
        $resource = $resource ?? 'php://memory';

        return $this->stream = is_resource($resource)
            ? new Stream($resource)
            : Stream::fromResourceUri($resource, $mode ?? 'r');
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
