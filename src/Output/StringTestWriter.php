<?php

namespace MicroUnit\Output;

use MicroUnit\Output\Format\DetailedResultFormatter;
use MicroUnit\Output\Format\TextStyle;

/** Detailed console output: return values, captured output, locations and stack traces. */
final class StringTestWriter extends TextTestWriter
{
    /** @param bool|null $colors null = auto-detect */
    public function __construct(?bool $colors = null)
    {
        parent::__construct(new DetailedResultFormatter(TextStyle::forStream(STDOUT, $colors)));
    }

    #[\Override]
    protected function write(string $text): void
    {
        echo $text;
    }
}
