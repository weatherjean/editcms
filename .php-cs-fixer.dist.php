<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/_edit/core', __DIR__ . '/_edit/api', __DIR__ . '/_edit/admin-api', __DIR__ . '/tests'])
    ->exclude('ThirdParty')
    ->append([__DIR__ . '/router.php'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setUsingCache(false)
    ->setRules([
        '@PSR12' => true,
        'binary_operator_spaces' => ['default' => 'single_space'],
        'concat_space' => ['spacing' => 'one'],
        'control_structure_braces' => true,
        'class_attributes_separation' => ['elements' => ['method' => 'one']],
        'whitespace_after_comma_in_array' => true,
        'space_after_semicolon' => true,
        'statement_indentation' => true,
        'no_multiple_statements_per_line' => true,
    ])
    ->setFinder($finder);
