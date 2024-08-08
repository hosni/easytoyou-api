<?php

namespace Hosni\EasytoyouApi\Commands;

use Hosni\EasytoyouApi\Account\Account;
use Hosni\EasytoyouApi\API;
use Hosni\EasytoyouApi\Decoders\Premium\Ic11Php74;
use Hosni\EasytoyouApi\HttpClient;
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
        $this->addOption('--manifest-file', null, InputArgument::OPTIONAL, 'The manifest file path to store report');
        $this->addOption('--decoder', null, InputArgument::OPTIONAL, 'Default decoder to decode', Ic11Php74::class);
        $this->addArgument('--src', InputArgument::OPTIONAL, 'The source directory to look for ioncubed files');
        $this->addArgument('--dest', InputArgument::OPTIONAL, 'The dest directory to store');

        $this->addOption('--watch', 'w', InputArgument::REQUIRED, 'Should watch for new files?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $shouldWatchForNewFiles = $input->hasParameterOption(['--watch', '-w'], true);
        if ($shouldWatchForNewFiles) {
            echo 'Run on watch mode!'.PHP_EOL;
        }

        /** @var string|null $srcDirectoryPath */
        $srcDirectoryPath = $input->getArgument('--src');
        /** @var string|null $dstDirectoryPath */
        $dstDirectoryPath = $input->getArgument('--dest');
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
                if (count($chunkedFiles) > 4) {
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
        /** @var string|null */
        $manifestFile = $input->getOption('manifest-file');
        if (!$manifestFile) {
            $manifestFile = rtrim($dstDirectoryPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'easytoyou-manifest.json';
        }

        static $tries = 0;
        try {
            echo 'decodeMulti:'.json_encode($files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
            $results = $api->decodeMulti($files);
            var_dump($results);
            foreach ($results as $result) {
                if ($manifestFile) { // @phpstan-ignore-line
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

                echo PHP_EOL.$result->getEncodedFile()->getPathname().PHP_EOL.
                    "-> Downloding: {$result->getDecodedFileUrl()} to {$sinkPath}".PHP_EOL.PHP_EOL;
                $api->getHttpClient()->get(
                    $result->getDecodedFileUrl(), [
                        'sink' => $sinkPath,
                    ]
                );
            }
        } catch (\Exception $e) {
            if (++$tries < 3) {
                $this->decodeFiles($input, $api, $files, $srcDirectoryPath, $dstDirectoryPath);
            } else {
                $tries = 0;
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
