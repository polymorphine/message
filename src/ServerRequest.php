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

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\UploadedFileInterface;
use InvalidArgumentException;


class ServerRequest implements ServerRequestInterface
{
    use Request\RequestMethodsTrait;

    private $server;
    private $cookie;
    private $query;
    private $attributes;
    private $parsedBody;
    private $files;

    public function __construct(
        string $method,
        UriInterface $uri,
        StreamInterface $body,
        array $headers = [],
        array $params = []
    ) {
        $this->method     = $this->validMethod($method);
        $this->uri        = $uri;
        $this->body       = $body;
        $this->version    = isset($params['version']) ? $this->validProtocolVersion($params['version']) : '1.1';
        $this->target     = isset($params['target']) ? $this->validRequestTarget($params['target']) : null;
        $this->server     = isset($params['server']) ? (array) $params['server'] : [];
        $this->cookie     = isset($params['cookie']) ? (array) $params['cookie'] : [];
        $this->query      = isset($params['query']) ? (array) $params['query'] : [];
        $this->attributes = isset($params['attributes']) ? (array) $params['attributes'] : [];
        $this->parsedBody = empty($params['parsedBody']) ? null : $params['parsedBody'];
        $this->files      = isset($params['files']) ? $this->validUploadedFiles($params['files']) : [];
        $this->loadHeaders($headers);
        $this->resolveHostHeader();
    }

    public function getServerParams(): array
    {
        return $this->server;
    }

    public function getCookieParams()
    {
        return $this->cookie;
    }

    public function withCookieParams(array $cookies)
    {
        $clone = clone $this;
        $clone->cookie = $cookies;

        return $clone;
    }

    public function getQueryParams()
    {
        return $this->query;
    }

    public function withQueryParams(array $query)
    {
        $clone = clone $this;
        $clone->query = $query;

        return $clone;
    }

    public function getUploadedFiles()
    {
        return $this->files;
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        $clone = clone $this;
        $clone->files = $this->validUploadedFiles($uploadedFiles);

        return $clone;
    }

    public function getParsedBody()
    {
        return isset($this->parsedBody) ? $this->parsedBody : $this->parsedBody = $this->resolveParsedBody();
    }

    public function withParsedBody($data)
    {
        $clone = clone $this;
        $clone->parsedBody = empty($data) ? null : $data;

        return $clone;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    public function withAttribute($name, $value)
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    public function withoutAttribute($name)
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);

        return $clone;
    }

    private function validUploadedFiles(array $files)
    {
        if (!$this->validFilesTree($files)) {
            throw new InvalidArgumentException('Expected associative array tree with UploadedFileInterface leaves');
        }

        return $files;
    }

    private function validFilesTree(array $files)
    {
        foreach ($files as $file) {
            $uploadedFile = is_array($file) && $this->validFilesTree($file) || $file instanceof UploadedFileInterface;
            if (!$uploadedFile) { return false; }
        }

        return true;
    }

    protected function resolveParsedBody()
    {
        return ($this->method === 'POST' && !empty($_POST) && $this->isFormContentType()) ? $_POST : null;
    }

    private function isFormContentType()
    {
        $content = $this->getHeaderLine('Content-Type');

        $urlEncoded = strpos($content, 'application/x-www-form-urlencoded') === 0;
        return $urlEncoded || strpos($content, 'multipart/form-data') === 0;
    }
}
