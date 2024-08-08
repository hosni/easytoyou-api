<?php

namespace Hosni\EasytoyouApi\Decoders;

use Hosni\EasytoyouApi\HttpClient;

interface IDecoder
{
    public function setHttpClient(HttpClient $client): void;

    public function getHttpClient(): HttpClient;

    public function getRequestUri(): string;

    public function decode(\SplFileInfo $file): DecodeResult;

    /**
     * @param \SplFileInfo[] $files
     *
     * @return DecodeResult[]
     */
    public function decodeMulti(array $files): array;
}
