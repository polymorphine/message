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

use Polymorphine\Message\Tests\ServerDataTest as Factory;

function apache_request_headers(): array
{
    return Factory::$nativeCallResult ?? [];
}

function function_exists($name): bool
{
    return Factory::$nativeCallResult ? true : \function_exists($name);
}
