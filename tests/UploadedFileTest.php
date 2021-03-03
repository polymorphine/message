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
use Polymorphine\Message\UploadedFile;
use Polymorphine\Message\Tests\Doubles\FakeStream;
use Psr\Http\Message\StreamInterface;
use InvalidArgumentException;
use RuntimeException;

require_once __DIR__ . '/Fixtures/uploaded-file-functions.php';


class UploadedFileTest extends TestCase
{
    public static bool $errorOnMove = false;

    private ?string $tempFile  = null;
    private ?string $movedFile = null;

    public function tearDown(): void
    {
        if (is_file($this->tempFile)) { unlink($this->tempFile); }
        if (is_file($this->movedFile)) { unlink($this->movedFile); }
        $this->tempFile  = null;
        $this->movedFile = null;

        self::$errorOnMove = false;
    }

    public function testCreatingValidFile()
    {
        $file = $this->file(['name' => 'test.txt', 'size' => 8]);
        $this->assertSame(UPLOAD_ERR_OK, $file->getError());
        $this->assertSame('test.txt', $file->getClientFilename());
        $this->assertSame(8, $file->getSize());
        $this->assertSame('text/plain', $file->getClientMediaType());
    }

    public function testUnreadableFileStream_ThrowsException()
    {
        $stream = new FakeStream();
        $stream->readable = false;
        $this->expectException(InvalidArgumentException::class);
        new UploadedFile($stream);
    }

    public function testInvalidErrorCode_ThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        new UploadedFile(new FakeStream(), 0, 10);
    }

    public function testFileIsMoved()
    {
        $file   = $this->file([], true);
        $source = $this->tempFile;
        $target = $this->targetPath();

        $this->assertTrue(file_exists($source));
        $this->assertFalse(file_exists($target));
        $file->moveTo($target);
        $this->assertFalse(file_exists($source));
        $this->assertTrue(file_exists($target));
    }

    public function testMoveFileWithUploadError_ThrowsException()
    {
        $file = $this->file(['error' => UPLOAD_ERR_EXTENSION]);
        $this->expectException(RuntimeException::class);
        $file->moveTo($this->targetPath());
    }

    public function testMoveAlreadyMovedFile_ThrowsException()
    {
        $file = $this->file();
        $file->moveTo($this->targetPath());
        $this->expectException(RuntimeException::class);
        $file->moveTo($this->targetPath());
    }

    public function testMoveWithInvalidTargetPath_ThrowsException()
    {
        $file = $this->file();
        $this->expectException(InvalidArgumentException::class);
        $file->moveTo(123);
    }

    public function testMoveForDetachedStream_ThrowsException()
    {
        $target = $this->targetPath();
        $file   = new UploadedFile(new FakeStream());
        $file->moveTo($target);

        $file = new UploadedFile(new FakeStream());
        $file->getStream()->close();
        $this->expectException(RuntimeException::class);
        $file->moveTo($target);
    }

    public function testFileMoveError_ThrowsException()
    {
        self::$errorOnMove = true;

        $file = $this->file();
        $this->expectException(RuntimeException::class);
        $file->moveTo($this->targetPath());
    }

    public function testGetStream_ReturnsStreamInterfaceInstance()
    {
        $file = $this->file();
        $this->assertInstanceOf(StreamInterface::class, $file->getStream());
    }

    public function testGetStreamFromUploadedWithError_ThrowsException()
    {
        $file = $this->file(['error' => UPLOAD_ERR_EXTENSION]);
        $this->expectException(RuntimeException::class);
        $file->getStream();
    }

    public function testGetStreamFromMovedFile_ThrowsException()
    {
        $file = $this->file();
        $file->moveTo($this->targetPath());
        $this->expectException(RuntimeException::class);
        $file->getStream();
    }

    private function file(array $data = [], bool $realFile = false): UploadedFile
    {
        $this->tempFile = $realFile
            ? tempnam(sys_get_temp_dir(), 'test')
            : 'php://temp';

        return UploadedFile::fromFileArray(['tmp_name' => $this->tempFile] + $data + [
            'size'  => 10,
            'error' => UPLOAD_ERR_OK,
            'name'  => 'clientName.txt',
            'type'  => 'text/plain'
        ]);
    }

    private function targetPath($name = 'test.txt'): string
    {
        return $this->movedFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name;
    }
}
