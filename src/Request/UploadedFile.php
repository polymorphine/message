<?php

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message\Request;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Polymorphine\Message;
use InvalidArgumentException;
use RuntimeException;


class UploadedFile implements UploadedFileInterface
{
    protected $fileName;
    protected $errorCode;
    protected $fileSize;
    protected $clientFilename;
    protected $clientMediaType;
    protected $isMoved = false;

    private $dataSpec = [
        'tmp_name' => 'string',
        'size'     => 'integer',
        'error'    => 'integer',
        'name'     => 'string',
        'type'     => 'string'
    ];

    public function __construct(array $file)
    {
        $this->checkSpec($file);

        $this->fileName        = $file['tmp_name'];
        $this->fileSize        = $file['size'];
        $this->errorCode       = $file['error'];
        $this->clientFilename  = $file['name'];
        $this->clientMediaType = $file['type'];
    }

    public function getStream(): StreamInterface
    {
        $this->checkFileAccess();

        return Message\Stream::fromResourceUri($this->fileName, 'r');
    }

    public function moveTo($targetPath)
    {
        $this->checkFileAccess();

        $this->isMoved = move_uploaded_file($this->fileName, $targetPath);
        if (!$this->isMoved) {
            throw new RuntimeException('Failed to move uploaded file');
        }
    }

    public function getSize()
    {
        return $this->fileSize;
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

    private function checkSpec(array $file)
    {
        foreach ($this->dataSpec as $key => $type) {
            if (!isset($file[$key]) || gettype($file[$key]) !== $type) {
                throw new InvalidArgumentException(sprintf('Invalid file %s data type', $key));
            }
        }

        if ($file['error'] < 0 || $file['error'] > 8) {
            throw new InvalidArgumentException('File error must be UPLOAD_ERR_* constant');
        }
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
