<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

class EnvironmentException extends MarkoException
{
    public static function alreadyRecording(): self
    {
        return new self(
            message: 'Environment variable reads are already being recorded',
            context: 'While starting to record environment variable reads',
            suggestion: 'Call EnvironmentVariables::stopRecording() before starting a new recording.',
        );
    }

    public static function notRecording(): self
    {
        return new self(
            message: 'Environment variable reads are not being recorded',
            context: 'While stopping a recording of environment variable reads',
            suggestion: 'Call EnvironmentVariables::startRecording() first.',
        );
    }
}
