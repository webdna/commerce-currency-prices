<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\migrations;

use Craft;
use craft\commerce\db\Table as CommerceTable;
use craft\db\Migration;
use craft\db\Table;
use webdna\commerce\currencyprices\records\PriceRecord;
use webdna\commerce\currencyprices\services\Purchasables;

/**
 * Fresh install: the price table, one row per purchasable × store × payment currency (spec §4).
 *
 * Deleting a purchasable or a store removes its rows through the foreign keys. Commerce's own tables
 * are never altered (B3). The Currency prices element is added below the price on every variant layout
 * (BR-2), and taken out again on uninstall.
 *
 * The 3.x install (a column per currency, foreign keys on `commerce_paymentcurrencies.iso`) is at
 * `734fa63:src/migrations/Install.php`, for the upgrade tasks 3.1 and 3.2.
 *
 * @author webdna
 * @since 1.0.0
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createPriceTable();

        // When the install arrives in project config, the layouts it changed arrive with it. Craft runs this
        // before it registers the plugin, so CurrencyPrices::getInstance() is null here: build the service.
        if (!Craft::$app->getProjectConfig()->getIsApplyingExternalChanges()) {
            (new Purchasables())->addToVariantLayouts();
        }

        return true;
    }

    public function safeDown(): bool
    {
        if (!Craft::$app->getProjectConfig()->getIsApplyingExternalChanges()) {
            (new Purchasables())->removeFromVariantLayouts();
        }

        $this->dropTableIfExists(PriceRecord::tableName());

        return true;
    }

    private function createPriceTable(): void
    {
        if ($this->db->tableExists(PriceRecord::tableName())) {
            return;
        }

        $this->createTable(PriceRecord::tableName(), [
            'id' => $this->primaryKey(),
            'purchasableId' => $this->integer()->notNull(),
            'storeId' => $this->integer()->notNull(),
            'currency' => $this->char(3)->notNull(),
            'price' => $this->decimal(14, 4)->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // The unique key is what keeps two editors' simultaneous saves to one row (TN-14).
        $this->createIndex(null, PriceRecord::tableName(), ['purchasableId', 'storeId', 'currency'], true);
        $this->createIndex(null, PriceRecord::tableName(), ['storeId', 'currency']);

        $this->addForeignKey(null, PriceRecord::tableName(), ['purchasableId'], Table::ELEMENTS, ['id'], 'CASCADE');
        $this->addForeignKey(null, PriceRecord::tableName(), ['storeId'], CommerceTable::STORES, ['id'], 'CASCADE');
    }
}
