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
use Polymorphine\Message\ServerRequest;
use Polymorphine\Message\Uri;
use Psr\Http\Message\ServerRequestInterface;
use InvalidArgumentException;


class ServerRequestTest extends TestCase
{
    public function testInstantiation()
    {
        $this->assertInstanceOf(ServerRequestInterface::class, $this->request());
    }

    public function testGetServerParams_ReturnsInstanceServerParamsArray()
    {
        $params = ['key' => 'value'];
        $this->assertSame($params, $this->request(['server' => $params])->getServerParams());
    }

    /**
     * @param callable $getValue fn(ServerRequest) => array
     *
     * @dataProvider instanceProperties
     */
    public function testGetters_ReturnConstructorProperties(string $name, array $value, callable $getValue)
    {
        $this->assertSame($value, $getValue($this->request([$name => $value])));
    }

    public static function instanceProperties(): array
    {
        return [
            'cookie' => ['cookie', ['key' => 'value'], fn (ServerRequest $request) => $request->getCookieParams()],
            'query'  => ['query', ['key' => 'value'], fn (ServerRequest $request) => $request->getQueryParams()],
            'pBody'  => ['parsedBody', ['key' => 'value'], fn (ServerRequest $request) => $request->getParsedBody()],
            'files'  => ['files', ['key' => new Doubles\FakeUploadedFile()], fn (ServerRequest $request) => $request->getUploadedFiles()]
        ];
    }

    public function testGetAttribute_ReturnsSpecifiedAttributeValue()
    {
        $request = $this->request()->withAttribute('name', 'value');
        $this->assertSame('value', $request->getAttribute('name', 'default'));
        $request = $this->request()->withAttribute('name', null);
        $this->assertSame(null, $request->getAttribute('name', 'default'));
    }

    public function testGetAttribute_ReturnsDefaultValueIfAttributeNotPresent()
    {
        $request = $this->request(['attributes' => ['unknownName' => 'value']]);
        $this->assertSame('default', $request->getAttribute('name', 'default'));
        $this->assertSame(null, $request->getAttribute('name'));
    }

    /**
     * @param callable $mutate fn(ServerRequest) => ServerRequest
     *
     * @dataProvider mutatorMethods
     */
    public function testMutatorMethods_ReturnNewInstance(callable $mutate)
    {
        $original = $this->request();
        $derivedA = $mutate($original);
        $derivedB = $mutate($original);
        $this->assertEquals($derivedA, $derivedB);
        $this->assertNotSame($derivedA, $derivedB);
    }

    public static function mutatorMethods(): array
    {
        return [
            'cookie' => [fn (ServerRequest $original) => $original->withCookieParams(['key' => 'value'])],
            'query'  => [fn (ServerRequest $original) => $original->withQueryParams(['key' => 'value'])],
            'pBody'  => [fn (ServerRequest $original) => $original->withParsedBody(['key' => 'value'])],
            'files'  => [fn (ServerRequest $original) => $original->withUploadedFiles(['key' => new Doubles\FakeUploadedFile()])]
        ];
    }

    public function testAttributeMutation_ReturnsNewInstance()
    {
        $original = $this->request();
        [$name, $value] = ['name', 'value'];
        $derivedA = $original->withAttribute($name, $value);
        $derivedB = $original->withAttribute($name, $value);
        $this->assertEquals($derivedA, $derivedB);
        $this->assertNotSame($derivedA, $derivedB);

        $original = $derivedA;
        $derivedA = $original->withoutAttribute($name);
        $derivedB = $original->withoutAttribute($name);
        $this->assertEquals($derivedA, $derivedB);
        $this->assertNotSame($derivedA, $derivedB);
    }

    public function testGetParsedBodyForRequestWithoutBody_returnsNull()
    {
        $this->assertNull($this->request()->getParsedBody());
        $request = $this->request(['body' => ['key' => 'value']]);
        $this->assertNull($request->withParsedBody(null)->getParsedBody());
        $this->assertNull($request->withParsedBody([])->getParsedBody());
    }

    public function testInvalidArgumentForWithParsedBodyMethod_ThrowsException()
    {
        $request = $this->request();
        $this->expectException(InvalidArgumentException::class);
        $request->withParsedBody(400);
    }

    public function testUploadedFilesInvalidStructure_ThrowsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $files = [
            'first'  => new Doubles\FakeUploadedFile(),
            'second' => 'oops im not a file'
        ];
        $this->request(['files' => $files]);
    }

    public function testUploadedFileNestedStructureIsValid()
    {
        $files = [
            'first' => new Doubles\FakeUploadedFile(),
            'second' => [
                'subcategory1' => new Doubles\FakeUploadedFile(),
                'subcategory2' => new Doubles\FakeUploadedFile()
            ]
        ];
        $request = $this->request(['files' => $files]);
        $this->assertSame($files, $request->getUploadedFiles());
    }

    private function request(array $params = []): ServerRequest
    {
        return new ServerRequest('GET', Uri::fromString(), null, [], $params);
    }
}
