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
    use RequestMethodsTrait;

    private $server;
    private $cookie;
    private $query;
    private $parsedBody;
    private $files;
    private $attributes = [];

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
        $this->parsedBody = empty($params['parsedBody']) ? null : $params['parsedBody'];
        $this->files      = isset($params['files']) ? $this->validUploadedFiles($params['files']) : [];
        $this->loadHeaders($headers);
        $this->resolveHostHeader();
    }

    public static function fromServerData(ServerData $data): self
    {
        return new self($data->method(), $data->uri(), $data->body(), $data->headers(), $data->params());
    }

    public static function fromGlobals(array $override = []): self
    {
        return self::fromServerData(new ServerData([
            'server' => isset($override['server']) ? $override['server'] + $_SERVER : $_SERVER,
            'get'    => isset($override['get']) ? $override['get'] + $_GET : $_GET,
            'post'   => isset($override['post']) ? $override['post'] + $_POST : $_POST,
            'cookie' => isset($override['cookie']) ? $override['cookie'] + $_COOKIE : $_COOKIE,
            'files'  => isset($override['files']) ? $override['files'] + $_FILES : $_FILES
        ]));
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
        return $this->parsedBody;
    }

    public function withParsedBody($data)
    {
        if (!is_null($data) && !is_array($data) && !is_object($data)) {
            throw new InvalidArgumentException('Parsed body can be either array/object data structure or null');
        }

        $clone = clone $this;
        $clone->parsedBody = $data ?: null;

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
}
