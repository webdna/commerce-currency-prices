<?php

namespace webdna\commerce\currencyprices\tests\integration;

use Codeception\Test\Unit;
use Craft;
use craft\db\Query;
use webdna\commerce\currencyprices\migrations\m261001_000000_currency_prices_4;
use webdna\commerce\currencyprices\records\PriceRecord;
use webdna\commerce\currencyprices\tests\Support\CommerceFixtures;

/**
 * The 3.x → 4.0 upgrade's copy (task 3.2, BR-18): one row per accepted, non-store currency, in the primary
 * store; deleted elements, other currencies and negative prices are counted, not copied; a second run
 * writes nothing. The real Commerce 4 → 5 upgrade (TS-8, BR-19, TN-9) is run on the craft4-commerce fixture.
 *
 * The 3.x table is a TEMPORARY table, because a CREATE TABLE would commit the test's transaction.
 */
class UpgradeMigrationTest extends Unit
{
    use CommerceFixtures;

    protected function _before(): void
    {
        $this->setUpGbpStore();

        $db = Craft::$app->getDb();
        $table = $db->quoteTableName($db->getSchema()->getRawTableName(m261001_000000_currency_prices_4::LEGACY_PRICE_TABLE));
        $db->createCommand("CREATE TEMPORARY TABLE $table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            purchasableId INT NOT NULL,
            siteId INT NULL,
            GBP DECIMAL(14,4) NULL,
            USD DECIMAL(14,4) NULL,
            EUR DECIMAL(14,4) NULL,
            JPY DECIMAL(14,4) NULL,
            dateCreated DATETIME NOT NULL,
            dateUpdated DATETIME NOT NULL,
            uid CHAR(36) NOT NULL DEFAULT '0'
        )")->execute();
        $db->getSchema()->refreshTableSchema(m261001_000000_currency_prices_4::LEGACY_PRICE_TABLE);
    }

    protected function _after(): void
    {
        $db = Craft::$app->getDb();
        $table = $db->quoteTableName($db->getSchema()->getRawTableName(m261001_000000_currency_prices_4::LEGACY_PRICE_TABLE));
        $db->createCommand("DROP TEMPORARY TABLE IF EXISTS $table")->execute();
        $db->getSchema()->refreshTableSchema(m261001_000000_currency_prices_4::LEGACY_PRICE_TABLE);
    }

    public function testEachAcceptedPriceIsCopiedToThePrimaryStoreAndTheRestAreCounted(): void
    {
        $priced = $this->createVariant();
        $partlyPriced = $this->createVariant();
        $negative = $this->createVariant();
        $deletedId = $priced->id + 100000;

        $this->insertLegacyRow($priced->id, ['GBP' => 50, 'USD' => 62.89, 'EUR' => 20, 'JPY' => 9000]);
        $this->insertLegacyRow($partlyPriced->id, ['GBP' => 30, 'USD' => 0, 'EUR' => null]);
        $this->insertLegacyRow($negative->id, ['USD' => -1, 'EUR' => 21.5]);
        $this->insertLegacyRow($deletedId, ['USD' => 10, 'EUR' => 11]);

        $migration = $this->runMigration();

        $this->assertSame([
            'copied' => 4,
            'storeCurrency' => 2,
            'notAccepted' => 1,
            'missingElement' => 2,
            'alreadyCopied' => 0,
            'invalid' => 1,
        ], $migration->counts);

        $this->assertSame([
            [$priced->id, 'EUR', 20.0],
            [$priced->id, 'USD', 62.89],
            [$partlyPriced->id, 'USD', 0.0],
            [$negative->id, 'EUR', 21.5],
        ], $this->copiedRows([$priced->id, $partlyPriced->id, $negative->id, $deletedId]));
    }

    public function testASecondRunWritesNothing(): void
    {
        $variant = $this->createVariant();
        $this->insertLegacyRow($variant->id, ['USD' => 12, 'EUR' => 13]);

        $this->runMigration();
        $before = (new Query())->from(PriceRecord::tableName())->orderBy(['id' => SORT_ASC])->all();

        $second = $this->runMigration();

        $this->assertSame(0, $second->counts['copied']);
        $this->assertSame(2, $second->counts['alreadyCopied']);
        $this->assertSame($before, (new Query())->from(PriceRecord::tableName())->orderBy(['id' => SORT_ASC])->all());
    }

    /**
     * @param array<string, float|int|null> $prices
     */
    private function insertLegacyRow(int $purchasableId, array $prices): void
    {
        Craft::$app->getDb()->createCommand()->insert(m261001_000000_currency_prices_4::LEGACY_PRICE_TABLE, [
            'purchasableId' => $purchasableId,
            'siteId' => 1,
            ...$prices,
        ])->execute();
    }

    private function runMigration(): m261001_000000_currency_prices_4
    {
        $migration = new m261001_000000_currency_prices_4();
        ob_start();
        try {
            $this->assertTrue($migration->safeUp());
        } finally {
            ob_end_clean();
        }

        return $migration;
    }

    /**
     * @param int[] $purchasableIds
     * @return array<array{int, string, float}> [purchasableId, currency, price] in the primary store
     */
    private function copiedRows(array $purchasableIds): array
    {
        return array_map(
            fn(array $row) => [(int)$row['purchasableId'], $row['currency'], (float)$row['price']],
            (new Query())
                ->select(['purchasableId', 'currency', 'price'])
                ->from(PriceRecord::tableName())
                ->where(['storeId' => $this->store->id, 'purchasableId' => $purchasableIds])
                ->orderBy(['purchasableId' => SORT_ASC, 'currency' => SORT_ASC])
                ->all(),
        );
    }
}
