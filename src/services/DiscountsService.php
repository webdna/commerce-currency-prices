<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://webdna.co.uk/
 * @copyright Copyright (c) 2018 webdna
 */

namespace webdna\commerce\currencyprices\services;

use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\records\DiscountsPricesRecord;

use craft\commerce\Plugin as Commerce;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Localization;

/**
 * @author    webdna
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class DiscountsService extends Component
{
	private $_fields = ['purchaseTotal','baseDiscount','perItemDiscount'];

    // Public Methods
	// =========================================================================

	public function getPricesByDiscountId($id)
	{
		$results = (new Query())
			->select(['*'])
			->from(['{{%commerce_discounts_currencyprices}}'])
			->where(['discountId' => $id])
			->all();

		if (!$results) {
			return [];
		}

		return $results;
	}

	public function getPricesByDiscountIdAndCurrency($id, $iso)
	{
		$result = (new Query())
			->select(['*'])
			->from(['{{%commerce_discounts_currencyprices}}'])
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

			if($values) {
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
		}

		return $getCurrentPrices ? $currencyPrices : $fields;
	}

	public function saveDiscount($id, $prices)
	{
		foreach ($prices as $key => $value)
		{
			$record = DiscountsPricesRecord::findOne(['discountId'=>$id, 'paymentCurrencyIso'=>$key]);

			if (!$record) {
				$record = new DiscountsPricesRecord();
			}

			$record->discountId = $id;
			$record->paymentCurrencyIso = $key;
			foreach ($this->_fields as $field) {
				$record->$field = Localization::normalizeNumber($value[$field]) * -1;
			}
			$record->save();
		}
	}
}
