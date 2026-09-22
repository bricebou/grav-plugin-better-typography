<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/better-typography.php',
        __DIR__ . '/classes',
        __DIR__ . '/tests',
        __DIR__ . '/rector.php',
        __DIR__ . '/ecs.php',
    ])
    ->withSkip([
        __DIR__ . '/vendor',
    ])
    ->withRootFiles()
    ->withPreparedSets(
        psr12: true,
        common: true,
        cleanCode: true,
    )
    ->withConfiguredRule(OrderedImportsFixer::class, [
        'imports_order' => ['class', 'function', 'const'],
        'sort_algorithm' => 'alpha',
    ])
    ->withSkip([
        // Keep explanatory docblocks: the Grav plugin API is loosely typed.
        NoSuperfluousPhpdocTagsFixer::class,
    ]);
