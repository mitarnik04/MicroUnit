<?php

namespace MicroUnit\Migrate;

final readonly class ParsedMethod
{
    /**
     * @param list<array{name: string, arguments: ?string}> $attributes short attribute names and their argument text
     * @param string $parameters the text between the parentheses
     * @param string|null $body "{ ... }", or null for abstract methods
     * @param int $bodyLine the line the body starts on
     */
    public function __construct(
        public string $name,
        public bool $isStatic,
        public array $attributes,
        public ?string $docComment,
        public string $parameters,
        public ?string $returnType,
        public ?string $body,
        public int $bodyLine,
    ) {}

    /** A test method as PHPUnit sees it: test* or marked with #[Test] / @test, and not abstract or static. */
    public function isTest(): bool
    {
        return $this->body !== null && !$this->isStatic
            && (str_starts_with(strtolower($this->name), 'test') || $this->hasAttribute('Test') || $this->annotation('test') !== []);
    }

    /** @return list<string> argument texts of every attribute with this short name */
    public function attributeArguments(string $name): array
    {
        return self::argumentsOf($this->attributes, $name);
    }

    public function hasAttribute(string $name): bool
    {
        return $this->attributeArguments($name) !== [];
    }

    /** @return list<string> values of a docblock annotation, e.g. "@dataProvider additions" => ["additions"] */
    public function annotation(string $name): array
    {
        if ($this->docComment === null || preg_match_all('/@' . preg_quote($name, '/') . '\b[ \t]*([^\r\n*]*)/', $this->docComment, $matches) === 0) {
            return [];
        }

        return array_map('trim', $matches[1]);
    }

    /**
     * The data sets of `@testWith`: one JSON array per line, on the annotation's line and the lines
     * right after it.
     *
     * @return list<string>
     */
    public function testWithDataSets(): array
    {
        $sets = [];
        $isInBlock = false;
        foreach (preg_split('/\R/', (string) $this->docComment) ?: [] as $line) {
            $line = trim((string) preg_replace(['~^\s*/?\*+~', '~\*/\s*$~'], '', $line));
            if (preg_match('/^@testWith\b\s*(.*)$/', $line, $match) === 1) {
                $isInBlock = true;
                $line = trim($match[1]);
            } elseif (!$isInBlock || !str_starts_with($line, '[')) {
                $isInBlock = false;
                continue;
            }
            if ($line !== '') {
                $sets[] = $line;
            }
        }

        return $sets;
    }

    /**
     * @param list<array{name: string, arguments: ?string}> $attributes
     * @return list<string> argument texts of every attribute with this short name
     */
    public static function argumentsOf(array $attributes, string $name): array
    {
        return array_values(array_map(
            static fn(array $attribute): string => $attribute['arguments'] ?? '',
            array_filter($attributes, static fn(array $attribute): bool => strcasecmp($attribute['name'], $name) === 0),
        ));
    }
}
