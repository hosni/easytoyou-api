<?php

namespace Hosni\EasytoyouApi\Commands;

use Illuminate\Container\Container;
use Symfony\Component\Console\Command\Command as ParentCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends ParentCommand
{
    protected Container $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure(): void
    {
        $dir = $this->app->make('bin-dir-path');
        $this->addOption('--username', null, InputArgument::OPTIONAL, 'Username of your easytoyou account', getenv('ETY_USERNAME') ?: null);
        $this->addOption('--password', null, InputArgument::OPTIONAL, 'Password of your easytoyou account', getenv('ETY_PASSWORD') ?: null);
        $this->addOption('--password-env', null, InputArgument::OPTIONAL, 'The env that use to fetch password', 'ETY_PASSWORD');
        $this->addOption('--proxy', null, InputArgument::OPTIONAL, 'The proxy to use', getenv('ETY_PROXY') ?: getenv('HTTP_PROXY') ?: getenv('HTTPS_PROXY'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}
