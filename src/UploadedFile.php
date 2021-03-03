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
    protected StreamInterface $stream;
    protected bool            $isMoved = false;

    private int     $errorCode;
    private ?int    $fileSize;
    private ?string $clientFilename;
    private ?string $clientMediaType;

    /**
     * @param StreamInterface $stream
     * @param int|null        $size
     * @param int             $error
     * @param string|null     $clientFilename
     * @param string|null     $clientMediaType
     */
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
        $stream = Stream::fromResourceUri($file['tmp_name']);
        return new self($stream, $file['size'], $file['error'], $file['name'], $file['type']);
    }

    public function getStream(): StreamInterface
    {
        $this->checkFileAccess();
        return $this->stream;
    }

    public function moveTo($targetPath): void
    {
        if (!is_string($targetPath) || empty($targetPath)) {
            throw new InvalidArgumentException('Invalid target path');
        }

        $this->checkFileAccess();
        if (!$source = $this->stream->getMetadata('uri')) {
            $this->isMoved = true;
            throw new RuntimeException('Cannot access file stream - assume already moved');
        }

        $this->moveFile($source, $targetPath);
        $this->isMoved = true;
    }

    public function getSize(): ?int
    {
        return $this->fileSize ?? $this->fileSize = $this->stream->getSize();
    }

    public function getError(): int
    {
        return $this->errorCode;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    protected function moveFile($source, $target): void
    {
        $this->stream->close();
        if (!move_uploaded_file($source, $target)) {
            throw new RuntimeException('Failed to move uploaded file');
        }
    }

    private function checkFileAccess(): void
    {
        if ($this->errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot access file - upload error');
        }

        if ($this->isMoved) {
            throw new RuntimeException('Cannot access file - file already moved');
        }
    }
}
