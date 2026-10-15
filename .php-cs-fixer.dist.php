<?php

// Code style of the project: the one of Symfony itself (https://cs.symfony.com/doc/ruleSets/Symfony.html)
$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'config']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
    ])
    ->setFinder($finder);
