<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__.'/src', __DIR__.'/tests'])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        // Style maison : alignement vertical des `=>` et `=` (surcharge @Symfony).
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'align_single_space_minimal',
                '=' => 'align_single_space_minimal',
            ],
        ],
        // Corps de méthode/constructeur vides gardés sur une seule ligne : `{}`.
        'single_line_empty_body' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/var/cache/.php-cs-fixer.cache')
    ;
