<?php

namespace webdna\commerce\currencyprices\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190126_140136_discounts migration.
 */
class m190126_140136_discounts extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable('{{%commerce_discounts_currencyprices}}', [
			'id' => $this->primaryKey(),
			'discountId' => $this->integer(),
			'paymentCurrencyIso' => $this->string()->notNull(),
			'purchaseTotal' => $this->decimal(14, 4),
			'baseDiscount' => $this->decimal(14, 4),
			'perItemDiscount' => $this->decimal(14, 4),
			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid' => $this->uid(),
		]);

		$this->createIndex(null, '{{%commerce_discounts_currencyprices}}', 'discountId', false);
		$this->createIndex(null, '{{%commerce_discounts_currencyprices}}', 'paymentCurrencyIso', false);

		$this->addForeignKey(null, '{{%commerce_discounts_currencyprices}}', ['discountId'], '{{%commerce_discounts}}', ['id'], 'CASCADE');
		$this->addForeignKey(null, '{{%commerce_discounts_currencyprices}}', ['paymentCurrencyIso'], '{{%commerce_paymentcurrencies}}', ['iso'], 'CASCADE');

		return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTableIfExists('{{%commerce_discounts_currencyprices}}');
        return true;
    }
}
