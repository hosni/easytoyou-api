<?php

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__])
    ->path('src')
;

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@Symfony' => true,
        'phpdoc_to_comment' => false,
    ])
    // ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setFinder($finder)
;
