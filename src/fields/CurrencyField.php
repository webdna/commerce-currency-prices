<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\currencyprices\fields;

use kuriousagency\commerce\currencyprices\CurrencyPrices;

use Craft;
use craft\commerce\Plugin;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Db;
use yii\db\Schema;
use craft\helpers\Json;

class CurrencyField extends Field
{
	public static function displayName(): string
	{
		return Craft::t('commerce', 'Currencies');
	}

	public function rules()
	{
		$rules = parent::rules();
		return $rules;
	}

	public function getContentColumnType(): string
	{
		return Schema::TYPE_STRING;
	}

	public function normalizeValue($value, ElementInterface $element = null)
	{
		return parent::normalizeValue($value, $element);
	}

	public function serializeValue($value, ElementInterface $element = null)
	{
		return parent::serializeValue($value, $element);
	}

	public function getInputHtml($value, ElementInterface $element = null): string
	{
		$id = Craft::$app->getView()->formatInputId($this->handle);
		$namespacedId = Craft::$app->getView()->namespaceInputId($id);

		$currencies = Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies();
		$options = [];
		foreach ($currencies as $currency)
		{
			$options[$currency->iso] = $currency->currency;
		}

		return Craft::$app->getView()->renderTemplate(
            'commerce-currency-prices/fields/CurrencyField_input',
            [
                'name' => $this->handle,
                'value' => $value,
                'field' => $this,
                'id' => $id,
                'options' => $options,
                'namespacedId' => $namespacedId,
            ]
        );
	}
}