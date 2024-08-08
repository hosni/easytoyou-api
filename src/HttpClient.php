<?php

namespace Hosni\EasytoyouApi;

use GuzzleHttp\Client;

class HttpClient extends Client
{
    public const BASE_URI = 'https://easytoyou.eu';

    protected static ?self $instance = null;

    /**
     * @param array<string,string|mixed> $config
     */
    public static function make(array $config = []): self
    {
        if (!self::$instance) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * @return array<string,string>
     */
    public static function getDefaultHeaders(): array
    {
        return [
            'Host' => 'easytoyou.eu',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en', // this make easytoyou fool
            'Pragma' => 'no-cache',
            'Cache-Control' => 'no-cache',
            'User-Agent' => self::getUserAgent(),
            'Referer' => 'https://easytoyou.eu/decoders',
        ];
    }

    public static function getUserAgent(): string
    {
        return 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/114.0';
    }

    /**
     * @param array<string,string|mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct(array_merge_recursive([
            'base_uri' => self::BASE_URI,
            // 'timeout'  => 20.0,
            'cookies' => true,
            'headers' => self::getDefaultHeaders(),
        ], $config));
    }
}
