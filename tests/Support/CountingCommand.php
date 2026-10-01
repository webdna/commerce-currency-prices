<?php

namespace webdna\commerce\currencyprices\tests\Support;

use Craft;
use craft\db\Command;

/**
 * Counts the queries the database runs, for BR-14's "one query per order". Craft's logger doesn't keep
 * Yii's profiling messages, so the profiler can't count them.
 */
class CountingCommand extends Command
{
    public static int $count = 0;

    /**
     * The number of queries `$fn` runs.
     */
    public static function count(callable $fn): int
    {
        $db = Craft::$app->getDb();
        $driver = $db->getDriverName();
        $commandMap = $db->commandMap;
        $db->commandMap[$driver] = self::class;
        self::$count = 0;

        try {
            $fn();

            return self::$count;
        } finally {
            $db->commandMap = $commandMap;
        }
    }

    public function execute()
    {
        self::$count++;

        return parent::execute();
    }

    protected function queryInternal($method, $fetchMode = null)
    {
        self::$count++;

        return parent::queryInternal($method, $fetchMode);
    }
}
