<?php

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message\Tests\Doubles;

use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\StreamInterface;


class FakeUploadedFile implements UploadedFileInterface
{
    public function getStream(): StreamInterface
    {
        return new FakeStream();
    }

    public function moveTo($targetPath): void
    {
    }

    public function getSize(): ?int
    {
        return 0;
    }

    public function getError(): int
    {
        return 0;
    }

    public function getClientFilename(): ?string
    {
        return null;
    }

    public function getClientMediaType(): ?string
    {
        return null;
    }
}
