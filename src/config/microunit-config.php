<?php
require_once __DIR__ . '/../core/output/string-test-writer.php';

class MicroUnitConfig
{
    /** 
     * @param array<ITestWriter> $testWriters 
     * @param array<string> $testFilePatterns
     */
    public function __construct(
        public readonly ?string $testDirectory = null,
        public readonly array $testWriters = [new StringTestWriter()],
        public readonly array $testFilePatterns = ['*-tests.php'],
        public readonly bool $stopOnFailure = false,
        public readonly ?bool $bootstrapFile = null
    ) {}
}
