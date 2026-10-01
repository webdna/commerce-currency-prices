<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\commerce\Plugin as Commerce;
use yii\db\Schema;

/**
 * Currencies field: a dropdown of the payment currencies, storing the ISO code.
 *
 * Ported to Craft 5's field signatures only; task 1.5 owns its behaviour.
 *
 * @author webdna
 * @since 1.0.0
 */
class CurrencyField extends Field
{
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Currencies');
    }

    public static function dbType(): string
    {
        return Schema::TYPE_STRING;
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        $options = [];
        foreach (Commerce::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies() as $currency) {
            $options[$currency->iso] = $currency->getName() ?? $currency->iso;
        }

        $id = $this->getInputId();

        return Craft::$app->getView()->renderTemplate('commerce-currency-prices/fields/CurrencyField_input', [
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
            'id' => $id,
            'options' => $options,
            'namespacedId' => Craft::$app->getView()->namespaceInputId($id),
        ]);
    }
}
