<?php declare(strict_types=1);

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message;

use Psr\Http\Message\StreamInterface;
use Polymorphine\Message\Exception\StreamResourceCallException;
use InvalidArgumentException;
use RuntimeException;


class Stream implements StreamInterface
{
    protected $resource;

    private ?array $metaData;
    private ?bool  $readable;
    private ?bool  $writable;
    private ?bool  $seekable;

    /**
     * @param resource $resource One of the stream type resources
     *
     * @see https://www.php.net/manual/en/resource.php
     */
    public function __construct($resource)
    {
        if (!is_resource($resource) || get_resource_type($resource) !== 'stream') {
            throw new InvalidArgumentException('Invalid stream resource');
        }

        $this->resource = $resource;
    }

    public static function fromResourceUri(string $streamUri, $mode = 'r'): self
    {
        set_error_handler(function () use ($mode) {
            restore_error_handler();
            if (preg_match('/^[acrwx](?:\+?[tb]?|[tb]?\+?)$/', $mode)) {
                throw new RuntimeException('Invalid stream reference');
            }
            throw new InvalidArgumentException('Invalid stream resource mode');
        }, E_WARNING);
        $resource = fopen($streamUri, $mode);
        restore_error_handler();

        return new self($resource);
    }

    public static function fromBodyString(string $body): self
    {
        $stream = new self(fopen('php://temp', 'w+b'));
        $stream->write($body);
        $stream->rewind();

        return $stream;
    }

    public function __toString(): string
    {
        try {
            $this->rewind();
            return $this->getContents();
        } catch (RuntimeException $e) {
            return '';
        }
    }

    public function close(): void
    {
        $resource = $this->detach();
        if ($resource) { fclose($resource); }
    }

    public function detach()
    {
        if (!$this->resource) { return null; }

        $resource = $this->resource;

        $this->resource = null;
        $this->metaData = null;
        $this->readable = false;
        $this->seekable = false;
        $this->writable = false;

        return $resource;
    }

    public function getSize(): ?int
    {
        return $this->resource ? fstat($this->resource)['size'] : null;
    }

    public function tell(): int
    {
        if (!$this->resource) {
            throw new RuntimeException('Pointer position not available in detached resource');
        }

        $position = ftell($this->resource);
        if ($position === false) {
            throw new StreamResourceCallException('Failed to tell pointer position');
        }

        return $position;
    }

    public function eof(): bool
    {
        return $this->resource ? feof($this->resource) : true;
    }

    public function isSeekable(): bool
    {
        if (isset($this->seekable)) { return $this->seekable; }

        return $this->seekable = $this->getMetadata('seekable');
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable or detached');
        }

        $exitCode = fseek($this->resource, $offset, $whence);
        if ($exitCode === -1) {
            throw new StreamResourceCallException('Failed to seek the stream');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        if (isset($this->writable)) { return $this->writable; }

        $mode     = $this->getMetadata('mode');
        $writable = ['w' => true, 'a' => true, 'x' => true, 'c' => true];

        return $this->writable = (isset($writable[$mode[0]]) || strstr($mode, '+'));
    }

    public function write($string): int
    {
        if (!$this->resource) {
            throw new RuntimeException('No resource available; cannot write');
        }

        if (!$this->isWritable()) {
            throw new RuntimeException('Stream is not writable');
        }

        $bytesWritten = fwrite($this->resource, $string);
        if ($bytesWritten === false) {
            throw new StreamResourceCallException('Failed writing to stream');
        }

        return $bytesWritten;
    }

    public function isReadable(): bool
    {
        if (isset($this->readable)) { return $this->readable; }

        $mode = $this->getMetadata('mode');

        return $this->readable = ($mode[0] === 'r' || strstr($mode, '+'));
    }

    public function read($length): string
    {
        if (!$this->resource) {
            throw new RuntimeException('No resource available; cannot read');
        }

        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable');
        }

        $streamData = fread($this->resource, $length);
        if ($streamData === false) {
            throw new StreamResourceCallException('Failed reading from stream');
        }

        return $streamData;
    }

    public function getContents(): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable or detached');
        }

        $streamContents = stream_get_contents($this->resource);
        if ($streamContents === false) {
            throw new StreamResourceCallException('Failed to retrieve stream contents');
        }

        return $streamContents;
    }

    public function getMetadata($key = null)
    {
        if (!$this->resource) {
            return $key ? null : [];
        }

        $this->metaData ??= stream_get_meta_data($this->resource);
        return $key ? $this->metaData[$key] ?? null : $this->metaData;
    }
}
