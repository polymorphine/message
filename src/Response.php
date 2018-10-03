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

    /**
     * @param string $text
     * @param int    $statusCode
     *
     * @return ResponseInterface
     */
    public static function text(string $text, int $statusCode = 200)
    {
        return new self($statusCode, Stream::fromBodyString($text), ['Content-Type' => 'text/plain']);
    }

    /**
     * @param string $html
     * @param int    $statusCode
     *
     * @return ResponseInterface
     */
    public static function html(string $html, int $statusCode = 200)
    {
        return new self($statusCode, Stream::fromBodyString($html), ['Content-Type' => 'text/html']);
    }

    /**
     * @param string $xml
     * @param int    $statusCode
     *
     * @return ResponseInterface
     */
    public static function xml(string $xml, int $statusCode = 200)
    {
        return new self($statusCode, Stream::fromBodyString($xml), ['Content-Type' => 'application/xml']);
    }

    /**
     * There's a XOR operator between $defaultEncode and $encodeOptions,
     * which means that if option is set in both provided and default it
     * will be switched off.
     *
     * @param array $data
     * @param int   $statusCode
     * @param int   $encodeOptions
     *
     * @return ResponseInterface
     */
    public static function json(array $data, int $statusCode = 200, $encodeOptions = 0)
    {
        $defaultEncode = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
        $serialized    = json_encode($data, $defaultEncode ^ $encodeOptions);

        return new self($statusCode, Stream::fromBodyString($serialized), ['Content-Type' => 'application/json']);
    }

    public static function redirect($uri, int $status = 303)
    {
        if ($status < 300 || $status > 399) {
            throw new InvalidArgumentException('Invalid status code for redirect response');
        }
        return new self($status, new Stream(fopen('php://temp', 'r')), ['Location' => (string) $uri]);
    }

    public static function notFound(StreamInterface $body = null)
    {
        return new self(404, $body ?: Stream::fromResourceUri('php://temp'));
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
