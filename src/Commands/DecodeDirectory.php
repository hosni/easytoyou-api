<?php

namespace Hosni\EasytoyouApi\Commands;

use GuzzleHttp\Exception\RequestException;
use Hosni\EasytoyouApi\Account\Account;
use Hosni\EasytoyouApi\API;
use Hosni\EasytoyouApi\Decoders\Premium\Ic11Php74;
use Hosni\EasytoyouApi\HttpClient;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use webignition\PathResolver\PathResolver;

class DecodeDirectory extends Command
{
    protected static $defaultName = 'decode-dir';

    protected static $defaultDescription = 'Decode ionCube files in source directory and put it in dest directory';

    protected function configure(): void
    {
        parent::configure();
        $this->addOption('--chunk-size', null, InputArgument::OPTIONAL, 'Size of each chunk that we send to easytoyou', 5);
        $this->addOption('--manifest-file', null, InputArgument::OPTIONAL, 'The manifest file path to store report');
        $this->addOption('--decoder', null, InputArgument::OPTIONAL, 'Default decoder to decode', Ic11Php74::class);
        $this->addArgument('--src', InputArgument::OPTIONAL, 'The source directory to look for ioncubed files');
        $this->addArgument('--dest', InputArgument::OPTIONAL, 'The dest directory to store');

        $this->addOption('--watch', 'w', InputArgument::REQUIRED, 'Should watch for new files?');

        $this->addOption('--async-dl', null, InputArgument::REQUIRED, 'Should download results async?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $shouldWatchForNewFiles = $input->hasParameterOption(['--watch', '-w'], true);
        if ($shouldWatchForNewFiles) {
            echo 'Run on watch mode!'.PHP_EOL;
        }

        $asyncDownload = $input->hasParameterOption(['--async-dl'], true);
        if ($asyncDownload) {
            echo 'Download mode: async!'.PHP_EOL;
        }

        /** @var int|null $chunkSize */
        $chunkSize = (int) $input->getOption('chunk-size');
        if ($chunkSize < 1) {
            throw new \Exception('The --chunk-size should be greater that zero!');
        }
        echo 'Using chunk-size: '.$chunkSize.PHP_EOL;

        /** @var string|null $srcDirectoryPath */
        $srcDirectoryPath = $input->getArgument('--src');

        /** @var string|null $dstDirectoryPath */
        $dstDirectoryPath = $input->getArgument('--dest');
        if (!$dstDirectoryPath) {
            $dstDirectoryPath = rtrim($srcDirectoryPath, DIRECTORY_SEPARATOR).'-decoded';
        }

        if (!$srcDirectoryPath || !$dstDirectoryPath) {
            throw new \Exception('You should pass --src and --dest paths.');
        }
        $pathResolver = new PathResolver();
        $srcDirectoryPath = $pathResolver->resolve(dirname(__FILE__, 3), $srcDirectoryPath);
        $dstDirectoryPath = $pathResolver->resolve(dirname(__FILE__, 3), $dstDirectoryPath);
        echo "Source directory: {$srcDirectoryPath}".PHP_EOL;
        echo "Destination directory: {$dstDirectoryPath}".PHP_EOL;

        if (!is_dir($srcDirectoryPath)) {
            throw new \Exception('The --src should be directory.');
        }

        /** @var string|null $username */
        $username = $input->getOption('username');
        /** @var string|null $password */
        $password = $input->getOption('password');
        if (!$password) {
            /** @var string|null $passwordEnvVariable */
            $passwordEnvVariable = $input->getOption('password-env');
            $password = $passwordEnvVariable ? getenv($passwordEnvVariable) : null;
        }

        $account = null;
        if ($username && $password) {
            echo "Using: username: {$username} and password: {$password}".PHP_EOL;
            $account = Account::make($username, $password);
        }

        $httpClientConfig = [];
        $proxy = $input->getOption('proxy');
        if ($proxy) {
            $httpClientConfig['proxy'] = $proxy;
        }
        $client = HttpClient::make($httpClientConfig);

        $api = new API($client);

        /** @var string|null $decoder */
        $decoder = $input->getOption('decoder');
        if ($decoder) {
            $api->setDecoder($decoder);
        }

        if ($account) {
            $api->setAccount($account);
        }

        do {
            $sourceDirectory = new \RecursiveDirectoryIterator($srcDirectoryPath);
            $iterator = new \RecursiveIteratorIterator($sourceDirectory);

            $chunkedFiles = [];
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ('php' != $file->getExtension()) {
                    continue;
                }
                echo "Should decode file: {$file->getPathname()} ? ";
                if (!$this->shouldDecodeFile($file, $srcDirectoryPath, $dstDirectoryPath)) {
                    echo 'No'.PHP_EOL;
                    continue;
                }
                echo 'Yes'.PHP_EOL;

                $chunkedFiles[] = $file;
                if (count($chunkedFiles) >= $chunkSize) {
                    $this->decodeFiles($input, $api, $chunkedFiles, $srcDirectoryPath, $dstDirectoryPath);
                    $chunkedFiles = [];
                }
            }
            if ($chunkedFiles) {
                $this->decodeFiles($input, $api, $chunkedFiles, $srcDirectoryPath, $dstDirectoryPath);
            }
        } while ($shouldWatchForNewFiles);

        return Command::SUCCESS;
    }

    /**
     * @param \SplFileInfo[] $files
     */
    protected function decodeFiles(InputInterface $input, API $api, array $files, string $srcDirectoryPath, string $dstDirectoryPath): void
    {
        static $tries = 0;
        try {
            echo 'decodeMulti:'.json_encode($files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
            $results = $api->decodeMulti($files);
            var_dump($results);

            // Dummy fix for easytoyou, they change the input file name to prevent bots
            // So, if there is no result, it means we are posting to wrong input name
            // By setting decoder on api, it will create new instance and again fetch the input name
            // Then we retry!
            if (!$results) {
                $api->setDecoder(get_class($api->getDecoder()));
                $this->decodeFiles($input, $api, $files, $srcDirectoryPath, $dstDirectoryPath);
            }
            $this->processResults($input, $api, $results, $srcDirectoryPath, $dstDirectoryPath);
        } catch (\Exception $e) {
            if (++$tries < 3) {
                $this->decodeFiles($input, $api, $files, $srcDirectoryPath, $dstDirectoryPath);
            } else {
                $tries = 0;
            }
        }
    }

    /**
     * @param \Hosni\EasytoyouApi\Decoders\DecodeResult[] $results
     */
    protected function processResults(InputInterface $input, API $api, array $results, string $srcDirectoryPath, string $dstDirectoryPath): void
    {
        /** @var string|null */
        $manifestFile = $input->getOption('manifest-file');
        if (!$manifestFile) {
            $manifestFile = rtrim($dstDirectoryPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'easytoyou-manifest.json';
        }

        $asyncDownload = $input->hasParameterOption(['--async-dl'], true);
        if ($asyncDownload) {
            echo 'Prepare to async download!'.PHP_EOL;
        }

        $promises = [];

        foreach ($results as $result) {
            if ($manifestFile) { // @phpstan-ignore-line
                $manifestDirectory = dirname($manifestFile);
                if (!is_dir($manifestDirectory)) {
                    mkdir($manifestDirectory, 0755, true);
                }
                file_put_contents(
                    $manifestFile,
                    json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
                    FILE_APPEND | LOCK_EX
                );
            }

            if (!$result->getDecodedFileUrl()) {
                echo PHP_EOL.PHP_EOL.
                    'Can not decode: '.$result->getEncodedFile()->getPathname().
                PHP_EOL.PHP_EOL;
                continue;
            }

            $sinkPath = $this->calculateDestFilePath($result->getEncodedFile(), $srcDirectoryPath, $dstDirectoryPath);
            $sinkPathDirectory = dirname($sinkPath);
            if (!is_dir($sinkPathDirectory)) {
                mkdir($sinkPathDirectory, 0755, true);
            }

            if ($asyncDownload) {
                echo PHP_EOL.$result->getEncodedFile()->getPathname().PHP_EOL.
                    "-> Prepare to async download: {$result->getDecodedFileUrl()} to {$sinkPath}".PHP_EOL.PHP_EOL;

                $promise = $api->getHttpClient()->getAsync(
                    $result->getDecodedFileUrl(), [
                        'sink' => $sinkPath,
                    ]
                );
                $promise->then(
                    function (ResponseInterface $res) use (&$result, &$sinkPath) {
                        echo PHP_EOL.$result->getEncodedFile()->getPathname().PHP_EOL.
                            "-> downloaded: {$result->getDecodedFileUrl()} to {$sinkPath} with status: {$res->getStatusCode()}".PHP_EOL.PHP_EOL;
                    },
                    function (RequestException $e) {
                        echo $e->getMessage()."\n";
                        echo $e->getRequest()->getMethod();
                    }
                );
                $promises[$sinkPath] = $promise;
            } else {
                echo PHP_EOL.$result->getEncodedFile()->getPathname().PHP_EOL.
                    "-> Downloding: {$result->getDecodedFileUrl()} to {$sinkPath}".PHP_EOL.PHP_EOL;

                static $singleModeTries = 0;
                try {
                    $api->getHttpClient()->get(
                        $result->getDecodedFileUrl(), [
                            'sink' => $sinkPath,
                        ]
                    );
                } catch (\Exception $e) {
                    if (++$singleModeTries < 3) {
                        $this->processResults($input, $api, [$result], $srcDirectoryPath, $dstDirectoryPath);
                    }
                }
            }
        }
        if ($asyncDownload && $promises) {
            static $asyncModeTries = 0;
            try {
                \GuzzleHttp\Promise\Utils::unwrap($promises);
            } catch (\Exception $e) {
                if (++$asyncModeTries < 3) {
                    $this->processResults($input, $api, $results, $srcDirectoryPath, $dstDirectoryPath);
                }
            }
        }
    }

    protected function shouldDecodeFile(\SplFileInfo $file, string $srcDirectoryPath, string $dstDirectoryPath): bool
    {
        if (str_contains((string) $file->openFile()->fread(4096), 'ionCube Loader')) {
            $destFilePath = $this->calculateDestFilePath($file, $srcDirectoryPath, $dstDirectoryPath);
            if (is_file($destFilePath)) {
                if (filesize($destFilePath)) {
                    return false;
                } else {
                    @unlink($destFilePath);
                }
            }

            return true;
        }

        return false;
    }

    protected function calculateDestFilePath(\SplFileInfo $file, string $srcDirectoryPath, string $dstDirectoryPath): string
    {
        return $dstDirectoryPath.substr($file->getPathname(), strlen($srcDirectoryPath));
    }
}
