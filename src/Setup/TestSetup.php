<?php

namespace MicroUnit\Setup;

use MicroUnit\Core\Tester;
use MicroUnit\Core\TesterHub;
use MicroUnit\Config\ConfigProvider;
use MicroUnit\Config\MicroUnitConfig;
use MicroUnit\Output\WriteHelper;


class TestSetup
{
    public static function getTester(string $suite): Tester
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
}
