<?php

namespace Hosni\EasytoyouApi;

use Illuminate\Container\Container;
use Symfony\Component\Console\Application;

error_reporting(E_ALL &~ E_DEPRECATED);

$container = Container::getInstance();

$container->singleton(Application::class, function (Container $container): Application {
    $app = new Application();
    $app->add(new Commands\DecodeDirectory($container));

    return $app;
});

$container->singleton('bin-path', function (): string {
    $pharPath = \Phar::running(false);
    if ($pharPath) {
        return $pharPath;
    }

    return dirname(__DIR__).'/bin/ety-decoder';
});

$container->singleton('bin-dir-path', function (): string {
    $pharPath = \Phar::running(false);
    if ($pharPath) {
        return dirname($pharPath);
    }

    return dirname(__DIR__);
});

return $container;