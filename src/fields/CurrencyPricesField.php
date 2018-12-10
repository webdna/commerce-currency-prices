<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * add multiple currency prices for products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\currencyprices\fields;

use kuriousagency\currencyprices\CurrencyPrices;
use kuriousagency\currencyprices\assetbundles\currencyprices\CurrencyPricesAsset;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Db;
use yii\db\Schema;
use craft\helpers\Json;
use craft\commerce\Plugin as Commerce;
use craft\helpers\Localization as LocalizationHelper;

/**
 * @author    Kurious Agency
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class CurrencyPricesField extends Field
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $prices;

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('currency-prices', 'Currency Prices');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules = array_merge($rules, [
            ['prices', 'string'],
        ]);
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getContentColumnType(): string
    {
        return Schema::TYPE_STRING;
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        return is_array($value) ? $value : json_decode($value);
    }

    /**
     * @inheritdoc
     */
    public function serializeValue($value, ElementInterface $element = null)
    {
		foreach ($value as $key => $v)
		{
			$value[$key] = LocalizationHelper::normalizeNumber($v);
		}
		return parent::serializeValue($value, $element);
    }

    /**
     * @inheritdoc
     */
    /*public function getSettingsHtml()
    {
        // Render the settings template
        return Craft::$app->getView()->renderTemplate(
            'currency-prices/_components/fields/CurrencyPricesField_settings',
            [
                'field' => $this,
            ]
        );
    }*/

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        // Register our asset bundle
        Craft::$app->getView()->registerAssetBundle(CurrencyPricesAsset::class);

        // Get our id and namespace
        $id = Craft::$app->getView()->formatInputId($this->handle);
		$namespacedId = Craft::$app->getView()->namespaceInputId($id);
		
		$currencies = Commerce::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies();
//Craft::dd($value);
		//$value = json_decode($value);
		$values = [];
		foreach ($currencies as $currency)
		{
			if (!$currency->primary) {
				$values[$currency->iso] = [
					'iso' => $currency->iso,
					'id' => $currency->id,
					'rate' => $currency->rate,
					'value' => isset($value->{$currency->iso}) ? $value->{$currency->iso} : '',
					'primary' => $currency->primary,
				];
			}
		}
		//Craft::dd($values);

        // Variables to pass down to our field JavaScript to let it namespace properly
        $jsonVars = [
            'id' => $id,
            'name' => $this->handle,
            'namespace' => $namespacedId,
            'prefix' => Craft::$app->getView()->namespaceInputId(''),
            ];
        $jsonVars = Json::encode($jsonVars);
        Craft::$app->getView()->registerJs("$('#{$namespacedId}-field').CurrencyPrices(" . $jsonVars . ");");

        // Render the input template
        return Craft::$app->getView()->renderTemplate(
            'currency-prices/_components/fields/CurrencyPricesField_input',
            [
                'name' => $this->handle,
                'values' => $values,
                'field' => $this,
                'id' => $id,
                'namespacedId' => $namespacedId,
            ]
        );
    }
}
