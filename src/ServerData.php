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
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use InvalidArgumentException;


class ServerData
{
    private $server;
    private $get;
    private $post;
    private $cookie;
    private $files;

    /**
     * @param array $params Associative array with keys corresponding to server superglobals:
     *                      server ($_SERVER), get ($_GET), post ($_POST), cookie ($_COOKIE), files ($_FILES)
     */
    public function __construct(array $params = [])
    {
        $this->server = $params['server'] ?? [];
        $this->get    = $params['get'] ?? [];
        $this->post   = $params['post'] ?? [];
        $this->cookie = $params['cookie'] ?? [];
        $this->files  = $params['files'] ?? [];
    }

    public function method(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function uri(): UriInterface
    {
        return $this->resolveUri();
    }

    public function body(): StreamInterface
    {
        return Stream::fromResourceUri('php://input');
    }

    public function headers(): array
    {
        return $this->resolveHeaders();
    }

    public function params(): array
    {
        return [
            'server'     => $this->server,
            'cookie'     => $this->cookie,
            'query'      => $this->get,
            'parsedBody' => $this->post,
            'files'      => $this->normalizeFiles($this->files),
            'version'    => $this->protocolVersion()
        ];
    }

    private function protocolVersion(): string
    {
        return isset($this->server['SERVER_PROTOCOL'])
            ? explode('/', $this->server['SERVER_PROTOCOL'])[1]
            : '1.1';
    }

    private function resolveUri(): UriInterface
    {
        $scheme = (empty($this->server['HTTPS']) || $this->server['HTTPS'] === 'off') ? 'http' : 'https';
        $host   = $this->server['HTTP_HOST'] ?? 'localhost';
        $port   = $this->server['SERVER_PORT'] ?? null;

        [$uri, $fragment] = explode('#', $this->server['REQUEST_URI'] ?? '/', 2) + ['', ''];
        [$path, $query] = explode('?', $uri, 2) + ['', ''];

        return new Uri([
            'scheme'   => $scheme,
            'host'     => $host,
            'port'     => $port,
            'path'     => $path,
            'query'    => $query,
            'fragment' => $fragment
        ]);
    }

    private function resolveHeaders(): array
    {
        $headers = [];
        foreach ($this->server as $key => $value) {
            $headerName = $this->headerName($key);
            if (!$value || !$headerName) { continue; }
            $headers[$headerName] = $value;
        }

        if (!isset($headers['Authorization']) && $value = $this->authorizationHeader()) {
            $headers['Authorization'] = $value;
        }

        return $headers;
    }

    private function headerName($name)
    {
        if (strpos($name, 'HTTP_') === 0) {
            if ($name === 'HTTP_CONTENT_MD5') { return 'Content-MD5'; }
            return $this->normalizedHeaderName(substr($name, 5));
        }

        if (strpos($name, 'CONTENT_') === 0) {
            return $this->normalizedHeaderName($name);
        }

        return false;
    }

    private function normalizedHeaderName(string $name): string
    {
        return ucwords(strtolower(str_replace('_', '-', $name)), '-');
    }

    private function authorizationHeader()
    {
        if (!function_exists('apache_request_headers')) { return false; }

        $headers = apache_request_headers();
        return $headers['Authorization'] ?? $headers['authorization'] ?? false;
    }

    private function normalizeFiles(array $files): array
    {
        $normalizedFiles = [];
        foreach ($files as $key => $value) {
            $normalizedFiles[$key] = ($value instanceof UploadedFileInterface)
                ? $value
                : $this->resolveFileTree($value);
        }

        return $normalizedFiles;
    }

    private function resolveFileTree($value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Invalid file data structure');
        }

        return isset($value['tmp_name']) ? $this->createUploadedFile($value) : $this->normalizeFiles($value);
    }

    private function createUploadedFile(array $file)
    {
        return is_array($file['tmp_name']) ? $this->transposeFileDataSet($file) : new UploadedFile($file);
    }

    private function transposeFileDataSet(array $files)
    {
        $normalizedFiles = [];
        foreach ($files as $specKey => $values) {
            foreach ($values as $idx => $value) {
                $normalizedFiles[$idx][$specKey] = $value;
            }
        }

        $createFile = function ($file) { return new UploadedFile($file); };
        return array_map($createFile, $normalizedFiles);
    }
}
