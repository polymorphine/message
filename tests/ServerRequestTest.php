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
    public static function instanceProperties(): array
    {
        return [
            'cookie' => ['cookie', ['key' => 'value'], fn (ServerRequest $request) => $request->getCookieParams()],
            'query'  => ['query', ['key' => 'value'], fn (ServerRequest $request) => $request->getQueryParams()],
            'pBody'  => ['parsedBody', ['key' => 'value'], fn (ServerRequest $request) => $request->getParsedBody()],
            'files'  => ['files', ['key' => new Doubles\FakeUploadedFile()], fn (ServerRequest $request) => $request->getUploadedFiles()]
        ];
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

    public function test_Instantiation()
    {
        $this->assertInstanceOf(ServerRequestInterface::class, $this->request());
    }

    public function test_GetServerParams_ReturnsInstanceServerParamsArray()
    {
        $params = ['key' => 'value'];
        $this->assertSame($params, $this->request(['server' => $params])->getServerParams());
    }

    /**
     * @param callable $getValue fn(ServerRequest) => array
     *
     * @dataProvider instanceProperties
     */
    public function test_Getters_ReturnConstructorProperties(string $name, array $value, callable $getValue)
    {
        $this->assertSame($value, $getValue($this->request([$name => $value])));
    }

    public function test_GetAttribute_WhenAttributeExists_ReturnsAttributeValue()
    {
        $request = $this->request()->withAttribute('name', 'value');
        $this->assertSame('value', $request->getAttribute('name', 'default'));
        $request = $this->request()->withAttribute('name', null);
        $this->assertSame(null, $request->getAttribute('name', 'default'));
    }

    public function test_GetAttribute_WhenAttributeNotPresent_ReturnsDefaultValue()
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
    public function test_MutatorMethods_ReturnNewInstance(callable $mutate)
    {
        $original = $this->request();
        $derivedA = $mutate($original);
        $derivedB = $mutate($original);
        $this->assertEquals($derivedA, $derivedB);
        $this->assertNotSame($derivedA, $derivedB);
    }

    public function test_AttributeMutation_ReturnsNewInstance()
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

    public function test_GetParsedBody_ForRequestWithoutBody_returnsNull()
    {
        $this->assertNull($this->request()->getParsedBody());
        $request = $this->request(['body' => ['key' => 'value']]);
        $this->assertNull($request->withParsedBody(null)->getParsedBody());
        $this->assertNull($request->withParsedBody([])->getParsedBody());
    }

    public function test_WithParsedBody_CalledWithInvalidArgument_ThrowsException()
    {
        $request = $this->request();
        $this->expectException(InvalidArgumentException::class);
        $request->withParsedBody(400);
    }

    public function test_UploadedFiles_WithInvalidStructure_ThrowsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $files = [
            'first'  => new Doubles\FakeUploadedFile(),
            'second' => 'oops im not a file'
        ];
        $this->request(['files' => $files]);
    }

    public function test_UploadedFile_WithNestedStructure()
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
