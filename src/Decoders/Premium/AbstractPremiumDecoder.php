<?php

namespace Hosni\EasytoyouApi\Decoders\Premium;

use Hosni\EasytoyouApi\Decoders\DecodeResult;
use Hosni\EasytoyouApi\Decoders\DecoderTrait;
use Hosni\EasytoyouApi\Decoders\IPremiumDecoder;
use Hosni\EasytoyouApi\HttpClient;

abstract class AbstractPremiumDecoder implements IPremiumDecoder
{
    use DecoderTrait;

    protected static ?string $fileInputName = null;

    public function __construct(protected HttpClient $client)
    {
    }

    public function decode(\SplFileInfo $file): DecodeResult
    {
        $results = $this->decodeMulti([
            $file,
        ]);
        if (1 === count($results)) {
            return array_pop($results);
        }

        throw new \RuntimeException('This could not be happend!');
    }

    public function decodeMulti(array $files): array
    {
        $response = $this->client->post($this->getRequestUri(), [
            'multipart' => array_merge(
                array_map(fn (\SplFileInfo $file): array => [
                    'name' => $this->getFileInputName(),
                    'contents' => $file->openFile(),
                    'filename' => $file->getFilename(),
                ], $files),
                [
                    [
                        'name' => 'submit',
                        'contents' => 'Decode',
                    ],
                ]
            ),
        ]);
        $html = $response->getBody()->getContents();
        $results = $this->parseResultHtml($html, $files);

        if (count($results) != count($files)) {
            var_dump($results);
            throw new \RuntimeException('The files count does not match results count!');
        }

        return $results;
    }
}
