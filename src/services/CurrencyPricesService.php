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
use kuriousagency\commerce\currencyprices\records\ShippingRulesPricesRecord;
use kuriousagency\commerce\currencyprices\records\ShippingCategoriesPricesRecord;

use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Sale as SaleRecord;

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

	public function getPricesByShippingRuleId($id)
	{
		$results = (new Query())
			->select(['*'])
			->from(['{{%commerce_shippingrules_currencyprices}}'])
			->where(['shippingRuleId' => $id])
			->all();

		if (!$results) {
			return [];
		}

		return $results;
	}

	public function getPricesByShippingRuleCategoryId($id, $catId)
	{
		$results = (new Query())
			->select(['*'])
			->from(['{{%commerce_shippingrule_categories_currencyprices}}'])
			->where(['shippingRuleId' => $id, 'shippingCategoryId'=> $catId])
			->all();

		if (!$results) {
			return [];
		}

		return $results;
	}

	public function getPricesByShippingRuleIdAndCurrency($id, $iso)
	{
		$result = (new Query())
			->select(['*'])
			->from(['{{%commerce_shippingrules_currencyprices}}'])
			->where(['shippingRuleId' => $id, 'paymentCurrencyIso' => $iso])
			->one();

		if (!$result) {
			return null;
		}

		return $result;
	}

	public function getPricesByShippingRuleCategoryIdAndCurreny($id, $catId, $iso)
	{
		$result = (new Query())
			->select(['*'])
			->from(['{{%commerce_shippingrule_categories_currencyprices}}'])
			->where(['shippingRuleId' => $id, 'shippingCategoryId'=> $catId, 'paymentCurrencyIso' => $iso])
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

	public function saveShipping($id, $prices, $categories)
	{
		foreach ($prices as $key => $value)
		{
			$record = ShippingRulesPricesRecord::findOne(['shippingRuleId'=>$id, 'paymentCurrencyIso'=>$key]);
			
			if (!$record) {
				$record = new ShippingRulesPricesRecord();
			}

			$record->shippingRuleId = $id;
			$record->paymentCurrencyIso = $key;
			$record->minTotal = $value['minTotal'];
			$record->maxTotal = $value['maxTotal'];
			$record->baseRate = $value['baseRate'];
			$record->perItemRate = $value['perItemRate'];
			$record->weightRate = $value['weightRate'];
			$record->percentageRate = $value['percentageRate'];
			$record->minRate = $value['minRate'];
			$record->maxRate = $value['maxRate'];
			$record->save();
		}

		$categoryPrices = [];
		foreach ($categories as $cid => $props)
		{
			unset($props['condition']);
			foreach ($props as $prop => $values)
			{
				foreach ($values as $k => $value)
				{
					$categoryPrices[$cid][$k][$prop] = $value;
				}
			}
		}
		//Craft::dd($categoryPrices);
		foreach ($categoryPrices as $key => $values)
		{
			foreach ($values as $iso => $value)
			{
				$record = ShippingCategoriesPricesRecord::findOne(['shippingRuleId'=>$id, 'shippingCategoryId'=>$key, 'paymentCurrencyIso'=>$iso]);
				
				if (!$record) {
					$record = new ShippingCategoriesPricesRecord();
				}

				$record->shippingRuleId = $id;
				$record->shippingCategoryId = $key;
				$record->paymentCurrencyIso = $iso;
				$record->perItemRate = $value['perItemRate'];
				$record->weightRate = $value['weightRate'];
				$record->percentageRate = $value['percentageRate'];
				$record->save();
			}
		}
	}

	public function getSalePrice($purchasable, $currency)
	{
		$sales = Commerce::getInstance()->getSales()->getSalesForPurchasable($purchasable);
		$prices = CurrencyPrices::$plugin->service->getPricesByPurchasableId($purchasable->id);
		$originalPrice = '';

		if ($prices) {
			$originalPrice = $prices[$currency];
		}
        
		$takeOffAmount = 0;
        $newPrice = null;

        /** @var Sale $sale */
        foreach ($sales as $sale) {

            switch ($sale->apply) {
                case SaleRecord::APPLY_BY_PERCENT:
                    // applyAmount is stored as a negative already
                    $takeOffAmount += ($sale->applyAmount * $originalPrice);
                    if ($sale->ignorePrevious) {
                        $newPrice = $originalPrice + ($sale->applyAmount * $originalPrice);
                    }
                    break;
                case SaleRecord::APPLY_TO_PERCENT:
                    // applyAmount needs to be reversed since it is stored as negative
                    $newPrice = (-$sale->applyAmount * $originalPrice);
                    break;
                case SaleRecord::APPLY_BY_FLAT:
                    // applyAmount is stored as a negative already
                    $takeOffAmount += $sale->applyAmount;
                    if ($sale->ignorePrevious) {
                        // applyAmount is always negative so add the negative amount to the original price for the new price.
                        $newPrice = $originalPrice + $sale->applyAmount;
                    }
                    break;
                case SaleRecord::APPLY_TO_FLAT:
                    // applyAmount needs to be reversed since it is stored as negative
                    $newPrice = -$sale->applyAmount;
                    break;
            }

            // If the stop processing flag is true, it must been the last
            // since the sales for this purchasable would have returned it last.
            if ($sale->stopProcessing) {
                break;
            }
        }

        $salePrice = ($originalPrice + $takeOffAmount);

        // A newPrice has been set so use it.
        if (null !== $newPrice) {
            $salePrice = $newPrice;
        }

        if ($salePrice < 0) {
            $salePrice = 0;
		}

        return $salePrice;
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
