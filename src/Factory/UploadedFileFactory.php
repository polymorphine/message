<?php

/*
 * This file is part of Polymorphine/Message package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Message\Factory;

use Psr\Http\Message\UploadedFileFactoryInterface;
use Polymorphine\Message\UploadedFile;
use Polymorphine\Message\NonSAPIUploadedFile;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\StreamInterface;


class UploadedFileFactory implements UploadedFileFactoryInterface
{
    private string $serverAPI;

    /**
     * @see php_sapi_name()
     *
     * @param string $serverAPI interface type used by PHP
     */
    public function __construct(string $serverAPI = 'server')
    {
        $this->serverAPI = $serverAPI;
    }

    public function createUploadedFile(
        StreamInterface $stream,
        int $size = null,
        int $error = UPLOAD_ERR_OK,
        string $clientFilename = null,
        string $clientMediaType = null
    ): UploadedFileInterface {
        return $this->isWebServerAPI()
            ? new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType)
            : new NonSAPIUploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }

    private function isWebServerAPI(): bool
    {
        return $this->serverAPI && strpos($this->serverAPI, 'cli') !== 0 && strpos($this->serverAPI, 'phpdbg') !== 0;
    }
}
