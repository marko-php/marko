<?php

declare(strict_types=1);
use Marko\Testing\TestCase;

// Project-wide Pest bootstrap. Binds Marko\Testing\TestCase as the base test
// case for all tests, which registers PSR-4 autoloaders for app/* and modules/*
// so that module classes resolve without per-project Composer classmap setup.
uses(TestCase::class)->in(__DIR__);
