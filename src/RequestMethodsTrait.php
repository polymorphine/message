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

    /** @var UriInterface */
    private $uri;
    private $target;
    private $method;

    public function getRequestTarget()
    {
        return $this->target ?: $this->resolveTargetFromUri();
    }

    public function withRequestTarget($requestTarget)
    {
        $clone = clone $this;
        $clone->target = $this->validRequestTarget($requestTarget);

        return $clone;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function withMethod($method)
    {
        $clone = clone $this;
        $clone->method = $this->validMethod($method);

        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $clone = clone $this;
        $clone->uri = $uri;
        $clone->resolveHostHeader($preserveHost);

        return $clone;
    }

    private function validRequestTarget($target)
    {
        $invalidTarget = (!$target || !is_string($target) || $target !== '*' && !parse_url($target));
        return $invalidTarget ? null : $target;
    }

    private function validMethod($method)
    {
        if (!is_string($method) || $this->invalidTokenChars($method)) {
            throw new InvalidArgumentException('Invalid HTTP method name argument. Expected valid string token');
        }

        return $method;
    }

    private function resolveHostHeader($preserveHost = true)
    {
        $uriHost = $this->uri->getHost();
        if ($preserveHost && $this->hasHeader('host') || !$uriHost) { return; }

        $this->setHeader('Host', [$uriHost]);
    }

    private function resolveTargetFromUri()
    {
        $target = $this->uri->getPath();
        $query  = $this->uri->getQuery();

        if (!$target && !$query) { return '/'; }
        return $query ? $target . '?' . $query : $target;
    }
}
