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

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use InvalidArgumentException;


class ServerData
{
    private const FILE_ARRAY = ['name', 'type', 'tmp_name', 'error', 'size'];

    private array $server;
    private array $get;
    private array $post;
    private array $cookie;
    private array $files;

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

    /**
     * @return string
     */
    public function method(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * @return UriInterface
     */
    public function uri(): UriInterface
    {
        return $this->resolveUri();
    }

    /**
     * @return StreamInterface
     */
    public function body(): StreamInterface
    {
        return Stream::fromResourceUri('php://input');
    }

    /**
     * @return array
     */
    public function headers(): array
    {
        return $this->resolveHeaders();
    }

    /**
     * @return array
     */
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

    /**
     * @return array
     */
    public function uploadedFiles(): array
    {
        return $this->normalizeFiles($this->files);
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

    private function headerName($name): ?string
    {
        if (strpos($name, 'HTTP_') === 0) {
            if ($name === 'HTTP_CONTENT_MD5') { return 'Content-MD5'; }
            return $this->normalizedHeaderName(substr($name, 5));
        }

        if (strpos($name, 'CONTENT_') === 0) {
            return $this->normalizedHeaderName($name);
        }

        return null;
    }

    private function normalizedHeaderName(string $name): string
    {
        return ucwords(strtolower(str_replace('_', '-', $name)), '-');
    }

    private function authorizationHeader(): ?string
    {
        if (!function_exists('apache_request_headers')) { return null; }

        $headers = apache_request_headers();
        return $headers['Authorization'] ?? $headers['authorization'] ?? null;
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

        if (isset($value['tmp_name']) && $this->isFileArray(array_keys($value))) {
            return $this->createUploadedFile($value);
        }

        return $value;
    }

    private function isFileArray(array $keys): bool
    {
        return !array_diff($keys, self::FILE_ARRAY);
    }

    private function createUploadedFile(array $file)
    {
        if (!is_array($file['tmp_name'])) {
            return UploadedFile::fromFileArray($file);
        }

        $filesTree = [];
        foreach (self::FILE_ARRAY as $specKey) {
            $this->buildStructure($filesTree, $file[$specKey], $specKey, $specKey === 'size');
        }

        return $filesTree;
    }

    private function buildStructure(array &$files, array $values, string $key, bool $isLastKey): void
    {
        foreach ($values as $name => $value) {
            isset($files[$name]) or $files[$name] = [];

            if (is_array($value)) {
                $this->buildStructure($files[$name], $value, $key, $isLastKey);
                continue;
            }

            $files[$name][$key] = $value;
            if ($isLastKey) {
                $files[$name] = UploadedFile::fromFileArray($files[$name]);
            }
        }
    }
}
