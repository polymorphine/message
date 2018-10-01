<?php

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message\Tests\Fixtures;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;
use Polymorphine\Message\MessageMethodsTrait;


class MessageMethodsClass implements MessageInterface
{
    use MessageMethodsTrait;

    public function __construct(StreamInterface $body, array $headers, $version = '1.1')
    {
        $this->body    = $body;
        $this->version = $this->validProtocolVersion($version);
        $this->loadHeaders($headers);
    }
}
