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
use kuriousagency\commerce\currencyprices\records\AddonsPricesRecord;

use craft\commerce\Plugin as Commerce;

use Craft;
use craft\base\Component;
use craft\db\Query;

/**
 * @author    Kurious Agency
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class AddonsService extends Component
{
	private $_fields = ['perItemDiscount'];

    // Public Methods
	// =========================================================================

	public function getPricesByAddonId($id)
	{
		$results = (new Query())
			->select(['*'])
			->from(['{{%addons_discounts_currencyprices}}'])
			->where(['discountId' => $id])
			->all();

		if (!$results) {
			return [];
		}

		return $results;
	}

	public function getPricesByAddonIdAndCurrency($id, $iso)
	{
		$result = (new Query())
			->select(['*'])
			->from(['{{%addons_discounts_currencyprices}}'])
			->where(['discountId' => $id, 'paymentCurrencyIso' => $iso])
			->one();

		if (!$result) {
			return null;
		}

		return $result;
	}

	public function getPrices($getCurrentPrices = true)
	{
		$request = Craft::$app->getRequest();

		$iso = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

		$currencyPrices = [];
		$fields = [];

		foreach ($this->_fields as $field)
		{
			$values = $request->getBodyParam($field.'CP');

			// replace empty values with 0
			if(is_array($values)) {
				$values = array_map(function($value) {
					return $value === "" ? 0 : $value;
				}, $values);
			}

			$fields[$field] = (float)$values[$iso];
			
			foreach ($values as $key => $price)
			{
				if (!array_key_exists($key, $currencyPrices)) {
					$currencyPrices[$key] = [];
				}
				$currencyPrices[$key][$field] = isset($price) ? $price : 0;
			}
		}

		return $getCurrentPrices ? $currencyPrices : $fields;
	}

	public function saveAddon($id, $prices)
	{
		foreach ($prices as $key => $value)
		{
			$record = AddonsPricesRecord::findOne(['discountId'=>$id, 'paymentCurrencyIso'=>$key]);
			
			if (!$record) {
				$record = new AddonsPricesRecord();
			}

			$record->discountId = $id;
			$record->paymentCurrencyIso = $key;
			foreach ($this->_fields as $field) {
				$record->$field = $value[$field] * -1;
			}
			$record->save();
		}
	}
}
