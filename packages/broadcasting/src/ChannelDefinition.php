<?php

declare(strict_types=1);

namespace Marko\Broadcasting;

readonly class ChannelDefinition
{
    /**
     * The characters a {param} placeholder may capture. Deliberately narrow: no dots (one
     * segment per parameter), and nothing a driver could read as syntax, such as the braces,
     * commas and asterisks of URI templates and topic selectors, whitespace or control characters.
     */
    public const string PLACEHOLDER_PATTERN = '[A-Za-z0-9_\-=@]+';

    /**
     * @param class-string<ChannelAuthorizerInterface|PresenceChannelAuthorizerInterface> $authorizerClass
     */
    public function __construct(
        public string $pattern,
        public string $authorizerClass,
    ) {}

    /**
     * Match a channel name against the pattern.
     *
     * @return array<string, string>|null Captured placeholder values, or null when the name does not match
     */
    public function match(
        string $channelName,
    ): ?array {
        $regex = preg_replace_callback(
            '/\\\\\{([A-Za-z_][A-Za-z0-9_]*)\\\\}/',
            fn (array $matches): string => '(?P<' . $matches[1] . '>' . self::PLACEHOLDER_PATTERN . ')',
            preg_quote($this->pattern, '#'),
        );

        if (preg_match('#\A' . $regex . '\z#', $channelName, $matches) !== 1) {
            return null;
        }

        return array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY);
    }
}
