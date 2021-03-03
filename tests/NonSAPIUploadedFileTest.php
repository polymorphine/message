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
use Polymorphine\Message\NonSAPIUploadedFile;
use Polymorphine\Message\Tests\Doubles\FakeStream;
use RuntimeException;


class NonSAPIUploadedFileTest extends TestCase
{
    public function testInstantiation()
    {
        $this->assertInstanceOf(NonSAPIUploadedFile::class, new NonSAPIUploadedFile(new FakeStream()));
    }

    public function testFileIsMoved()
    {
        $source = tempnam(sys_get_temp_dir(), 'test');
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test.txt';
        $file   = new NonSAPIUploadedFile($stream = new FakeStream('content'));

        $stream->streamUri = $source;

        $this->assertTrue(file_exists($source));
        $this->assertFalse(file_exists($target));
        $file->moveTo($target);
        $this->assertFalse(file_exists($source));
        $this->assertTrue(file_exists($target));
        unlink($target);
    }

    public function testMoveToNotExistingPath_ThrowsException()
    {
        $file      = new NonSAPIUploadedFile(new FakeStream());
        $targetDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '/notExists';

        $this->assertFalse(is_dir($targetDir));
        $this->expectException(RuntimeException::class);
        $file->moveTo($targetDir . '/someFile.txt');
    }
}
