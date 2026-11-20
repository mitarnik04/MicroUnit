<?php

namespace MicroUnit\Output;

use MicroUnit\Output\Format\MinimalResultFormatter;
use MicroUnit\Output\Format\TextStyle;

/** Compact console output: one line per test, details only for failures. The default writer. */
final class MinimalStringTestWriter extends TextTestWriter
{
    /** @param bool|null $colors null = auto-detect (off in CI logs without TTY, honours NO_COLOR/FORCE_COLOR) */
    public function __construct(?bool $colors = null)
    {
        parent::__construct(new MinimalResultFormatter(TextStyle::forStream(STDOUT, $colors)));
    }

    #[\Override]
    protected function write(string $text): void
    {
        echo $text;
    }
}
