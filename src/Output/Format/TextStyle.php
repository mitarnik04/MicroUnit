<?php

namespace MicroUnit\Output\Format;

/** Optional ANSI colouring. With colours disabled every method returns its input unchanged. */
final readonly class TextStyle
{
    public function __construct(
        public bool $hasColors,
    ) {}

    public static function plain(): self
    {
        return new self(false);
    }

    /**
     * @param resource $stream
     * @param bool|null $colors null = detect (TTY, NO_COLOR and FORCE_COLOR are honoured)
     */
    public static function forStream($stream, ?bool $colors = null): self
    {
        return new self($colors ?? self::supportsColors($stream));
    }

    public function green(string $text): string
    {
        return $this->wrap($text, '32');
    }

    public function red(string $text): string
    {
        return $this->wrap($text, '31');
    }

    public function yellow(string $text): string
    {
        return $this->wrap($text, '33');
    }

    public function cyan(string $text): string
    {
        return $this->wrap($text, '36');
    }

    public function dim(string $text): string
    {
        return $this->wrap($text, '2');
    }

    public function bold(string $text): string
    {
        return $this->wrap($text, '1');
    }

    private function wrap(string $text, string $code): string
    {
        return $this->hasColors && $text !== '' ? "\e[{$code}m{$text}\e[0m" : $text;
    }

    /** CI systems whose build logs render ANSI colours although stdout is not a terminal. */
    private const array COLOR_CAPABLE_CI_VARIABLES = ['GITHUB_ACTIONS', 'GITLAB_CI', 'TF_BUILD', 'CIRCLECI', 'BUILDKITE', 'TEAMCITY_VERSION', 'DRONE'];

    /**
     * Colour is the default. It is turned off by NO_COLOR, and when output goes to a pipe or file
     * outside a CI system that renders colours (so log files never fill up with escape codes).
     *
     * @param resource $stream
     */
    private static function supportsColors($stream): bool
    {
        if ((string) getenv('NO_COLOR') !== '') {
            return false;
        }
        if ((string) getenv('FORCE_COLOR') !== '') {
            return true;
        }
        if (array_any(self::COLOR_CAPABLE_CI_VARIABLES, static fn(string $variable): bool => (string) getenv($variable) !== '')) {
            return true;
        }
        if (!stream_isatty($stream)) {
            return false;
        }

        return PHP_OS_FAMILY !== 'Windows' || sapi_windows_vt100_support($stream, true);
    }
}
