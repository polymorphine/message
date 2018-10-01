<?php

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message\Tests\Response;

use PHPUnit\Framework\TestCase;
use Polymorphine\Message\Response;
use Polymorphine\Message\Uri;
use Psr\Http\Message\ResponseInterface;
use InvalidArgumentException;


class DerivedResponsesTest extends TestCase
{
    public function testInstantiation()
    {
        $this->assertInstanceOf(ResponseInterface::class, new Response\NotFoundResponse());

        $this->equivalentConstructs(
            new Response\RedirectResponse('/foo/bar/234'),
            Response\RedirectResponse::fromUri(Uri::fromString('/foo/bar/234'))
        );

        $this->equivalentConstructs(
            new Response\StringResponse('<span>html</span>', 200, ['Content-Type' => 'text/html']),
            Response\StringResponse::html('<span>html</span>')
        );

        $this->equivalentConstructs(
            new Response\StringResponse('Hello World!', 200, ['Content-Type' => 'text/plain']),
            Response\StringResponse::text('Hello World!')
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?><note>Hello</note>';
        $this->equivalentConstructs(
            new Response\StringResponse($xml, 200, ['Content-Type' => 'application/xml']),
            Response\StringResponse::xml($xml)
        );

        $this->equivalentConstructs(
            new Response\StringResponse('{"path":"some/path"}', 200, ['Content-Type' => 'application/json']),
            Response\StringResponse::json(['path' => 'some/path'])
        );

        $this->equivalentConstructs(
            new Response\StringResponse('{"path":"some\/path"}', 200, ['Content-Type' => 'application/json']),
            Response\StringResponse::json(['path' => 'some/path'], 200, JSON_UNESCAPED_SLASHES)
        );
    }

    public function testNotFoundResponseStatusCode()
    {
        $response = new Response\NotFoundResponse();
        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * @dataProvider invalidRedirectCode
     *
     * @param int $status
     */
    public function testInvalidRedirectResponseStatusCode_ThrowsException(int $status)
    {
        $this->expectException(InvalidArgumentException::class);
        Response\RedirectResponse::fromUri(Uri::fromString('/foo/bar/234'), $status);
    }

    public function invalidRedirectCode()
    {
        return [[100], [200], [299], [400], [500]];
    }

    private function equivalentConstructs(ResponseInterface $responseA, ResponseInterface $responseB)
    {
        $bodyA = $responseA->getBody();
        $this->assertSame($bodyA->getContents(), $responseB->getBody()->getContents());
        $this->assertEquals($responseA, $responseB->withBody($bodyA));
    }
}
