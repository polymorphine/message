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

use Psr\Http\Message\UriInterface;
use InvalidArgumentException;


class Uri implements UriInterface
{
    public const CHARSET_HOST  = '^a-z0-9A-Z.\-_~&=+;,$!\'()*%';
    public const CHARSET_PATH  = '^a-z0-9A-Z.\-_~&=+;,$!\'()*%:\/@';
    public const CHARSET_QUERY = '^a-z0-9A-Z.\-_~&=+;,$!\'()*%:\/@?';

    protected array $supportedSchemes = [
        'http'  => ['port' => 80],
        'https' => ['port' => 443]
    ];

    private ?string $uri;

    private string $scheme;
    private string $userInfo;
    private string $host;
    private ?int   $port;
    private string $path;
    private string $query;
    private string $fragment;

    /**
     * @param array $segments Array of uri segment strings associated with same keys as returned from
     *                        parse_url() function: scheme, host, port, user, pass, path, query and fragment
     *
     * @see https://www.php.net/manual/en/function.parse-url.php
     */
    public function __construct(array $segments = [])
    {
        $this->scheme   = isset($segments['scheme']) ? $this->validScheme($segments['scheme']) : '';
        $this->userInfo = isset($segments['user']) ? $this->encode($segments['user'], self::CHARSET_HOST) : '';
        $this->userInfo .= isset($segments['pass']) ? $this->appendPassword($segments['pass']) : '';
        $this->host     = isset($segments['host']) ? $this->normalizedHost($segments['host']) : '';
        $this->port     = isset($segments['port']) ? $this->validPortRange((int) $segments['port']) : null;
        $this->path     = isset($segments['path']) ? $this->encode($segments['path'], self::CHARSET_PATH) : '';
        $this->query    = isset($segments['query']) ? $this->encode($segments['query'], self::CHARSET_QUERY) : '';
        $this->fragment = isset($segments['fragment']) ? $this->encode($segments['fragment'], self::CHARSET_QUERY) : '';
    }

    public static function fromString(string $uri = ''): self
    {
        $segments = parse_url($uri);
        if ($segments === false) {
            throw new InvalidArgumentException('Malformed URI string: `$uri`');
        }

        return new self($segments);
    }

    public function __toString(): string
    {
        return $this->uri ??= $this->buildUriString();
    }

    /**
     * Resets cached uri string.
     */
    public function __clone()
    {
        unset($this->uri);
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        $default = $this->port && $this->scheme && $this->supportedSchemes[$this->scheme]['port'] === $this->port;
        return ($default) ? null : $this->port;
    }

    public function getAuthority(): string
    {
        if (!$this->host) { return ''; }

        $user = $this->userInfo ? $this->userInfo . '@' : '';
        $port = $this->getPort();

        return $user . $this->host . ($port ? ':' . $port : '');
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme($scheme): UriInterface
    {
        if (!is_string($scheme)) {
            throw new InvalidArgumentException('URI scheme must be a string');
        }

        $clone = clone $this;
        $clone->scheme = $clone->validScheme($scheme);

        return $clone;
    }

    public function withUserInfo($user, $password = null): UriInterface
    {
        if (!is_string($user)) {
            throw new InvalidArgumentException('URI user must be a string');
        }

        if (!is_null($password) && !is_string($password)) {
            throw new InvalidArgumentException('URI password must be a string or null');
        }

        $clone = clone $this;
        $clone->userInfo = $this->encode($user, self::CHARSET_HOST) . $this->appendPassword($password);

        return $clone;
    }

    public function withHost($host): UriInterface
    {
        if (!is_string($host)) {
            throw new InvalidArgumentException('URI host must be a string');
        }

        $clone = clone $this;
        $clone->host = $this->normalizedHost($host);

        return $clone;
    }

    public function withPort($port): UriInterface
    {
        if (!is_int($port) && !is_null($port)) {
            throw new InvalidArgumentException('Invalid port parameter - expected int<1-65535> or null');
        }

        $clone = clone $this;
        $clone->port = is_null($port) ? null : $clone->validPortRange($port);

        return $clone;
    }

    public function withPath($path): UriInterface
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('URI path must be a string');
        }

        $clone = clone $this;
        $clone->path = $this->encode($path, self::CHARSET_PATH);

        return $clone;
    }

    public function withQuery($query): UriInterface
    {
        if (!is_string($query)) {
            throw new InvalidArgumentException('URI query must be a string');
        }

        $clone = clone $this;
        $clone->query = $this->encode($query, self::CHARSET_QUERY);

        return $clone;
    }

    public function withFragment($fragment): UriInterface
    {
        if (!is_string($fragment)) {
            throw new InvalidArgumentException('URI fragment must be a string.');
        }

        $clone = clone $this;
        $clone->fragment = $this->encode($fragment, self::CHARSET_QUERY);

        return $clone;
    }

    protected function buildUriString(): string
    {
        $uri = ($this->scheme) ? $this->scheme . ':' : '';
        $uri .= ($this->host) ? $this->authorityPath() : $this->filteredPath();
        if ($this->query) {
            $uri .= '?' . $this->query;
        }
        if ($this->fragment) {
            $uri .= '#' . $this->fragment;
        }

        return $uri ?: '/';
    }

    private function authorityPath(): string
    {
        $authority = '//' . $this->getAuthority();
        if (!$this->path) { return $authority; }
        return ($this->path[0] === '/') ? $authority . $this->path : $authority . '/' . $this->path;
    }

    private function filteredPath(): string
    {
        if (!$this->path) { return ''; }
        return ($this->path[0] === '/') ? '/' . ltrim($this->path, '/') : $this->path;
    }

    private function validScheme(string $scheme): string
    {
        if (!$scheme) { return ''; }

        $scheme = strtolower($scheme);
        if (!isset($this->supportedSchemes[$scheme])) {
            throw new InvalidArgumentException('Unsupported scheme');
        }

        return $scheme;
    }

    private function validPortRange(int $port): int
    {
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Invalid port range. Expected <1-65535> value');
        }

        return $port;
    }

    private function normalizedHost($host): string
    {
        $host = $this->encode($host, self::CHARSET_HOST, false);
        return $this->uppercaseEncoded(strtolower($host));
    }

    private function appendPassword(?string $password): string
    {
        return $password ? ':' . $this->encode($password, self::CHARSET_HOST . ':') : '';
    }

    private function encode(string $string, $charset, $normalize = true): string
    {
        $string = preg_replace('/%(?![0-9a-fA-F]{2})/', '%25', $string);
        $regexp = '/[' . $charset . ']+/u';
        $encode = function ($matches) { return rawurlencode($matches[0]); };
        $string = preg_replace_callback($regexp, $encode, $string);
        return $normalize ? $this->uppercaseEncoded($string) : $string;
    }

    private function uppercaseEncoded(string $string)
    {
        $upperEncoded = function ($matches) { return strtoupper($matches[0]); };
        return preg_replace_callback('/%(?=[A-Za-z0-9]{2}).{2}/', $upperEncoded, $string);
    }
}
