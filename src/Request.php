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

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;


class Request implements RequestInterface
{
    use RequestMethodsTrait;

    /**
     * @param string           $method  Normally one of the common methods defined by RFC 7231 section 4.3
     * @param UriInterface     $uri
     * @param ?StreamInterface $body
     * @param array            $headers Associative array of header strings or arrays of header strings
     * @param array            $params  Associative array with following keys and its default values
     *                                  when key is not present or its value is null:
     *                                  - version - http protocol version (default: '1.1')
     *                                  - target - request target (default: resolved from passed $uri param)
     *
     * @see https://tools.ietf.org/html/rfc7231#section-4.3
     */
    public function __construct(
        string $method,
        UriInterface $uri,
        ?StreamInterface $body = null,
        array $headers = [],
        array $params = []
    ) {
        $this->method  = $this->validMethod($method);
        $this->uri     = $uri;
        $this->body    = $body ?? Stream::fromBodyString('');
        $this->version = isset($params['version']) ? $this->validProtocolVersion($params['version']) : '1.1';
        $this->target  = isset($params['target']) ? $this->validRequestTarget($params['target']) : null;
        $this->loadHeaders($headers);
        $this->resolveHostHeader();
    }
}
