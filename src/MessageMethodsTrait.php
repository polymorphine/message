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

use Psr\Http\Message\StreamInterface;
use InvalidArgumentException;


trait MessageMethodsTrait
{
    private StreamInterface $body;
    private string          $version;
    private array           $headers;

    private array $supportedProtocolVersions = ['1.0', '1.1', '2'];

    private array $headerNames = [];

    /**
     * {@inheritDoc}
     */
    public function getProtocolVersion(): string
    {
        return $this->version;
    }

    /**
     * {@inheritDoc}
     */
    public function withProtocolVersion($version): self
    {
        $clone = clone $this;
        $clone->version = $this->validProtocolVersion($version);

        return $clone;
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaders(): array
    {
        return $this->headers ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function hasHeader($name): bool
    {
        return is_string($name) && isset($this->headerNames[strtolower($name)]);
    }

    /**
     * {@inheritDoc}
     */
    public function getHeader($name): array
    {
        if (!$this->hasHeader($name)) { return []; }

        $index = strtolower($name);
        $name  = $this->headerNames[$index];

        return $this->headers[$name];
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaderLine($name): string
    {
        $header = $this->getHeader($name);
        return empty($header) ? '' : implode(', ', $header);
    }

    /**
     * {@inheritDoc}
     */
    public function withHeader($name, $value): self
    {
        $clone = clone $this;
        $clone->removeHeader($name);
        $clone->setHeader($name, $value);

        return $clone;
    }

    /**
     * {@inheritDoc}
     */
    public function withAddedHeader($name, $value): self
    {
        if (!$this->hasHeader($name)) {
            return $this->withHeader($name, $value);
        }

        $index = strtolower($name);
        $name  = $this->headerNames[$index];
        $value = $this->validHeaderValues($value);

        $clone = clone $this;
        $clone->headers[$name] = array_merge($clone->headers[$name], $value);

        return $clone;
    }

    /**
     * {@inheritDoc}
     */
    public function withoutHeader($name): self
    {
        $clone = clone $this;
        $clone->removeHeader($name);

        return $clone;
    }

    /**
     * {@inheritDoc}
     */
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * {@inheritDoc}
     */
    public function withBody(StreamInterface $body): self
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    private function loadHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    private function setHeader($name, $value): void
    {
        $name  = $this->validHeaderName($name);
        $index = strtolower($name);

        $this->headers[$name]      = $this->validHeaderValues($value);
        $this->headerNames[$index] = $name;
    }

    private function validHeaderName($name): string
    {
        if (!is_string($name) || $this->invalidTokenChars($name)) {
            throw new InvalidArgumentException('Invalid header name argument type - expected valid string token');
        }

        return $name;
    }

    private function invalidTokenChars($token): bool
    {
        return preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $token) !== 1;
    }

    private function validHeaderValues($headerValues): array
    {
        if (is_string($headerValues)) {
            $headerValues = [$headerValues];
        }
        if (!is_array($headerValues) || !$this->legalHeaderStrings($headerValues)) {
            $message = 'Invalid HTTP header value argument - expected legal strings[] or string';
            throw new InvalidArgumentException($message);
        }

        return array_values($headerValues);
    }

    private function legalHeaderStrings(array $headerValues): bool
    {
        foreach ($headerValues as $value) {
            if (!is_string($value) || $this->illegalHeaderChars($value)) {
                return false;
            }
        }

        return true;
    }

    private function illegalHeaderChars(string $header): bool
    {
        $illegalCharset   = preg_match("/[^\t\r\n\x20-\x7E\x80-\xFE]/", $header);
        $invalidLineBreak = preg_match("/(?:[^\r]\n|\r[^\n]|\n[^ \t])/", $header);

        return $illegalCharset || $invalidLineBreak;
    }

    private function removeHeader($name): void
    {
        $headerIndex = strtolower($name);
        if (!isset($this->headerNames[$headerIndex])) { return; }
        unset($this->headers[$this->headerNames[$headerIndex]], $this->headerNames[$headerIndex]);
    }

    private function validProtocolVersion($version): string
    {
        if (!is_string($version)) {
            throw new InvalidArgumentException('Invalid HTTP protocol version type - expected string');
        }

        if (!in_array($version, $this->supportedProtocolVersions, true)) {
            $message = 'Unsupported HTTP protocol version - expected <%s> string';
            throw new InvalidArgumentException(sprintf($message, implode('|', $this->supportedProtocolVersions)));
        }

        return $version;
    }
}
