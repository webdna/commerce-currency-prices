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
 * Currencies field: a dropdown of the payment currencies of the current site's store, storing the ISO code
 * (BR-16). Its class name and stored values are 3.x's, so a 3.x value carries over (TN-15).
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

    public static function icon(): string
    {
        return 'money-bill';
    }

    public static function phpType(): string
    {
        return 'string|null';
    }

    public static function dbType(): string
    {
        return Schema::TYPE_STRING;
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The currencies offered: the payment currencies of the store of the element's site, or of the current
     * site. A saved value the store no longer accepts stays offered, so a save does not drop it.
     *
     * @return array<string, string> Name by ISO code
     */
    public function getCurrencyOptions(mixed $value = null, ?ElementInterface $element = null): array
    {
        $commerce = Commerce::getInstance();
        $store = $element?->siteId ? $commerce->getStores()->getStoreBySiteId($element->siteId) : null;
        $store ??= $commerce->getStores()->getCurrentStore();

        $options = [];
        foreach ($commerce->getPaymentCurrencies()->getAllPaymentCurrencies($store->id) as $currency) {
            $options[$currency->iso] = $currency->getName() ?? $currency->iso;
        }

        if (is_string($value) && $value !== '' && !isset($options[$value])) {
            $options[$value] = $value;
        }

        return $options;
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        $id = $this->getInputId();

        return Craft::$app->getView()->renderTemplate('commerce-currency-prices/fields/CurrencyField_input', [
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
            'id' => $id,
            'options' => $this->getCurrencyOptions($value, $element),
            'namespacedId' => Craft::$app->getView()->namespaceInputId($id),
        ]);
    }
}
