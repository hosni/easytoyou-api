<?php

namespace Hosni\EasytoyouApi\Decoders;

use Hosni\EasytoyouApi\HttpClient;
use PHPHtmlParser\Dom;

trait DecoderTrait
{
    protected static ?string $htmlOfPage = null;
    protected static ?string $fileInputName = null;

    public function setHttpClient(HttpClient $client): void
    {
        $this->client = $client;
    }

    public function getHttpClient(): HttpClient
    {
        return $this->client;
    }

    public function getFileInputName(): string
    {
        if (!self::$fileInputName) {
            $dom = new Dom();
            $dom->loadStr($this->getHtmlOfPage());

            /** @var \PHPHtmlParser\Dom\HtmlNode[] */
            $fileInputs = $dom->find('input[type=file]');
            foreach ($fileInputs as $fileInput) {
                if (self::$fileInputName = $fileInput->getAttribute('name')) {
                    break;
                }
            }
        }
        if (!self::$fileInputName) {
            echo $this->getHtmlOfPage();
            throw new \RuntimeException('Can not scrap file input name!');
        }

        return self::$fileInputName;
    }

    protected function getHtmlOfPage(): string
    {
        if (!self::$htmlOfPage) {
            self::$htmlOfPage = $this->getHttpClient()->get($this->getRequestUri())->getBody()->getContents();

            if (false !== stripos(self::$htmlOfPage, "doesn't match your IP address")) {
                throw new \RuntimeException('Country does not match to IP country!');
            }
        }

        return self::$htmlOfPage;
    }

    /**
     * @param \SplFileInfo[] $files
     *
     * @return DecodeResult[]
     */
    protected function parseResultHtml(string $html, array $files): array
    {
        $results = [];

        $dom = new Dom();
        $dom->loadStr($html, ['whitespaceTextNode' => false]);

        /** @var Dom\HtmlNode[]|null */
        $alertBoxes = $dom->find('.container > .alert');
        if (!$alertBoxes) {
            return $results;
        }

        /** @var Dom\HtmlNode $alertBox */
        foreach ($alertBoxes as $alertBox) {
            $line = trim(str_replace(['×', '&#8226;'], '', $alertBox->text(true)));

            $failedToDecode = false;
            if (str_starts_with($line, 'The file') or str_starts_with($line, 'the file')) {
                $line = trim(substr($line, strlen('The file')));
                $failedToDecode = true;
            }
            foreach ($files as $file) {
                if (str_starts_with($line, $file->getFilename())) {
                    if ($failedToDecode) {
                        $results[$file->getPathname()] = DecodeResult::make(
                            $file,
                            null,
                            $line,
                        );
                    } else {
                        $parts = explode('  ', $line);

                        $results[$file->getPathname()] = DecodeResult::make(
                            $file,
                            trim(str_replace('Download link:', '', $parts[2])),
                            trim(str_replace('File encoder version:', '', $parts[1])),
                        );
                    }
                }
            }
        }

        return $results;
    }
}
