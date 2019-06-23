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
    public static $forceNativeFunctionErrors = false;

    private $testFilename;
    private $movedFilename;

    public function tearDown()
    {
        if (file_exists($this->testFilename)) { unlink($this->testFilename); }
        if (file_exists($this->movedFilename)) {
            unlink($this->movedFilename);
        }
        $this->testFilename  = null;
        $this->movedFilename = null;

        self::$forceNativeFunctionErrors = false;
    }

    public function testCreatingValidFile()
    {
        $file = $this->file('contents', ['name' => 'test.txt']);
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
        $file   = $this->file('empty');
        $target = $this->targetPath();
        $this->assertFalse(file_exists($target));
        $file->moveTo($target);
        $this->assertTrue(file_exists($target));
    }

    public function testMoveFileWithUploadError_ThrowsException()
    {
        $file = $this->file('', ['error' => UPLOAD_ERR_EXTENSION]);
        $this->expectException(RuntimeException::class);
        $file->moveTo($this->targetPath());
    }

    public function testMoveAlreadyMovedFile_ThrowsException()
    {
        $file = $this->file();
        $file->moveTo($this->targetPath());
        $this->expectException(RuntimeException::class);
        $file->moveTo(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'file.txt');
    }

    public function testFileMoveError_ThrowsException()
    {
        self::$forceNativeFunctionErrors = true;

        $file = $this->file();
        $this->expectException(RuntimeException::class);
        $file->moveTo($this->targetPath());
    }

    public function testGetStream_ReturnsStreamInterfaceInstance()
    {
        $file = $this->file();
        $this->assertInstanceOf(StreamInterface::class, $file->getStream());
    }

    public function testGetSreamFromUploadedWithError_ThrowsException()
    {
        $file = $this->file('', ['error' => UPLOAD_ERR_EXTENSION]);
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

    private function file($contents = '', array $data = [])
    {
        if (!isset($this->testFilename)) {
            $this->testFilename = tempnam(sys_get_temp_dir(), 'test');
        }

        if ($contents) { file_put_contents($this->testFilename, $contents); }

        $fileData = [
            'tmp_name' => $this->testFilename,
            'size'     => strlen($contents),
            'error'    => UPLOAD_ERR_OK,
            'name'     => 'clientName.txt',
            'type'     => 'text/plain'
        ];

        $_FILES['test'] = $data + $fileData;

        return UploadedFile::fromFileArray($_FILES['test']);
    }

    private function targetPath($name = 'test.txt')
    {
        return $this->movedFilename = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name;
    }
}
