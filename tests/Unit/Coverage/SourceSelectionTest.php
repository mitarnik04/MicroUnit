<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Coverage\SourceSelection;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Toolkit\TemporaryDirectory;

$tester = TestSetup::getTester('SourceSelection');

$tester->fixture('project', function (): TemporaryDirectory {
    $project = TemporaryDirectory::create();
    $project->write('src/Order.php', '<?php');
    $project->write('src/Generated/Proxy.php', '<?php');
    $project->write('src/notes.txt', 'not php');
    $project->write('srcExtra/Other.php', '<?php');

    return $project;
}, cleanup: fn(TemporaryDirectory $project) => $project->delete());

$tester->define('measures_php_files_under_include_minus_exclude', function (TemporaryDirectory $project): void {
    $selection = new SourceSelection([$project->path('src')], [$project->path('src/Generated')]);

    Assert::exact([SourceSelection::normalise($project->path('src/Order.php'))], $selection->files());
    Assert::isTrue($selection->contains($project->path('src/Order.php')));
    Assert::isFalse($selection->contains($project->path('src/Generated/Proxy.php')));
    Assert::isFalse($selection->contains($project->path('src/notes.txt')));
    Assert::isFalse($selection->contains($project->path('srcExtra/Other.php')));
});

$tester->define('measured_file_returns_the_normalised_path', function (TemporaryDirectory $project): void {
    $selection = new SourceSelection([$project->path('src')]);

    Assert::exact(SourceSelection::normalise($project->path('src/Order.php')), $selection->measuredFile($project->path('src/./Order.php')));
    Assert::isNull($selection->measuredFile($project->path('srcExtra/Other.php')));
});
