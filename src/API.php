<?php

namespace Hosni\EasytoyouApi;

use Hosni\EasytoyouApi\Account\Account;
use Hosni\EasytoyouApi\Decoders\DecodeResult;
use Hosni\EasytoyouApi\Decoders\Demo\DemoPhp74;
use Hosni\EasytoyouApi\Decoders\IDecoder;
use Hosni\EasytoyouApi\Decoders\IPremiumDecoder;
use Hosni\EasytoyouApi\Decoders\Premium\Ic11Php74;

class API
{
    protected ?IDecoder $decoder = null;

    public function __construct(protected HttpClient $client, protected ?Account $account = null)
    {
        if ($this->account) {
            $this->loginWithAccount($this->account);
        }
    }

    public function decode(\SplFileInfo $encodedFile): DecodeResult
    {
        return $this->getDecoder()->decode($encodedFile);
    }

    /**
     * @param \SplFileInfo[] $files
     *
     * @return DecodeResult[]
     */
    public function decodeMulti(array $files): array
    {
        return $this->getDecoder()->decodeMulti($files);
    }

    public function getDecoder(): IDecoder
    {
        if (!$this->decoder) {
            $this->decoder = $this->account ?
                new Ic11Php74($this->client, $this->account) :
                new DemoPhp74($this->client);
        }

        return $this->decoder;
    }

    public function setDecoder(IDecoder|string $decoder): void
    {
        if (is_string($decoder)) {
            if (!class_exists($decoder)) {
                throw new \InvalidArgumentException(sprintf('The passed decoder class %s does not exists!', $decoder));
            }
            if (is_a($decoder, IPremiumDecoder::class, true)) {
                /** @var IPremiumDecoder $decoder */
                $decoder = new $decoder($this->getHttpClient(), $this->getAccount());
            } elseif (is_a($decoder, IDecoder::class, true)) {
                /** @var IDecoder $decoder */
                $decoder = new $decoder($this->getHttpClient());
            } else {
                throw new \InvalidArgumentException(sprintf('The passed decoder should be instance of %s but %s given', IDecoder::class, $decoder));
            }
        }
        $this->decoder = $decoder;
    }

    public function getHttpClient(): HttpClient
    {
        return $this->client;
    }

    public function setHttpClient(HttpClient $client, bool $reloginIfNeeded = true): void
    {
        $this->client = $client;
        if ($this->account && $reloginIfNeeded) {
            $this->loginWithAccount($this->account);
        }
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): void
    {
        $this->account = $account;
        $this->loginWithAccount($this->account);
    }

    protected function loginWithAccount(Account $account): void
    {
        $response = $this->getHttpClient()->post('/login', [
            'form_params' => [
                'loginname' => $account->getUsername(),
                'password' => $account->getPassword(),
            ],
        ]);
        $body = $response->getBody()->getContents();

        if (false !== stripos($body, "account doesn't exist")
            or false === stripos($body, "Logged in as {$account->getUsername()}")
        ) {
            var_dump($response, $body);
            throw new \RuntimeException('Can not login to easytoyou!');
        }
    }
}
