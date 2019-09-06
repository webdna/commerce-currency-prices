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
use kuriousagency\commerce\currencyprices\records\ShippingRulesPricesRecord;
use kuriousagency\commerce\currencyprices\records\ShippingCategoriesPricesRecord;

use craft\commerce\Plugin as Commerce;

use Craft;
use craft\base\Component;
use craft\db\Query;

/**
 * @author    Kurious Agency
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class ShippingService extends Component
{
	private $_fields = [
		'minTotal',
		'maxTotal',
		'baseRate',
		'perItemRate',
		'weightRate',
		'percentageRate',
		'minRate',
		'maxRate',
	];

    // Public Methods
	// =========================================================================

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

	public function getPricesByShippingRuleCategoryIdAndCurrency($id, $catId, $iso)
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

		$allRulesCategories = Craft::$app->getRequest()->getBodyParam('ruleCategoriesCP');
		
		foreach ($allRulesCategories as $key => $ruleCategory) {
			foreach ($ruleCategory as $k => $v)
			{
				$ruleCategory[$k] = is_array($v) ? $v[$iso] : $v;
			}
			$ruleCategory['condition'] = Craft::$app->getRequest()->getBodyParam('ruleCategories')[$key]['condition'];
            $fields['ruleCategories'][$key] = $ruleCategory;
		}
		//Craft::dd($fields);

		return $getCurrentPrices ? $currencyPrices : $fields;
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
			foreach ($this->_fields as $field) {
				$record->$field = $value[$field];
			}
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
}
