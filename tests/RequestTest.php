<?php declare(strict_types=1);

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message\Tests;

use PHPUnit\Framework\TestCase;
use Polymorphine\Message\Request;
use Polymorphine\Message\Uri;
use Psr\Http\Message\RequestInterface;
use InvalidArgumentException;


class RequestTest extends TestCase
{
    public function testRequestInstantiation()
    {
        $this->assertInstanceOf(RequestInterface::class, $this->request());
    }

    /**
     * @dataProvider mutatorMethods
     *
     * @param $method
     * @param $param
     */
    public function testMutatorMethod_ReturnsNewInstance($method, $param)
    {
        $original = $this->request();
        $clone1   = $original->{$method}($param);
        $clone2   = $original->{$method}($param);
        $this->assertNotSame($clone1, $clone2);
        $this->assertEquals($clone1, $clone2);
        $this->assertNotEquals($original, $clone1);
    }

    public function mutatorMethods(): array
    {
        return [
            'withRequestTarget' => ['withRequestTarget', '*'],
            'withUri'           => ['withUri', Uri::fromString('/some/path')],
            'withMethod'        => ['withMethod', 'POST']
        ];
    }

    public function testGetMethod()
    {
        $this->assertSame('POST', $this->request('POST')->getMethod());
        $this->assertSame('DELETE', $this->request()->withMethod('DELETE')->getMethod());
    }

    public function testGetUri()
    {
        $uri = Uri::fromString();
        $this->assertSame($uri, $this->request('GET', [], $uri)->getUri());
        $this->assertSame($uri, $this->request()->withUri($uri)->getUri());
    }

    public function testWithMethodForInvalidMethod_ThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->request()->withMethod('SPACE INSIDE');
    }

    public function testConstructorWithInvalidMethod_ThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->request('SPACE INSIDE');
    }

    public function testResolvingRequestTarget()
    {
        $fail = 'Empty URIs path+query should produce root path for INVALID target';
        $this->assertSame('/', $this->request('GET', [], null, '//malformed:uri')->getRequestTarget(), $fail);
        $this->assertSame('/', $this->request()->withRequestTarget(['not string'])->getRequestTarget(), $fail);

        $fail = 'Empty URIs path+query should produce root path for UNSPECIFIED target';
        $this->assertSame('/', $this->request()->getRequestTarget(), $fail);

        $request = $this->request('GET', [], Uri::fromString('/some/path?query=param'));

        $fail = 'UNSPECIFIED or INVALID target should be resolved from URIs path+query';
        $this->assertSame('/some/path?query=param', $request->getRequestTarget(), $fail);
        $this->assertSame('/fizz/buzz', $request->withUri(Uri::fromString('/fizz/buzz'))->getRequestTarget(), $fail);
        $this->assertSame('/fizz/buzz', $request->withUri(Uri::fromString('/fizz/buzz'))->withRequestTarget(500)->getRequestTarget(), $fail);

        $fail = 'withRequestTarget() with VALID target should change target';
        $this->assertSame('*', $request->withRequestTarget('*')->getRequestTarget(), $fail);

        $fail = 'withUri() should not change previously SPECIFIED request target';
        $this->assertSame('*', $request->withRequestTarget('*')->withUri(Uri::fromString('fizz/buzz'))->getRequestTarget(), $fail);

        $fail    = 'Uri should not affect request target SPECIFIED in constructor';
        $request = $this->request('GET', [], Uri::fromString('/foo/bar'), '/fizz/buzz');
        $this->assertSame('/fizz/buzz', $request->getRequestTarget(), $fail);
    }

    public function testConstructorResolvesHostHeaderFromUri()
    {
        $fail    = 'Constructor should not create host header from URI with no host';
        $request = $this->request();
        $this->assertFalse($request->hasHeader('host'), $fail);

        $fail    = 'Constructor should create missing host header if URI has host info';
        $request = $this->request('GET', [], Uri::fromString('//example.com'));
        $this->assertSame('example.com', $request->getHeaderLine('host'), $fail);

        $fail    = 'Constructor should not overwrite host header';
        $request = $this->request('GET', ['host' => ['foo.com']], Uri::fromString('//bar.com'));
        $this->assertSame('foo.com', $request->getHeaderLine('host'), $fail);
    }

    public function testWithUriResolvesHostHeader()
    {
        $fail    = 'WithUri() should not create host header from URI with no host';
        $request = $this->request()->withUri(Uri::fromString('path/only'));
        $this->assertFalse($request->hasHeader('host'), $fail);

        $fail    = 'WithUri() should create missing host header if URI has host info';
        $request = $this->request()->withUri(Uri::fromString('//example.com'));
        $this->assertSame('example.com', $request->getHeaderLine('host'), $fail);

        $request = $this->request('GET', ['host' => ['header-example.com']]);
        $uri     = Uri::fromString('//uri-example.com');
        $fail    = 'WithUri($uri, true) should not overwrite host header';
        $this->assertSame('header-example.com', $request->withUri($uri, true)->getHeaderLine('host'), $fail);
        $fail = 'WithUri($uri, [false]) should overwrite host header';
        $this->assertSame('uri-example.com', $request->withUri($uri, false)->getHeaderLine('host'), $fail);
    }

    private function request($method = 'GET', array $headers = [], $uri = null, $target = null): Request
    {
        if (!isset($uri)) {
            $uri = Uri::fromString();
        }
        if (!$target) {
            return new Request($method, $uri, null, $headers, []);
        }

        return new Request($method, $uri, null, $headers, ['target' => $target]);
    }
}
