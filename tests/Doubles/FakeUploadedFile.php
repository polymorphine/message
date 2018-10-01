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


class FakeUploadedFile implements UploadedFileInterface
{
    public function getStream()
    {
    }

    public function moveTo($targetPath)
    {
    }

    public function getSize()
    {
    }

    public function getError()
    {
    }

    public function getClientFilename()
    {
    }

    public function getClientMediaType()
    {
    }
}
