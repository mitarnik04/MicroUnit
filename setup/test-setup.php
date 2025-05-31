<?php
const CORE_DIR = __DIR__ . '/../core';
const ASSERT_DIR = __DIR__ . '/../assertion';

require_once CORE_DIR . '/testWriter/string-test-writer.php';
require_once CORE_DIR . '/tester-hub.php';
require_once ASSERT_DIR . '/assert.php';
require_once ASSERT_DIR . '/assert-single.php';
require_once ASSERT_DIR . '/assert-array.php';

function getTester(string $suite): Tester
{
    static $testerHub;
    if (!isset($testerHub)) {
        $testerHub = new TesterHub(new StringTestWriter());

        register_shutdown_function(function () use ($testerHub) {
            $testerHub->runAll();
        });
    }

    return $testerHub->getOrCreateTester($suite);
}
