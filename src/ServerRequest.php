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

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\UploadedFileInterface;
use InvalidArgumentException;


class ServerRequest extends Request implements ServerRequestInterface
{
    private array  $server;
    private array  $cookie;
    private array  $query;
    private ?array $parsedBody;
    private array  $files;
    private array  $attributes = [];

    /**
     * @param string          $method  Normally one of the common methods defined by RFC 7231 section 4.3
     * @param UriInterface    $uri
     * @param StreamInterface $body
     * @param array           $headers Associative array of header strings or arrays of header strings
     * @param array           $params  Associative array with following keys and its default values
     *                                 when key is not present or its value is null:
     *                                 - version - http protocol version (default: '1.1')
     *                                 - target - request target (default: resolved from passed $uri param)
     *                                 - server - $_SERVER superglobal equivalent (default: [])
     *                                 - cookie - $_COOKIE superglobal equivalent (default: [])
     *                                 - query - $_GET superglobal equivalent (default: [])
     *                                 - parsedBody - $_POST superglobal equivalent (default: [])
     *                                 - files - associative array (multidimensional) of UploadedFileInterface
     *                                 instances (default: [])
     *
     * @see https://tools.ietf.org/html/rfc7231#section-4.3
     */
    public function __construct(
        string $method,
        UriInterface $uri,
        StreamInterface $body,
        array $headers = [],
        array $params = []
    ) {
        $this->server     = isset($params['server']) ? (array) $params['server'] : [];
        $this->cookie     = isset($params['cookie']) ? (array) $params['cookie'] : [];
        $this->query      = isset($params['query']) ? (array) $params['query'] : [];
        $this->parsedBody = empty($params['parsedBody']) ? null : $params['parsedBody'];
        $this->files      = isset($params['files']) ? $this->validUploadedFiles($params['files']) : [];
        parent::__construct($method, $uri, $body, $headers, $params);
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

    public function getCookieParams(): array
    {
        return $this->cookie;
    }

    public function withCookieParams(array $cookies): self
    {
        $clone = clone $this;
        $clone->cookie = $cookies;

        return $clone;
    }

    public function getQueryParams(): array
    {
        return $this->query;
    }

    public function withQueryParams(array $query): self
    {
        $clone = clone $this;
        $clone->query = $query;

        return $clone;
    }

    public function getUploadedFiles(): array
    {
        return $this->files;
    }

    public function withUploadedFiles(array $uploadedFiles): self
    {
        $clone = clone $this;
        $clone->files = $this->validUploadedFiles($uploadedFiles);

        return $clone;
    }

    public function getParsedBody(): ?array
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): self
    {
        if (!is_null($data) && !is_array($data) && !is_object($data)) {
            throw new InvalidArgumentException('Parsed body can be either array/object data structure or null');
        }

        $clone = clone $this;
        $clone->parsedBody = $data ?: null;

        return $clone;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    public function withAttribute($name, $value): self
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    public function withoutAttribute($name): self
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);

        return $clone;
    }

    private function validUploadedFiles(array $files): array
    {
        if (!$this->validFilesTree($files)) {
            throw new InvalidArgumentException('Expected associative array tree with UploadedFileInterface leaves');
        }

        return $files;
    }

    private function validFilesTree(array $files): bool
    {
        foreach ($files as $file) {
            $uploadedFile = is_array($file) && $this->validFilesTree($file) || $file instanceof UploadedFileInterface;
            if (!$uploadedFile) { return false; }
        }

        return true;
    }
}
