<?php

namespace kuriousagency\commerce\currencyprices\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190904_142500_addons migration.
 */
class m190904_142500_addons extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable('{{%addons_discounts_currencyprices}}', [
			'id' => $this->primaryKey(),
			'discountId' => $this->integer(),
			'paymentCurrencyIso' => $this->string()->notNull(),
			'perItemDiscount' => $this->decimal(14, 4),
			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid' => $this->uid(),
		]);

		$this->createIndex(null, '{{%addons_discounts_currencyprices}}', 'discountId', false);
		$this->createIndex(null, '{{%addons_discounts_currencyprices}}', 'paymentCurrencyIso', false);

		$this->addForeignKey(null, '{{%addons_discounts_currencyprices}}', ['discountId'], '{{%addons_discounts}}', ['id'], 'CASCADE');
		$this->addForeignKey(null, '{{%addons_discounts_currencyprices}}', ['paymentCurrencyIso'], '{{%commerce_paymentcurrencies}}', ['iso'], 'CASCADE');

		return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTableIfExists('{{%addons_discounts_currencyprices}}');
        return true;
    }
}
