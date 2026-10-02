<?php

namespace webdna\commerce\currencyprices\migrations;

use craft\db\Migration;

/**
 * m261002_000000_drop_iso_foreign_keys migration.
 *
 * Drops the foreign keys from this plugin's tables to `commerce_paymentcurrencies.iso`. Commerce 5's
 * m230920_051125 drops the unique index on that column, which MySQL and MariaDB refuse while a
 * foreign key needs it, so a site with these keys cannot upgrade to Commerce 5.
 */
class m261002_000000_drop_iso_foreign_keys extends Migration
{
    private array $_tables = [
        '{{%commerce_shippingrule_categories_currencyprices}}',
        '{{%commerce_shippingrules_currencyprices}}',
        '{{%commerce_discounts_currencyprices}}',
    ];

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        foreach ($this->_tables as $table) {
            if ($this->db->tableExists($table)) {
                $this->dropForeignKeyIfExists($table, ['paymentCurrencyIso']);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m261002_000000_drop_iso_foreign_keys cannot be reverted.\n";
        return false;
    }
}
