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

use Polymorphine\Message\Tests\StreamTest;

function fread($resource, $count)
{
    return StreamTest::$overrideFunctions ? false : \fread($resource, $count);
}

function fwrite($resource, $contents)
{
    return StreamTest::$overrideFunctions ? false : \fwrite($resource, $contents);
}

function ftell($resource)
{
    return StreamTest::$overrideFunctions ? false : \ftell($resource);
}

function stream_get_contents($resource)
{
    return StreamTest::$overrideFunctions ? false : \stream_get_contents($resource);
}

function get_resource_type($resource)
{
    return StreamTest::$overrideFunctions ? 'not-stream' : \get_resource_type($resource);
}
