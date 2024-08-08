<?php

namespace Hosni\EasytoyouApi\Decoders;

class DecodeResult implements \JsonSerializable
{
    public static function make(\SplFileInfo $encodedFile, ?string $downloadUrl, ?string $fileType = null): self
    {
        return new self($encodedFile, $downloadUrl, $fileType);
    }

    protected function __construct(
        protected \SplFileInfo $encodedFile,
        protected ?string $decodedFileUrl,
        protected ?string $fileType = null)
    {
    }

    public function getEncodedFile(): \SplFileInfo
    {
        return $this->encodedFile;
    }

    public function getDecodedFileUrl(): ?string
    {
        return $this->decodedFileUrl;
    }

    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    /**
     * @return array{encodedFile:string,decodedFileUrl:string|null,fileType:string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'encodedFile' => $this->encodedFile->__toString(),
            'decodedFileUrl' => $this->decodedFileUrl,
            'fileType' => $this->fileType,
        ];
    }
}
