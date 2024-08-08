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

class DecodeFile extends Command
{
    protected static $defaultName = 'decode-file';

    protected static $defaultDescription = 'Decode ionCube file';

    protected function configure(): void
    {
        parent::configure();
        $this->addOption('--manifest-file', null, InputArgument::OPTIONAL, 'The manifest file path to store report');
        $this->addOption('--decoder', null, InputArgument::OPTIONAL, 'Default decoder to decode', Ic11Php74::class);
        $this->addArgument('--src', InputArgument::OPTIONAL, 'The source file to decode');
        $this->addArgument('--dest', InputArgument::OPTIONAL, 'The dest file to store');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        /** @var string|null */
        $manifestFile = $input->getOption('manifest-file');
        if (!$manifestFile) {
            $manifestFile = '/home/hosni/w/easytoyou-manifest.json';
            // $manifestFile = rtrim($dstDirectoryPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'easytoyou-manifest.json';
        }

        /** @var string|null */
        $srcFile = $input->getArgument('--src');
        /** @var string|null */
        $dstFile = $input->getArgument('--dest');
        if (!$srcFile || !$dstFile) {
            throw new \Exception('You should pass --src and --dest paths.');
        }
        $pathResolver = new PathResolver();
        $srcFile = $pathResolver->resolve(dirname(__FILE__, 3), $srcFile);
        $dstFile = $pathResolver->resolve(dirname(__FILE__, 3), $dstFile);
        echo "Source file: {$srcFile}".PHP_EOL;
        echo "Destination file: {$dstFile}".PHP_EOL;

        if (!is_file($srcFile)) {
            throw new \Exception('The --src should be file.');
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

        $result = $api->decode(new \SplFileInfo($srcFile));
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

            return Command::FAILURE;
        }
        $sinkPathDirectory = dirname($dstFile);
        if (!is_dir($sinkPathDirectory)) {
            mkdir($sinkPathDirectory, 0755, true);
        }

        echo PHP_EOL.$result->getEncodedFile()->getPathname().PHP_EOL.
            "-> Downloding: {$result->getDecodedFileUrl()} to {$dstFile}".PHP_EOL.PHP_EOL;
        $api->getHttpClient()->get(
            $result->getDecodedFileUrl(), [
                'sink' => $dstFile,
            ]
        );

        return Command::SUCCESS;
    }
}
