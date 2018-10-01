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
    private $body;
    private $version;
    private $headers;

    private $supportedProtocolVersions = ['1.0', '1.1', '2'];

    private $headerNames = [];

    public function getProtocolVersion()
    {
        return $this->version;
    }

    public function withProtocolVersion($version)
    {
        $clone = clone $this;
        $clone->version = $this->validProtocolVersion($version);

        return $clone;
    }

    public function getHeaders()
    {
        return $this->headers ?: [];
    }

    public function hasHeader($name)
    {
        return is_string($name) && isset($this->headerNames[strtolower($name)]);
    }

    public function getHeader($name)
    {
        if (!$this->hasHeader($name)) { return []; }

        $index = strtolower($name);
        $name  = $this->headerNames[$index];

        return $this->headers[$name];
    }

    public function getHeaderLine($name)
    {
        $header = $this->getHeader($name);
        return empty($header) ? '' : implode(', ', $header);
    }

    public function withHeader($name, $value)
    {
        $clone = clone $this;
        $clone->removeHeader($name);
        $clone->setHeader($name, $value);

        return $clone;
    }

    public function withAddedHeader($name, $value)
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

    public function withoutHeader($name)
    {
        $clone = clone $this;
        $clone->removeHeader($name);

        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body)
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    private function loadHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    private function setHeader($name, $value)
    {
        $name  = $this->validHeaderName($name);
        $index = strtolower($name);

        $this->headers[$name]      = $this->validHeaderValues($value);
        $this->headerNames[$index] = $name;
    }

    private function validHeaderName($name)
    {
        if (!is_string($name) || $this->invalidTokenChars($name)) {
            throw new InvalidArgumentException('Invalid header name argument type - expected valid string token');
        }

        return $name;
    }

    private function invalidTokenChars($token)
    {
        return preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $token) !== 1;
    }

    private function validHeaderValues($headerValues)
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

    private function legalHeaderStrings(array $headerValues)
    {
        foreach ($headerValues as $value) {
            if (!is_string($value) || $this->illegalHeaderChars($value)) {
                return false;
            }
        }

        return true;
    }

    private function illegalHeaderChars(string $header)
    {
        $illegalCharset   = preg_match("/[^\t\r\n\x20-\x7E\x80-\xFE]/", $header);
        $invalidLineBreak = preg_match("/(?:[^\r]\n|\r[^\n]|\n[^ \t])/", $header);

        return $illegalCharset || $invalidLineBreak;
    }

    private function removeHeader($name)
    {
        $headerIndex = strtolower($name);
        if (!isset($this->headerNames[$headerIndex])) { return; }
        unset($this->headers[$this->headerNames[$headerIndex]], $this->headerNames[$headerIndex]);
    }

    private function validProtocolVersion($version)
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
