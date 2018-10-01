<?php

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message\Response;

use Polymorphine\Message\Response;
use Polymorphine\Message\Stream;


class StringResponse extends Response
{
    /**
     * StringResponse constructor.
     *
     * @param string $body
     * @param int    $statusCode
     * @param array  $headers
     */
    public function __construct(string $body, int $statusCode, array $headers)
    {
        parent::__construct($statusCode, Stream::fromBodyString($body), $headers);
    }

    /**
     * @param string $text
     * @param int    $statusCode
     *
     * @return StringResponse
     */
    public static function text(string $text, int $statusCode = 200)
    {
        return new self($text, $statusCode, ['Content-Type' => 'text/plain']);
    }

    /**
     * @param string $html
     * @param int    $statusCode
     *
     * @return StringResponse
     */
    public static function html(string $html, int $statusCode = 200)
    {
        return new self($html, $statusCode, ['Content-Type' => 'text/html']);
    }

    public static function xml(string $xml, int $statusCode = 200)
    {
        return new self($xml, $statusCode, ['Content-Type' => 'application/xml']);
    }

    /**
     * There's a XOR operator between $defaultEncode and $encodeOptions,
     * which means that if option is set in both provided and default it
     * will be switched off.
     *
     * @param array $data
     * @param int   $statusCode
     * @param int   $encodeOptions
     *
     * @return StringResponse
     */
    public static function json(array $data, int $statusCode = 200, $encodeOptions = 0)
    {
        $defaultEncode = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
        $serialized    = json_encode($data, $defaultEncode ^ $encodeOptions);

        return new self($serialized, $statusCode, ['Content-Type' => 'application/json']);
    }
}
