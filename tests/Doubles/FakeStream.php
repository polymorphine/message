<?php

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message\Tests\Doubles;

use Psr\Http\Message\StreamInterface;


class FakeStream implements StreamInterface
{
    public $seekable = true;

    private $body;
    private $stream;

    public function __construct(string $body = '')
    {
        $this->body   = $body;
        $this->stream = $body;
    }

    public function __toString()
    {
        return $this->body;
    }

    public function close()
    {
    }

    public function detach()
    {
    }

    public function getSize()
    {
        return strlen($this->stream);
    }

    public function tell()
    {
    }

    public function eof()
    {
        return empty($this->stream);
    }

    public function isSeekable()
    {
        return $this->seekable;
    }

    public function seek($offset, $whence = SEEK_SET)
    {
    }

    public function rewind()
    {
        $this->stream = $this->body;
    }

    public function isWritable()
    {
    }

    public function write($string)
    {
    }

    public function isReadable()
    {
        return true;
    }

    public function read($length)
    {
        $send = substr($this->stream, 0, $length);
        $this->stream = substr($this->stream, $length);
        return $send;
    }

    public function getContents()
    {
        return $this->body;
    }

    public function getMetadata($key = null)
    {
    }
}
