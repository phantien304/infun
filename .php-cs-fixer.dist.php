<?php
return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'class_attributes_separation' => [
            'elements' => ['method' => 'one'],
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__ . '/app')
            ->in(__DIR__ . '/config')
            ->in(__DIR__ . '/database')
            ->in(__DIR__ . '/routes')
            ->in(__DIR__ . '/tests')
    );
