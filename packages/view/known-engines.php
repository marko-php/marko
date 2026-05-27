<?php

declare(strict_types=1);

return [
    'twig' => [
        'extension' => '.twig',
        'driver' => 'marko/view-twig',
        'description' => 'Twig — recommended for broader PHP ecosystem familiarity (Symfony, Drupal, Craft CMS)',
    ],
    'latte' => [
        'extension' => '.latte',
        'driver' => 'marko/view-latte',
        'description' => 'Latte — compile-time safety, n:attribute syntax, Nette ecosystem',
    ],
];
