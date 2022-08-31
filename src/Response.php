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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use InvalidArgumentException;


class Response implements ResponseInterface
{
    use StatusCodesTrait;
    use MessageMethodsTrait;

    private int    $status;
    private string $reason;

    /**
     * @param int              $statusCode Normally one of the status codes defined by RFC 7231 section 6
     * @param ?StreamInterface $body
     * @param array            $headers    Associative array of header strings or arrays of header strings
     * @param array            $params     Associative array with following keys and its default values
     *                                     when key is not present or its value is null:
     *                                     - version - http protocol version (default: '1.1')
     *                                     - reason - reason phrase normally associated with $statusCode, so by
     *                                     default it will be resolved from it.
     *
     * @see https://tools.ietf.org/html/rfc7231#section-6
     * @see StatusCodesTrait
     */
    public function __construct(
        int $statusCode,
        ?StreamInterface $body = null,
        array $headers = [],
        array $params = []
    ) {
        $this->status  = $this->validStatusCode($statusCode);
        $this->body    = $body ?? Stream::fromBodyString('');
        $this->reason  = $this->validReasonPhrase($params['reason'] ?? '');
        $this->version = isset($params['version']) ? $this->validProtocolVersion($params['version']) : '1.1';
        $this->loadHeaders($headers);
    }

    public static function text(string $text, int $statusCode = 200): self
    {
        return new self($statusCode, Stream::fromBodyString($text), ['Content-Type' => 'text/plain']);
    }

    public static function html(string $html, int $statusCode = 200): self
    {
        return new self($statusCode, Stream::fromBodyString($html), ['Content-Type' => 'text/html']);
    }

    public static function xml(string $xml, int $statusCode = 200): self
    {
        return new self($statusCode, Stream::fromBodyString($xml), ['Content-Type' => 'application/xml']);
    }

    /**
     * There's a XOR operator between $defaultOptions and $encodeOptions,
     * which means that if an option is set in both provided and default it
     * will be switched off.
     *
     * @param array $data
     * @param int   $statusCode
     * @param int   $encodeOptions
     *
     * @return self
     */
    public static function json(array $data, int $statusCode = 200, int $encodeOptions = 0): self
    {
        $defaultOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
        $serialized     = $data ? json_encode($data, $defaultOptions ^ $encodeOptions) : '{}';

        return new self($statusCode, Stream::fromBodyString($serialized), ['Content-Type' => 'application/json']);
    }

    public static function redirect($uri, int $status = 303): self
    {
        if ($status < 300 || $status > 399) {
            throw new InvalidArgumentException('Invalid status code for redirect response');
        }
        return new self($status, null, ['Location' => (string) $uri]);
    }

    public static function badRequest(StreamInterface $body = null): self
    {
        return new self(400, $body);
    }

    public static function unauthorized(StreamInterface $body = null): self
    {
        return new self(401, $body);
    }

    public static function notFound(StreamInterface $body = null): self
    {
        return new self(404, $body);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function withStatus($code, $reasonPhrase = ''): self
    {
        $clone = clone $this;
        $clone->status = $this->validStatusCode($code);
        $clone->reason = $clone->validReasonPhrase($reasonPhrase);

        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return $this->reason;
    }

    private function validStatusCode($code): int
    {
        if (!is_int($code) || $code < 100 || $code >= 600) {
            throw new InvalidArgumentException('Invalid status code argument - integer <100-599> expected');
        }
        return $code;
    }

    private function validReasonPhrase($reason): string
    {
        if (!is_string($reason)) {
            throw new InvalidArgumentException('Invalid HTTP ResponseHeaders reason phrase - string expected');
        }
        return $this->resolveReasonPhrase($reason);
    }

    private function resolveReasonPhrase(string $reason = ''): string
    {
        if (empty($reason) && isset($this->statusCodes[$this->status])) {
            $reason = $this->statusCodes[$this->status];
        }
        return $reason;
    }
}
