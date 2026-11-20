<?php

namespace MicroUnit\Migrate;

/** The parts of phpunit.xml a migrated suite needs: bootstrap file, <env>/<server> variables and <ini> settings. */
final readonly class PhpUnitConfiguration
{
    private const array FILES = ['phpunit.xml', 'phpunit.xml.dist', 'phpunit.dist.xml'];

    /**
     * @param array<string, string> $environment
     * @param array<string, string> $ini
     */
    private function __construct(
        public ?string $bootstrap,
        public array $environment,
        public array $ini,
    ) {}

    public static function read(string $projectDirectory): self
    {
        foreach (self::FILES as $name) {
            $xml = @file_get_contents($projectDirectory . DIRECTORY_SEPARATOR . $name);
            if ($xml !== false) {
                return self::parse($xml);
            }
        }

        return new self(null, [], []);
    }

    public static function parse(string $xml): self
    {
        $bootstrap = preg_match('/<phpunit\b[^>]*\bbootstrap="([^"]+)"/s', $xml, $match) ? (string) preg_replace('~^(\.[/\\\\])+~', '', $match[1]) : null;
        if ($bootstrap === 'vendor/autoload.php') {
            $bootstrap = null; // MicroUnit loads Composer's autoloader itself
        }

        $environment = [];
        $ini = [];
        preg_match_all('/<(env|server|ini)\s+([^>]*?)\/?>/s', $xml, $elements, PREG_SET_ORDER);
        foreach ($elements as [, $kind, $attributes]) {
            preg_match_all('/(\w+)="([^"]*)"/', $attributes, $pairs);
            $values = array_combine($pairs[1], array_map(static fn(string $value): string => html_entity_decode($value, ENT_QUOTES | ENT_XML1), $pairs[2]));
            if (!isset($values['name'])) {
                continue;
            }
            if ($kind === 'ini') {
                $ini[$values['name']] = $values['value'] ?? '';
            } else {
                $environment[$values['name']] = $values['value'] ?? '';
            }
        }

        return new self($bootstrap, $environment, $ini);
    }

    /** The `environment: [...]` argument for a framework integration's constructor, '' without <env>/<server> variables. */
    public function environmentArgument(): string
    {
        if ($this->environment === []) {
            return '';
        }
        $pairs = array_map(
            static fn(string $name, string $value): string => var_export($name, true) . ' => ' . var_export($value, true),
            array_keys($this->environment),
            $this->environment,
        );

        return 'environment: [' . implode(', ', $pairs) . ']';
    }
}
