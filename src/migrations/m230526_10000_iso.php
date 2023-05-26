<?php

namespace webdna\commerce\currencyprices\migrations;

use Craft;
use craft\db\Migration;

/**
 * m230526_10000_iso migration.
 */
class m230526_10000_iso extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->alterColumn('{{%commerce_shippingrule_categories_currencyprices}}', 'paymentCurrencyIso', $this->string(3)->notNull());
        $this->alterColumn('{{%commerce_shippingrules_currencyprices}}', 'paymentCurrencyIso', $this->string(3)->notNull());
        $this->alterColumn('{{%commerce_discounts_currencyprices}}', 'paymentCurrencyIso', $this->string(3)->notNull());
        $this->alterColumn('{{%addons_discounts_currencyprices}}', 'paymentCurrencyIso', $this->string(3)->notNull());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown():bool
    {
        return true;
    }
}
