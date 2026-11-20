<?php

namespace MicroUnit\Coverage;

/**
 * One coverage measurement: intercepts includes of measured files while tests run and turns the
 * probe counters into CoverageData afterwards. Files that were never loaded are reported with
 * zero hits, so untested files lower the percentage as they should.
 */
final class CoverageSession
{
    /** @var array<string, InstrumentedSource> files instrumented during this run */
    private array $instrumented = [];

    /** MicroUnit's own coverage engine, which must never instrument itself */
    private readonly string $engineDirectory;

    private function __construct(
        private readonly SourceSelection $selection,
        private readonly InstrumentationCache $cache,
    ) {
        $this->engineDirectory = SourceSelection::normalise(__DIR__) . DIRECTORY_SEPARATOR;
    }

    public static function start(SourceSelection $selection, string $cacheDirectory): self
    {
        $session = new self($selection, new InstrumentationCache($cacheDirectory));
        $session->warnAboutMeasurementGaps();
        IncludeInterceptor::register($session->instrumentForInclude(...));

        return $session;
    }

    public function collect(): CoverageData
    {
        IncludeInterceptor::unregister();

        $files = [];
        foreach ($this->selection->files() as $file) {
            $source = $this->instrumented[$file] ?? $this->sourceOf($file);
            if ($source === null) {
                continue;
            }
            $hits = Probe::$hits[$file] ?? [];
            $lines = [];
            foreach ($source->executableLines as $line) {
                $lines[$line] = $hits[$line] ?? 0;
            }
            $files[$file] = ['lines' => $lines, 'functions' => $source->functions];
        }

        return new CoverageData($files);
    }

    private function instrumentForInclude(string $path): ?string
    {
        $file = $this->selection->measuredFile($path);
        if ($file === null || str_starts_with($file, $this->engineDirectory)) {
            return null;
        }

        $source = $this->sourceOf($file);
        if ($source === null) {
            return null;
        }

        $this->instrumented[$file] = $source;
        Probe::register($file, $source->executableLines);

        return $source->code;
    }

    private function sourceOf(string $file): ?InstrumentedSource
    {
        $cached = $this->cache->get($file);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $source = Instrumenter::instrument((string) file_get_contents($file), $file);
        } catch (InstrumentationException $exception) {
            fwrite(STDERR, "Coverage: $file is not measured ({$exception->getMessage()})." . PHP_EOL);
            return null;
        }
        $this->cache->put($file, $source);

        return $source;
    }

    private function warnAboutMeasurementGaps(): void
    {
        $preloaded = array_values(array_filter(get_included_files(), $this->selection->contains(...)));
        if ($preloaded !== []) {
            fwrite(STDERR, sprintf(
                'Coverage: %d file(s) were loaded before coverage started and cannot be measured, e.g. %s. '
                . 'Avoid including source files from microunit.config.php.%s',
                count($preloaded),
                $preloaded[0],
                PHP_EOL,
            ));
        }

        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        if (is_array($opcache) && ($opcache['opcache_enabled'] ?? false)) {
            fwrite(STDERR, 'Coverage: OPcache is enabled for the CLI and may bypass instrumentation. Run PHP with -d opcache.enable_cli=0.' . PHP_EOL);
        }
    }
}
