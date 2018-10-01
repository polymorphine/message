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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use InvalidArgumentException;


class Response implements ResponseInterface
{
    use Response\StatusCodesTrait;
    use MessageMethodsTrait;

    private $status;
    private $reason;

    public function __construct(
        int $statusCode,
        StreamInterface $body,
        array $headers = [],
        array $params = []
    ) {
        $this->status  = $this->validStatusCode($statusCode);
        $this->body    = $body;
        $this->reason  = $this->validReasonPhrase($params['reason'] ?? '');
        $this->version = isset($params['version']) ? $this->validProtocolVersion($params['version']) : '1.1';
        $this->loadHeaders($headers);
    }

    public function getStatusCode()
    {
        return $this->status;
    }

    public function withStatus($code, $reasonPhrase = '')
    {
        $clone = clone $this;
        $clone->status = $this->validStatusCode($code);
        $clone->reason = $clone->validReasonPhrase($reasonPhrase);

        return $clone;
    }

    public function getReasonPhrase()
    {
        return $this->reason;
    }

    private function validStatusCode($code)
    {
        if (!is_int($code) || $code < 100 || $code >= 600) {
            throw new InvalidArgumentException('Invalid status code argument - integer <100-599> expected');
        }
        return $code;
    }

    private function validReasonPhrase($reason)
    {
        if (!is_string($reason)) {
            throw new InvalidArgumentException('Invalid HTTP ResponseHeaders reason phrase - string expected');
        }
        return $this->resolveReasonPhrase($reason);
    }

    private function resolveReasonPhrase($reason = '')
    {
        if (empty($reason) && isset($this->statusCodes[$this->status])) {
            $reason = $this->statusCodes[$this->status];
        }
        return $reason;
    }
}
