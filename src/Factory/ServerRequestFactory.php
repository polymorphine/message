<?php declare(strict_types=1);

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message\Factory;

use Polymorphine\Message\ServerRequest;
use Polymorphine\Message\Stream;
use Polymorphine\Message\Uri;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;


class ServerRequestFactory implements ServerRequestFactoryInterface
{
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        if (is_string($uri)) {
            $uri = Uri::fromString($uri);
        }

        $stream = Stream::fromResourceUri('php://temp');
        return new ServerRequest($method, $uri, $stream, [], ['server' => $serverParams]);
    }
}
