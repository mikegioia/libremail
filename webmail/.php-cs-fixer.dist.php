<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude([
        'config',
        'tools',
        'vendor',
        'views'
    ])
    ->name('libremail')
    ->name('server')
    ->name('sync')
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'new_with_braces' => false,
        'trailing_comma_in_multiline' => false,
        'not_operator_with_successor_space' => true,
        'phpdoc_align' => false,
        'phpdoc_summary' => false,
        'function_declaration' => [
            'closure_function_spacing' => 'one'
        ],
        'blank_line_before_statement' => [
            'statements' => [
                 'return', 'try', 'throw'
            ]
        ]
    ])
    ->setFinder($finder)
    ->setUsingCache(false);
