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

use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\StreamInterface;
use InvalidArgumentException;
use RuntimeException;


class UploadedFile implements UploadedFileInterface
{
    private $stream;
    private $errorCode;
    private $fileSize;
    private $clientFilename;
    private $clientMediaType;
    private $isMoved = false;

    public function __construct(
        StreamInterface $stream,
        int $size = null,
        int $error = UPLOAD_ERR_OK,
        string $clientFilename = null,
        string $clientMediaType = null
    ) {
        if (!$stream->isReadable()) {
            throw new InvalidArgumentException('Stream is not readable');
        }
        if ($error < 0 || $error > 8) {
            throw new InvalidArgumentException('Error code out of range - must be UPLOAD_ERR_* constant <0 - 8>');
        }

        $this->stream          = $stream;
        $this->fileSize        = $size;
        $this->errorCode       = $error;
        $this->clientFilename  = $clientFilename;
        $this->clientMediaType = $clientMediaType;
    }

    /**
     * @param array $file Associative array with keys corresponding to $_FILES[name] array
     *                    for single uploaded file
     *
     * @return UploadedFile
     *
     * @see https://www.php.net/manual/en/features.file-upload.post-method.php
     */
    public static function fromFileArray(array $file): self
    {
        $stream = Stream::fromResourceUri($file['tmp_name'], 'r');
        return new self($stream, $file['size'], $file['error'], $file['name'], $file['type']);
    }

    public function getStream(): StreamInterface
    {
        $this->checkFileAccess();
        return $this->stream;
    }

    public function moveTo($targetPath)
    {
        $this->checkFileAccess();
        $this->isMoved = move_uploaded_file($this->stream->getMetadata('uri'), $targetPath);
        if (!$this->isMoved) {
            throw new RuntimeException('Failed to move uploaded file');
        }
    }

    public function getSize()
    {
        return $this->fileSize ?? $this->fileSize = $this->stream->getSize();
    }

    public function getError()
    {
        return $this->errorCode;
    }

    public function getClientFilename()
    {
        return $this->clientFilename;
    }

    public function getClientMediaType()
    {
        return $this->clientMediaType;
    }

    private function checkFileAccess()
    {
        if ($this->errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot access file - upload error');
        }

        if ($this->isMoved) {
            throw new RuntimeException('Cannot access file - file already moved');
        }
    }
}
