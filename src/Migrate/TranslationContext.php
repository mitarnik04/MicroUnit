<?php

namespace MicroUnit\Migrate;

/** What the translation of one class needs to know about the migration as a whole. */
final readonly class TranslationContext
{
    /**
     * @param string $targetFile where the migrated file is written (to keep __DIR__ pointing at the original folder)
     * @param string|null $supportFile file name of the helper declarations kept from the original file
     * @param array<string, true> $functions lowercase fully qualified functions declared in the migrated files
     * @param string|null $blocker why none of the class's tests can be migrated automatically
     * @param string|null $suiteName the suite name, when the class name alone is not unique
     */
    public function __construct(
        public string $targetFile,
        public ?string $supportFile = null,
        public array $functions = [],
        public ?string $blocker = null,
        public ?string $suiteName = null,
    ) {}

    /** __DIR__ of $originalFile, written relative to the migrated file. */
    public function directoryOf(string $originalFile): string
    {
        return self::pathExpression(dirname($this->targetFile), dirname($originalFile));
    }

    /**
     * PHP code for $path as seen from a file in $directory: `__DIR__`, `__DIR__ . '/../tests'`, or
     * the absolute path when there is no relative way (another drive).
     */
    public static function pathExpression(string $directory, string $path): string
    {
        $from = explode('/', trim(str_replace('\\', '/', $directory), '/'));
        $to = explode('/', trim(str_replace('\\', '/', $path), '/'));
        $common = 0;
        while (isset($from[$common], $to[$common]) && strcasecmp($from[$common], $to[$common]) === 0) {
            $common++;
        }
        if ($common === 0 && (str_contains($from[0], ':') || str_contains($to[0], ':'))) {
            return var_export(str_replace('\\', '/', $path), true);
        }
        $relative = implode('/', [...array_fill(0, count($from) - $common, '..'), ...array_slice($to, $common)]);

        return $relative === '' ? '__DIR__' : "__DIR__ . '/$relative'";
    }
}
