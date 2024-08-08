<?php

namespace Hosni\EasytoyouApi\Decoders\Demo;

use Hosni\EasytoyouApi\Decoders\DecodeResult;
use Hosni\EasytoyouApi\Decoders\DecoderTrait;
use Hosni\EasytoyouApi\Decoders\IDecoder;
use Hosni\EasytoyouApi\HttpClient;
use PHPHtmlParser\Dom;

abstract class AbstractDecoder implements IDecoder
{
    use DecoderTrait;

    protected static ?string $captchaInputName = null;

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
                        'name' => $this->getCaptchaInputName(),
                        'contents' => $this->getCaptchaFromUser(),
                    ],
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

    protected function getCaptchaInputName(): string
    {
        if (!self::$captchaInputName) {
            $dom = new Dom();
            $dom->loadStr($this->getHtmlOfPage());

            /** @var \PHPHtmlParser\Dom\HtmlNode[] */
            $fileInputs = $dom->find('input[type=text]');
            foreach ($fileInputs as $fileInput) {
                if (self::$captchaInputName = $fileInput->getAttribute('name')) {
                    break;
                }
            }
        }
        if (!self::$captchaInputName) {
            throw new \RuntimeException('Can not scrap captcha input name!');
        }

        return self::$captchaInputName;
    }

    protected function getCaptchaFromUser(): string
    {
        $time = time();
        $captchaFile = tempnam(sys_get_temp_dir(), "ety_{$time}_");
        $this->getHttpClient()->get('/captcha.php?'.$time, [
            'sink' => $captchaFile,
        ]);

        shell_exec("feh {$captchaFile}");

        $captchaNumber = '';
        do {
            echo 'Please enter captcha: ';
            $captchaNumber = trim(fgets(fopen('php://stdin', 'r'))); // @phpstan-ignore-line
        } while (!is_numeric($captchaNumber) or 4 != strlen($captchaNumber));

        if (is_file($captchaFile)) {
            @unlink($captchaFile);
        }

        return $captchaNumber;
    }
}
