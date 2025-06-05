<?php

class MicroUnitConfigBuilder
{
    //     /** 
    //  * @param array<ITestWriter> $testWriters 
    //  * @param array<string> $testFilePatterns
    //  */
    // public function __construct(
    //     public readonly string $testDirectory = __DIR__ . '/../../';
    //     public readonly array $testWriters = [new StringTestWriter()],
    //     public readonly array $testFilePatterns = ['*-tests.php'],
    //     public readonly bool $stopOnFailure = false,
    //     public readonly ?bool $bootstrapFile = null
    // ) {}

    public ?string $testDirectory;
    public array $testWriters;
    public array $testFilePatterns;
    public ?bool $stopOnFailure;
    public ?bool $bootstrapFile;


    private function __construct() {}

    public static function create(): MicroUnitConfigBuilder
    {
        return new MicroUnitConfigBuilder();
    }

    public function withTestDir(string $testDirectory): MicroUnitConfigBuilder
    {
        $this->testDirectory = $testDirectory;
        return $this;
    }

    public function withTestWriters(ITestWriter ...$writers): MicroUnitConfigBuilder
    {
        $this->testWriters = $writers;
        return $this;
    }

    public function addTestWriter(ITestWriter $writer): MicroUnitConfigBuilder
    {
        if (!isset($this->testWriters)) {
            $this->testWriters = [];
        }
        $this->testWriters[] = $writer;
        return $this;
    }

    public function withTestFilePatterns(string ...$testFilePatterns): MicroUnitConfigBuilder
    {
        $this->testFilePatterns = $testFilePatterns;
        return $this;
    }

    public function addTestFilePattern(string $pattern): MicroUnitConfigBuilder
    {
        if (!isset($this->testFilePatterns)) {
            $this->testFilePatterns = [];
        }
        $this->testFilePatterns[] = $pattern;
        return $this;
    }

    public function stopOnFailure(): MicroUnitConfigBuilder
    {
        $this->stopOnFailure = true;
        return $this;
    }

    public function withBootstrapFile(string $bootstrapFile): MicroUnitConfigBuilder
    {
        $this->bootstrapFile = $bootstrapFile;
        return $this;
    }

    public function build()
    {
        $defaultConfig = new MicroUnitConfig();
        return new MicroUnitConfig(
            $this->testDirectory ?? $defaultConfig->testDirectory,
            $this->testWriters ?? $defaultConfig->testWriters,
            $this->testFilePatterns ?? $defaultConfig->testFilePatterns,
            $this->stopOnFailure ?? $defaultConfig->stopOnFailure,
            $this->bootstrapFile ?? $defaultConfig->bootstrapFile,
        );
    }
}
