<?php

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
use Polymorphine\Message\Uri;
use InvalidArgumentException;


class UriTest extends TestCase
{
    public function testEmptyConstructorUri_ReturnsRootPathUriString()
    {
        $this->assertSame('/', (string) $this->uri());
    }

    public function testGettersContractForEmptyUri()
    {
        $uri = $this->uri();
        $this->assertSame('', $uri->getScheme());
        $this->assertSame('', $uri->getUserInfo());
        $this->assertSame('', $uri->getHost());
        $this->assertSame('', $uri->getAuthority());
        $this->assertSame(null, $uri->getPort());
        $this->assertSame('', $uri->getPath());
        $this->assertSame('', $uri->getQuery());
        $this->assertSame('', $uri->getPath());
    }

    public function testAllPropertiesAreSetWithinConstructor()
    {
        $uri = $this->uri('https://user:pass@example.com:9001/foo/bar?foo=bar&baz=qux#foo');
        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('user:pass', $uri->getUserInfo());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame(9001, $uri->getPort());
        $this->assertSame('user:pass@example.com:9001', $uri->getAuthority());
        $this->assertSame('/foo/bar', $uri->getPath());
        $this->assertSame('foo=bar&baz=qux', $uri->getQuery());
        $this->assertSame('foo', $uri->getFragment());
    }

    public function testImmutability_ModifiersShouldReturnNewInstances()
    {
        $uri = $this->uri();
        $this->assertNotSame($uri->withScheme('http'), $uri->withScheme('http'));
        $this->assertNotSame($uri->withUserInfo('user'), $uri->withUserInfo('user'));
        $this->assertNotSame($uri->withUserInfo('user', 'password'), $uri->withUserInfo('user', 'password'));
        $this->assertNotSame($uri->withHost('example.com'), $uri->withHost('example.com'));
        $this->assertNotSame($uri->withPort(9001), $uri->withPort(9001));
        $this->assertNotSame($uri->withPort(null), $uri->withPort(null));
        $this->assertNotSame($uri->withPath('/foo/bar'), $uri->withPath('/foo/bar'));
        $this->assertNotSame($uri->withQuery('?foo=bar&baz=qux'), $uri->withQuery('?foo=bar&baz=qux'));
        $this->assertNotSame($uri->withFragment('foo'), $uri->withFragment('foo'));
    }

    public function testModifierParametersAndGetterResponseEquivalence()
    {
        $uri = $this->uri();
        $this->assertSame('http', $uri->withScheme('http')->getScheme());
        $this->assertSame('user', $uri->withUserInfo('user')->getUserInfo());
        $this->assertSame('user:password', $uri->withUserInfo('user', 'password')->getUserInfo());
        $this->assertSame('example.com', $uri->withHost('example.com')->getHost());
        $this->assertSame(9001, $uri->withPort(9001)->getPort());
        $this->assertSame(null, $uri->withPort(9001)->withPort(null)->getPort());
        $this->assertSame('user:pass@example.com:500', $uri->withHost('example.com')->withUserInfo('user', 'pass')->withPort(500)->getAuthority());
        $this->assertSame('/foo/bar', $uri->withPath('/foo/bar')->getPath());
        $this->assertSame('foo=bar&baz=qux', $uri->withQuery('foo=bar&baz=qux')->getQuery());
        $this->assertSame('foo', $uri->withFragment('foo')->getFragment());
    }

    public function testInstantiationWithInvalidUriString_ThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->uri('http:///example.com');
    }

    public function testInstantiationWithUnsupportedScheme_ThrowsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->uri('xttp://example.com');
    }

    public function testModifyingToUnsupportedScheme_ThrowsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->uri()->withScheme('httpx');
    }

    public function testEmptySchemeIsAllowed_ReturnsInstanceWithEmptyScheme()
    {
        $uri = $this->uri('http:\\www.example.com');
        $this->assertSame('', $uri->withScheme('')->getScheme());
    }

    public function testSchemeIsNormalizedToLowercase()
    {
        $this->assertSame('http', $this->uri()->withScheme('Http')->getScheme());
        $this->assertSame('http', $this->uri('hTTP://www.example.com')->getScheme());
    }

    public function testHostIsNormalizedToLowercase()
    {
        $this->assertSame('example.com', $this->uri()->withHost('eXample.com')->getHost());
        $this->assertSame('example.com', $this->uri('http://EXAMPLE.COM')->getHost());
        $this->assertSame('http://example.com/foo/bar?baz=qux', (string) $this->uri('http://EXAMPLE.COM/foo/bar?baz=qux'));
    }

    public function testDefaultSchemePortLogic()
    {
        $this->assertNull($this->uri('www.example.com')->getPort(), 'No port specified');
        $this->assertSame(80, $this->uri('www.example.com:80')->getPort(), 'Default port for http, but scheme yet unknown');
        $this->assertNull($this->uri('http://www.example.com:80')->getPort(), 'Default should be omitted when scheme present');

        $this->assertSame(80, $this->uri('http://example.com:80')->withScheme('https')->getPort(), 'Scheme has changed and SPECIFIED port no longer default');
        $this->assertNull($this->uri('https://example.com:80')->withScheme('http')->getPort(), 'Scheme has changed and SPECIFIED port became default');

        $uri = $this->uri('http:foo.bar:500');
        $this->assertNull($uri->getPort(), 'This is relative path with scheme - port not specified');
        $uri = $uri->withPort(443); //SET Port
        $this->assertSame(443, $uri->getPort(), 'No host was given but port was set with modifier');
        $this->assertNull($this->uri((string) $uri)->getPort(), 'Without host port will not be part of uri string even if specified');
        $uri = $uri->withHost('example.com'); //SET Host
        $this->assertSame(443, $this->uri((string) $uri)->getPort(), 'Port included in uri string when host became present');
        $this->assertNull($this->uri((string) $uri->withScheme('https'))->getPort(), 'Changed scheme match its default port - not present in uri string');
    }

    public function testWhenHostEmpty_GetAuthorityReturnsEmptyString()
    {
        $uri = $this->uri('//user@example.com:2560');
        $this->assertSame('', $uri->withHost('')->getAuthority());
    }

    public function testBasicSegmentsConcatenationLogic()
    {
        $uri = $this->uri('https://user:pass@example.com:9001/foo/bar?foo=bar&baz=qux#foo');
        $this->assertSame('//user:pass@example.com:9001/foo/bar?foo=bar&baz=qux#foo', (string) $uri->withScheme(''));
        $this->assertSame('https://example.com:9001/foo/bar?foo=bar&baz=qux#foo', (string) $uri->withUserInfo(''));
        $this->assertSame('//example.com:9001/foo/bar?foo=bar&baz=qux#foo', (string) $uri->withScheme('')->withUserInfo(''));
        $this->assertSame('/foo/bar?foo=bar&baz=qux#foo', (string) $uri->withScheme('')->withHost(''));
        $this->assertSame('https://user:pass@example.com:9001?foo=bar&baz=qux#foo', (string) $uri->withPath(''));
        $this->assertSame('https://user:pass@example.com:9001#foo', (string) $uri->withPath('')->withQuery(''));
        $this->assertSame('https://user:pass@example.com:9001/foo/bar#foo', (string) $uri->withQuery(''));
        $this->assertSame('https://user:pass@example.com:9001/foo/bar?foo=bar&baz=qux', (string) $uri->withFragment(''));
        $this->assertSame('?foo=bar&baz=qux#foo', (string) $uri->withScheme('')->withHost('')->withPath(''));
        $this->assertSame('#foo', (string) $uri->withScheme('')->withHost('')->withPath('')->withQuery(''));

        //Invalid links, but valid URIs
        //Browsers would ignore 'http' scheme (but not https) and resolve these into valid relative links
        $this->assertSame('https:/foo/bar?foo=bar&baz=qux#foo', (string) $uri->withUserInfo('')->withHost(''));
        $this->assertSame('https:/foo/bar?foo=bar&baz=qux#foo', (string) $uri->withHost(''));
        $this->assertSame('https:#foo', (string) $uri->withHost('')->withPath('')->withQuery(''));
    }

    public function testWhenAuthorityIsPresent_SlashDelimiterForRelativePathIsAdded()
    {
        $uri = $this->uri('relative/path?foo=bar&baz=qux');
        $this->assertSame('relative/path?foo=bar&baz=qux', (string) $uri);
        $this->assertSame('http:relative/path?foo=bar&baz=qux', (string) $uri->withScheme('http'));
        $this->assertSame('//example.com/relative/path?foo=bar&baz=qux', (string) $uri->withHost('example.com'));
    }

    public function testWhenRemovingHostFromAuthorityOnlyUri_toStringReturnsRootPath()
    {
        $uri = $this->uri('//user@example.com:2560');
        $this->assertSame('/', (string) $uri->withHost(''));
    }

    public function testWhenAuthorityIsRemoved_InitialSlashesFromPathShouldBeReducedToOne()
    {
        $this->assertSame('http:/foo/bar', (string) $this->uri('http://user@example.com//foo/bar')->withHost(''));
        $this->assertSame('http:/foo/bar', (string) $this->uri('http://user@example.com//////foo/bar')->withHost(''));
    }

    public function testGetPathShouldNotFilterInitialSlashes()
    {
        $this->assertSame('//foo/bar', $this->uri('http://user@example.com//foo/bar')->getPath());
        $this->assertSame('//////foo/bar', $this->uri('http://user@example.com//////foo/bar')->getPath());
        $this->assertSame('//foo/bar', $this->uri()->withPath('//foo/bar')->getPath());
    }

    /**
     * @param $port
     * @dataProvider invalidPorts
     */
    public function testWithPortInvalidArgument_ThrowsException($port)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->uri()->withPort($port);
    }

    public function invalidPorts()
    {
        return [
            'bool'           => [true],
            'literal string' => ['string'],
            'array'          => [[45]],
            'object'         => [(object) ['port' => 113]],
            'zero'           => [0],
            'negative'       => [-20],
            'out of range'   => [65536],
            'numeric string' => ['65']
        ];
    }

    /**
     * @param $user
     * @param $pass
     * @dataProvider invalidUserInfoArgs
     */
    public function testWithUserInfoInvalidArgument_ThrowsException($user, $pass)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->uri()->withUserInfo($user, $pass);
    }

    public function invalidUserInfoArgs()
    {
        return [
            'bool username'   => [true, null],
            'array username'  => [['user', 'password'], null],
            'object username' => [(object) ['user' => 'foo'], null],
            'int username'    => [65536, null],
            'bool password'   => ['user', false],
            'array password'  => ['user', ['password']],
            'object password' => ['user', (object) ['password' => 'foo']],
            'int password'    => ['user', 65536]
        ];
    }

    /**
     * @param $scheme
     * @dataProvider invalidNonStringArgs
     */
    public function testWithSchemeNonStringArgument_ThrowsException($scheme)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->uri()->withScheme($scheme);
    }

    /**
     * @param $host
     * @dataProvider invalidNonStringArgs
     */
    public function testWithHostNonStringArgument_ThrowsException($host)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->uri()->withHost($host);
    }

    /**
     * @param $path
     * @dataProvider invalidNonStringArgs
     */
    public function testWithPathNonStringArgument_ThrowsException($path)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->uri()->withPath($path);
    }

    /**
     * @param $query
     * @dataProvider invalidNonStringArgs
     */
    public function testWithQueryNonStringArgument_ThrowsException($query)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->uri()->withQuery($query);
    }

    /**
     * @param $fragment
     * @dataProvider invalidNonStringArgs
     */
    public function testWithFragmentNonStringArgument_ThrowsException($fragment)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->uri()->withFragment($fragment);
    }

    public function invalidNonStringArgs()
    {
        return [
            'bool'   => [true],
            'array'  => [['string']],
            'object' => [(object) ['value' => 'string']],
            'int'    => [65536]
        ];
    }

    public function testIllegalUriCharactersArePercentEncoded()
    {
        $uri = $this->uri('http://➡.ws/䨹?foo=bar baz#qux(✪)');
        $this->assertSame('http://%E2%9E%A1.ws/%E4%A8%B9?foo=bar%20baz#qux(%E2%9C%AA)', (string) $uri);
        $this->assertSame('%E2%9E%A1:my%20pass', $uri->withUserInfo('➡', 'my pass')->getUserInfo());
        $this->assertSame('%E2%9E%A1.ws', $uri->withHost('➡.ws')->getHost());
        $this->assertSame('%E2%9E%A1/foo%20bar', $uri->withPath('➡/foo bar')->getPath());
        $this->assertSame('%E2%9E%A1=foo%20bar&%E4%BE%8B%E5%AD%90=%E6%B5%8B%E8%AF%95', $uri->withQuery('➡=foo bar&例子=测试')->getQuery());
        $this->assertSame('%D9%85%D8%AB%D8%A7%D9%84', $uri->withFragment('مثال')->getFragment());
    }

    public function testEncodedStringParametersAreNotDoubleEncoded()
    {
        $uri = $this->uri('http://%E2%9E%A1䨹.ws/%E4%A8%B9?foo=bar baz#qux(%E2%9C%AA)');
        $this->assertSame('http://%E2%9E%A1%E4%A8%B9.ws/%E4%A8%B9?foo=bar%20baz#qux(%E2%9C%AA)', (string) $uri);
        $this->assertSame('%E2%9E%A1:my%20pass', $uri->withUserInfo('%E2%9E%A1', 'my pass')->getUserInfo());
        $this->assertSame('fo%C3%B3%20bar.baz', $uri->withHost('foó%20bar.baz')->getHost());
        $this->assertSame('%E2%9E%A1/foo%20bar', $uri->withPath('%E2%9E%A1/foo bar')->getPath());
        $this->assertSame('%E2%9E%A1=foo%20bar&%E4%BE%8B%E5%AD%90=%E6%B5%8B%E8%AF%95', $uri->withQuery('%E2%9E%A1=foo%20bar&%E4%BE%8B%E5%AD%90=测试')->getQuery());
        $this->assertSame('%D9%85%D8%AB%D8%A7%D9%84', $uri->withFragment('مثا%D9%84')->getFragment()); //Right-to-left-literals
    }

    public function testEncodedNormalizedToUppercase()
    {
        $uri = $this->uri('http://us%e3:p%aass@%abcd.com/p%4a/th?qu%e1y=%f0o#fr%a3gment');
        $this->assertSame('us%E3:p%AAss', $uri->getUserInfo());
        $this->assertSame('%ABcd.com', $uri->getHost());
        $this->assertSame('us%E3:p%AAss@%ABcd.com', $uri->getAuthority());
        $this->assertSame('/p%4A/th', $uri->getPath());
        $this->assertSame('qu%E1y=%F0o', $uri->getQuery());
        $this->assertSame('fr%A3gment', $uri->getFragment());
    }

    public function testEncodeLiteralPercent()
    {
        $this->assertSame('low%25foo', $this->uri()->withPath('low%foo')->getpath());
    }

    public function testNormalizedHostEncodedFirst()
    {
        $this->assertSame('fo%C3%93.bar', $this->uri()->withHost('foÓ.BAR')->getHost());
    }

    public function testEncodeHostExcludedChars()
    {
        $this->assertSame('www%40example.com', $this->uri()->withHost('www@example.com')->getHost());
        $this->assertSame('www.e%5Bx%5Dample.com', $this->uri('http://www.e[x]ample.com')->getHost());
    }

    public function testEncodeUserInfoExcludedChars()
    {
        $this->assertSame('us%3Aer%40name:pa%40ss:word', $this->uri()->withUserInfo('us:er@name', 'pa@ss:word')->getUserInfo());
    }

    public function testEncodePathExcludedChars()
    {
        $this->assertSame('foo%3Fbar/baz', $this->uri()->withPath('foo?bar/baz')->getPath());
        $this->assertSame('/foo%5Bbar%5D/baz', $this->uri('http://www.example.com/foo[bar]/baz?quz=qux')->getPath());
    }

    public function testEncodeQueryExcludedCharacters()
    {
        $this->assertSame('foo%5Bbar%5D=baz', $this->uri('http://www.example.com/path/segment?foo[bar]=baz')->getQuery());
        $this->assertSame('foo%23bar=baz', $this->uri()->withQuery('foo#bar=baz')->getQuery());
    }

    public function testQueryMayContainQuestionMarkAndPathSeparators()
    {
        $this->assertSame('?foo=bar', $this->uri('http://www.example.com/path??foo=bar')->getQuery());
        $this->assertSame('??foo=bar', (string) $this->uri()->withQuery('?foo=bar'));
        $this->assertSame('foo=bar?path/segment', $this->uri('http://www.example.com/path?foo=bar?path/segment')->getQuery());
        $this->assertSame('??foo=bar/baz', (string) $this->uri()->withQuery('?foo=bar/baz'));
    }

    public function testEncodeFragmentExcludedCharacters()
    {
        $this->assertSame('%23foo-bar', $this->uri()->withFragment('#foo-bar')->getFragment());
        $uri = $this->uri('http://www.example.com/path/segment?query=segment#foo[bar]');
        $this->assertSame('foo%5Bbar%5D', $uri->getFragment());
        $this->assertSame('http://www.example.com/path/segment?query=segment#foo%5Bbar%5D', (string) $uri);
    }

    private function uri($uri = '')
    {
        return is_array($uri) ? new Uri($uri) : Uri::fromString($uri);
    }
}
