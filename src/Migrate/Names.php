<?php

namespace MicroUnit\Migrate;

/** Resolves class names the way PHP does inside a file: through its namespace and use statements. */
final readonly class Names
{
    /** @param array<string, string> $imports lowercase alias => fully qualified name */
    private function __construct(
        private ?string $namespace,
        private array $imports,
    ) {}

    /** @param list<string> $useStatements verbatim "use ..." statements of the file */
    public static function of(?string $namespace, array $useStatements): self
    {
        $imports = [];
        foreach ($useStatements as $statement) {
            $statement = trim(substr($statement, 3));
            if (preg_match('/^(function|const)\s/i', $statement)) {
                continue;
            }
            if (preg_match('/^([\w\\\\]+)\\\\\{(.+)\}$/s', $statement, $group)) {
                $names = array_map(static fn(string $name): string => rtrim($group[1], '\\') . '\\' . trim($name), explode(',', $group[2]));
            } else {
                $names = explode(',', $statement);
            }
            foreach ($names as $name) {
                $parts = preg_split('/\s+as\s+/i', trim($name)) ?: [];
                $fullName = ltrim($parts[0], '\\');
                $alias = $parts[1] ?? substr($fullName, (int) strrpos('\\' . $fullName, '\\'));
                $imports[strtolower($alias)] = $fullName;
            }
        }

        return new self($namespace, $imports);
    }

    public function resolve(string $name): string
    {
        if (str_starts_with($name, '\\')) {
            return substr($name, 1);
        }
        $first = strtolower(explode('\\', $name)[0]);
        if (isset($this->imports[$first])) {
            $rest = substr($name, strlen($first));

            return $this->imports[$first] . $rest;
        }

        return ($this->namespace === null ? '' : $this->namespace . '\\') . $name;
    }

    /** @return array<string, string> lowercase alias => fully qualified name */
    public function imports(): array
    {
        return $this->imports;
    }
}
