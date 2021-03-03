<?php declare(strict_types=1);

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message;

use RuntimeException;


class NonSAPIUploadedFile extends UploadedFile
{
    protected function moveFile($source, $target): void
    {
        $directory = dirname($target);
        if (!is_dir($directory) || !is_writeable($directory)) {
            throw new RuntimeException('Cannot write into target directory');
        }

        $targetStream = Stream::fromResourceUri($target, 'w+b');

        $this->stream->rewind();
        while (!$this->stream->eof()) {
            $targetStream->write($this->stream->read(4096));
        }

        $targetStream->close();
        $this->stream->close();

        if (is_file($source)) { unlink($source); }
    }
}
