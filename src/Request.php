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

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;


class Request implements RequestInterface
{
    use RequestMethodsTrait;

    public function __construct(
        string $method,
        UriInterface $uri,
        StreamInterface $body,
        array $headers = [],
        array $params = []
    ) {
        $this->method  = $this->validMethod($method);
        $this->uri     = $uri;
        $this->body    = $body;
        $this->version = isset($params['version']) ? $this->validProtocolVersion($params['version']) : '1.1';
        $this->target  = isset($params['target']) ? $this->validRequestTarget($params['target']) : null;
        $this->loadHeaders($headers);
        $this->resolveHostHeader();
    }
}
