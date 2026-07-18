<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    // Aligne le code sur la version PHP minimale du projet (composer.json : >=8.4).
    ->withPhpSets(php84: true)
    // Modernisation progressive : qualité, code mort, déclarations de types.
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    // Aligne aussi sur la dernière version PHP LTS gérée.
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
    ])
    ->withImportNames(removeUnusedImports: true);
