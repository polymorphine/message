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
use Polymorphine\Message\Tests\Fixtures\MessageMethodsClass;
use Polymorphine\Message\Tests\Doubles\FakeStream;
use Psr\Http\Message\MessageInterface;
use InvalidArgumentException;


class MessageMethodsTest extends TestCase
{
    public function testInstantiatingMessage()
    {
        $this->assertInstanceOf(MessageInterface::class, $this->message());
    }

    public function testSetSupportedProtocolVersion()
    {
        $this->assertSame('2', $this->message([], '2')->getProtocolVersion());
        $this->assertSame('1.0', $this->message()->withProtocolVersion('1.0')->getProtocolVersion());
        $this->assertSame('1.1', $this->message()->withProtocolVersion('1.1')->getProtocolVersion());
        $this->assertSame('2', $this->message()->withProtocolVersion('2')->getProtocolVersion());
    }

    public function testProtocolVersionChange_ReturnsNewObject()
    {
        $original = $this->message();
        $modified = $original->withProtocolVersion('2');
        $this->assertEquals($modified, $original->withProtocolVersion('2'));
        $this->assertNotSame($modified, $original->withProtocolVersion('2'));
    }

    public function testInstantiateWithUnsupportedProtocolVersion_ThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->message([], '0.9');
    }

    public function testInstantiateWithInvalidProtocolVersionType_ThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->message([], 1.1);
    }

    public function testSetUnsupportedProtocolVersion_ThrowsException()
    {
        $message = $this->message();
        $this->expectException(InvalidArgumentException::class);
        $message->withProtocolVersion('0.9');
    }

    public function testSetInvalidProtocolVersionType_ThrowsException()
    {
        $message = $this->message();
        $this->expectException(InvalidArgumentException::class);
        $message->withProtocolVersion(1.1);
    }

    public function testGetBody_ReturnsPassedStream()
    {
        $body = new FakeStream();
        $this->assertSame($body, $this->message()->withBody($body)->getBody());
        $message = new MessageMethodsClass($body, []);
        $this->assertSame($body, $message->getBody());
    }

    public function testWithBody_ReturnsNewObject()
    {
        $original = $this->message();
        $modified = $original->withBody(new FakeStream());
        $this->assertEquals($original, $modified);
        $this->assertNotSame($original, $modified);
    }

    public function testHasHeaderForUnknownHeaderName_ReturnsFalse()
    {
        $this->assertFalse($this->message()->hasHeader('test'));
    }

    public function testHasHeaderForKnownHeaderName_ReturnsTrue()
    {
        $this->assertTrue($this->message(['test' => 'header string'])->hasHeader('test'));
    }

    public function testGetHeaderWhenHeaderNorFound_ReturnsEmptyArray()
    {
        $this->assertSame([], $this->message()->getHeader('not exists'));
    }

    public function testGetHeadersFromMessageWithoutHeaders_ReturnsEmptyArray()
    {
        $this->assertSame([], $this->message()->getHeaders());
    }

    public function testGetHeaderForValuePassedAsArray_ReturnsSameArray()
    {
        $value = ['header value'];
        $this->assertSame($value, $this->message()->withHeader('test', $value)->getHeader('test'));
        $this->assertSame($value, $this->message(['test' => $value])->getHeader('test'));
    }

    public function testGetHeaderLine_ReturnsCommaConcatenatedHeaderString()
    {
        $message = $this->message(['TwoValues' => ['first value', 'second value']]);
        $this->assertSame('first value, second value', $message->getHeaderLine('TwoValues'));
    }

    public function testWithHeader_ReturnsNewObject()
    {
        $original = $this->message(['test' => ['old value']]);
        $modified = $original->withHeader('test', ['new value']);
        $this->assertEquals(['old value'], $original->getHeader('test'));
        $this->assertEquals($original, $modified->withHeader('test', ['old value']));
        $this->assertNotSame($original, $modified->withHeader('test', ['old value']));
    }

    public function testGetHeaderForValuePassedAsString_ReturnsArrayWithSameString()
    {
        $this->assertSame(['string'], $this->message()->withHeader('test', 'string')->getHeader('test'));
    }

    public function testWithAddedHeader_AppendsValueToExistingValues()
    {
        $message = $this->message()->withHeader('test', 'first');
        $changed = $message->withAddedHeader('test', 'second');
        $this->assertSame(['first', 'second'], $changed->getHeader('test'));
        $this->assertNotSame($changed, $message->withAddedHeader('test', 'second'));
    }

    public function testWithoutHeader_RemovesHeader()
    {
        $this->assertFalse($this->message(['test' => 'value'])->withoutHeader('test')->hasHeader('test'));
    }

    public function testWithoutHeader_ReturnsNewObject()
    {
        $original = $this->message();
        $modified = $original->withoutHeader('doesntMatter');
        $this->assertEquals($original, $modified);
        $this->assertNotSame($original, $modified);
    }

    public function testHasHeader_HeaderNameIsNotCaseSensitive()
    {
        $message = $this->message(['tEsT' => ['string']]);
        $this->assertTrue($message->hasHeader('TeSt'));
    }

    public function testGetHeader_HeaderNameIsNotCaseSensitive()
    {
        $message = $this->message(['TEST' => ['string']]);
        $this->assertSame(['string'], $message->getHeader('teST'));
    }

    public function testGetHeaderLine_HeaderNameIsNotCaseSensitive()
    {
        $message = $this->message(['TESTlowercase' => ['first', 'SECOND']]);
        $this->assertSame('first, SECOND', $message->getHeaderLine('testLOWERCASE'));
    }

    public function testWithHeader_HeaderNameIsNotCaseSensitive()
    {
        $message = $this->message(['TEst' => ['old value']]);
        $this->assertSame(['new value'], $message->withHeader('teST', ['new value'])->getHeader('tESt'));
    }

    public function testWithAddedHeader_HeaderNameIsNotCaseSensitive()
    {
        $message = $this->message(['TEst' => ['old value']]);
        $this->assertSame(['old value', 'added value'], $message->withAddedHeader('teST', ['added value'])->getHeader('tESt'));
    }

    public function testWithAddedHeader_OriginalKeysArePreserved()
    {
        $message = $this->message(['TEst' => ['old value']]);
        $this->assertSame(['TEst' => ['old value', 'new value']], $message->withAddedHeader('teST', ['new value'])->getHeaders());
    }

    public function testWithoutHeader_HeaderNamesIsNotCaseSensitive()
    {
        $message = $this->message(['garbage' => ['one', 'two'], 'useful' => 'three']);
        $this->assertFalse($message->withoutHeader('GARBAGE')->hasHeader('garbage'));
        $this->assertSame(['useful' => ['three']], $message->withoutHeader('GARBAGE')->getHeaders());
    }

    public function testGetHeaders_ReturnsHeaderNamesWithOriginalCase()
    {
        $this->assertSame(['testCASE' => ['value']], $this->message(['testCASE' => ['value']])->getHeaders());
        $this->assertSame(['testCASE' => ['value']], $this->message()->withHeader('testCASE', 'value')->getHeaders());
    }

    public function testWithHeaderChangeOriginalCaseOnOverwrite()
    {
        $message = $this->message(['testCASE' => ['old value']])->withHeader('TESTcase', ['new value']);
        $this->assertSame(['TESTcase' => ['new value']], $message->getHeaders());
    }

    public function testWithAddedHeaderDoesNotChangeOriginalHeaderCase()
    {
        $message = $this->message(['testCASE' => ['first value']])->withAddedHeader('TESTcase', ['added value']);
        $this->assertSame(['testCASE' => ['first value', 'added value']], $message->getHeaders());
    }

    public function testGetHeaders_ReturnsHeaderValuesIgnoringPassedArrayKeys()
    {
        $message = $this->message(['test' => ['one' => 'first']]);
        $this->assertSame(['test' => ['first']], $message->getHeaders());
        $this->assertSame(['test' => ['second']], $message->withHeader('test', ['two' => 'second'])->getHeaders());
        $this->assertSame(['test' => ['first', 'second']], $message->withAddedHeader('test', ['two' => 'second'])->getHeaders());
    }

    /**
     * @dataProvider invalidHeaderNames
     *
     * @param $name
     */
    public function testInstantiateWithInvalidHeaderName_ThrowsException($name)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->message([$name => 'valid value']);
    }

    /**
     * @dataProvider invalidHeaderNames
     *
     * @param $name
     */
    public function testWithHeaderSettingInvalidHeaderName_ThrowsException($name)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->message()->withHeader($name, 'valid value');
    }

    /**
     * @dataProvider invalidHeaderNames
     *
     * @param $name
     */
    public function testWithAddedHeaderSettingInvalidHeaderName_ThrowsException($name)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->message()->withAddedHeader($name, 'valid value');
    }

    public function invalidHeaderNames(): array
    {
        return [
            'empty name'            => [''],
            'spaced name'           => ['header name'],
            'invalid name char "@"' => ['email@example']
        ];
    }

    /**
     * @dataProvider invalidHeaderValues
     *
     * @param $header
     */
    public function testInstantiateWithInvalidHeaderValues_ThrowsException($header)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->message(['test' => $header]);
    }

    public function invalidHeaderValues(): array
    {
        return [
            'null value'                    => [null],
            'bool value'                    => [true],
            'toString object'               => [new FakeStream()],
            'int within array'              => [['valid header', 9001]],
            'illegal char'                  => ["some value\xFF"],
            'invalid linebreak \n'          => ["some\n value"],
            'invalid linebreak \r'          => ["some\r value"],
            'invalid linebreak \n\r'        => ["some\n\r value"],
            'no whitespace after linebreak' => ["some\r\nvalue"]
        ];
    }

    private function message(array $headers = [], $version = null): MessageMethodsClass
    {
        if (!$version) {
            return new MessageMethodsClass(new FakeStream(), $headers);
        }

        return new MessageMethodsClass(new FakeStream(), $headers, $version);
    }
}
