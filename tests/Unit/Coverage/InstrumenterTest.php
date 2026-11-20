<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Coverage\Instrumenter;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('Instrumenter');

/** Instruments $code and asserts that the result is still valid PHP. */
function instrumentedSource(string $code): MicroUnit\Coverage\InstrumentedSource
{
    $source = Instrumenter::instrument($code, '/app/example.php');
    Assert::doesNotThrow(fn() => PhpToken::tokenize($source->code, TOKEN_PARSE), 'The instrumented code does not parse.');

    return $source;
}

$tester->define('probes_every_statement_on_its_own_line', function (): void {
    $source = instrumentedSource("<?php\n\$a = 1;\nif (\$a) {\n    echo \$a;\n}\n");

    Assert::exact([2, 3, 4], $source->executableLines);
    Assert::exact(5, substr_count($source->code, "\n"));
});

$tester->define('a_body_without_braces_ending_at_a_close_tag_stays_valid', function (): void {
    $source = instrumentedSource("<?php if (\$a) echo 'x' ?>\n<p>html</p>\n");

    Assert::exact([1], $source->executableLines);
});

$tester->define('hooks_of_promoted_properties_are_named_after_class_and_property', function (): void {
    $source = instrumentedSource("<?php\nclass Order {\n    public function __construct(public int \$total { get { return 1; } }) {}\n}\n");

    Assert::exact(['Order::$total::get', 'Order::__construct'], array_column($source->functions, 'name'));
});
