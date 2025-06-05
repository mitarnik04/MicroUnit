<?php
const CORE_DIR = __DIR__ . '/../core';
const ASSERT_DIR = __DIR__ . '/../assertion';


require_once __DIR__ . '/../config/config-provider.php';
require_once __DIR__ . '/../config/microunit-config.php';
require_once CORE_DIR . '/output/write-helper.php';
require_once CORE_DIR . '/output/string-test-writer.php';
require_once CORE_DIR . '/output/minimal-string-test-writer.php';
require_once CORE_DIR . '/output/file-test-writer.php';
require_once CORE_DIR . '/tester-hub.php';
require_once ASSERT_DIR . '/assert.php';
require_once ASSERT_DIR . '/assert-single.php';
require_once ASSERT_DIR . '/assert-array.php';
require_once ASSERT_DIR . '/assert-numeric.php';


function getTester(string $suite): Tester
{
    static $testerHub;

    if (!isset($testerHub)) {
        /** @var MicroUnitConfig */
        static $config = ConfigProvider::get();
        $testerHub = new TesterHub(new WriteHelper($config->testWriters), $config->stopOnFailure);

        register_shutdown_function(function () use ($testerHub) {
            $testerHub->runAll();
        });
    }

    return $testerHub->getOrCreateTester($suite);
}
