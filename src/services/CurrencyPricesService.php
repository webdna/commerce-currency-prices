<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\currencyprices\services;

use kuriousagency\commerce\currencyprices\CurrencyPrices;
use kuriousagency\commerce\currencyprices\models\CurrencyPricesModel;
use kuriousagency\commerce\currencyprices\records\CurrencyPricesRecord;

use craft\commerce\Plugin as Commerce;

use Craft;
use craft\base\Component;
use craft\helpers\MigrationHelper;
use craft\helpers\Db;
use craft\db\Query;

/**
 * @author    Kurious Agency
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class CurrencyPricesService extends Component
{
	private $_migration;

    // Public Methods
	// =========================================================================

	public function getPricesByPurchasableId($id)
	{
		$result = (new Query())
			->select(['*'])
			->from(['{{%commerce_currencyprices}}'])
			->where(['purchasableId' => $id])
			->one();

		if (!$result) {
			return null;
		}

		return $result;
	}

	public function savePrices($purchasable, $prices)
	{
		$record = CurrencyPricesRecord::findOne($purchasable->id);
		
		if (!$record) {
			$record = new CurrencyPricesRecord();
		}

		$record->purchasableId = $purchasable->id;
		$record->siteId = $purchasable->siteId;
		
		$primaryIso = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
		$record->{$primaryIso} = $purchasable->price;

		foreach ($prices as $iso => $value)
		{
			$record->{$iso} = $value;
		}

		$record->save();
	}

	public function deletePrices($purchasableId)
	{
		$record = CurrencyPricesRecord::findOne($purchasableId);

		if ($record) {
			$record->delete();
		}
	}

	
	public function renameCurrency($old, $new)
	{
		Craft::$app->getDb()->createCommand()
				->renameColumn('{{%commerce_currencyprices}}', $old, $new)
				->execute();
	}
	
	public function addCurrency($column)
	{
		Craft::$app->getDb()->createCommand()
			->addColumn('{{%commerce_currencyprices}}', $column, 'decimal(14,4) NOT NULL')
			->execute();
	}

	public function removeCurrency($column)
	{
		Craft::$app->getDb()->createCommand()
			->dropColumn('{{%commerce_currencyprices}}', $column)
			->execute();
	}
}
