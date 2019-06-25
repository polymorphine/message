<?php

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message;

use Polymorphine\Message\Tests\UploadedFileTest as TestConfig;

function move_uploaded_file($filename, $destination)
{
    if (TestConfig::$errorOnMove) { return false; }

    $result = copy($filename, $destination);
    if (is_file($filename)) { unlink($filename); }
    return $result;
}
