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

use Psr\Http\Message\UriInterface;
use InvalidArgumentException;


trait RequestMethodsTrait
{
    use MessageMethodsTrait;

    private UriInterface $uri;
    private ?string      $target;
    private string       $method;

    /**
     * {@inheritDoc}
     */
    public function getRequestTarget(): string
    {
        return $this->target ?: $this->resolveTargetFromUri();
    }

    /**
     * {@inheritDoc}
     */
    public function withRequestTarget($requestTarget): self
    {
        $clone = clone $this;
        $clone->target = $this->validRequestTarget($requestTarget);

        return $clone;
    }

    /**
     * {@inheritDoc}
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * {@inheritDoc}
     */
    public function withMethod($method): self
    {
        $clone = clone $this;
        $clone->method = $this->validMethod($method);

        return $clone;
    }

    /**
     * {@inheritDoc}
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * {@inheritDoc}
     */
    public function withUri(UriInterface $uri, $preserveHost = false): self
    {
        $clone = clone $this;
        $clone->uri = $uri;
        $clone->resolveHostHeader($preserveHost);

        return $clone;
    }

    private function validRequestTarget($target): ?string
    {
        $invalidTarget = (!$target || !is_string($target) || $target !== '*' && !parse_url($target));
        return $invalidTarget ? null : $target;
    }

    private function validMethod($method): string
    {
        if (!is_string($method) || $this->invalidTokenChars($method)) {
            throw new InvalidArgumentException('Invalid HTTP method name argument. Expected valid string token');
        }

        return $method;
    }

    private function resolveHostHeader($preserveHost = true): void
    {
        $uriHost = $this->uri->getHost();
        if ($preserveHost && $this->hasHeader('host') || !$uriHost) { return; }

        $this->setHeader('Host', [$uriHost]);
    }

    private function resolveTargetFromUri(): string
    {
        $target = $this->uri->getPath();
        $query  = $this->uri->getQuery();

        if (!$target && !$query) { return '/'; }
        return $query ? $target . '?' . $query : $target;
    }
}
