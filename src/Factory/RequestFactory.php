<?php

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message\Factory;

use Polymorphine\Message\Request;
use Polymorphine\Message\Stream;
use Polymorphine\Message\Uri;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;


class RequestFactory implements RequestFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        if (is_string($uri)) {
            $uri = Uri::fromString($uri);
        }

        $stream = Stream::fromResourceUri('php://temp', 'w+b');
        return new Request($method, $uri, $stream);
    }
}
