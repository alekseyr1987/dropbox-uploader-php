<?php

use PhpCsFixer\Finder;
use PhpCsFixer\Config;

$finder = (new Finder())
    ->in(
        [
            __DIR__.'/src',
        ]
    )
;

return (new Config())
    ->setRules(
        [
            '@PhpCsFixer' => true,
        ]
    )
    ->setFinder($finder)
;
