<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\Plugin as Commerce;
use craft\fieldlayoutelements\BaseNativeField;
use craft\web\View;
use webdna\commerce\currencyprices\behaviors\CurrencyPricesBehavior;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\services\Purchasables;

/**
 * The Currency prices layout element: one money input per eligible payment currency of the element's
 * store, below Commerce's own price (BR-2). It is offered on every purchasable type's layout and on an
 * Events ticket-type layout, and the install adds it to variant layouts.
 *
 * Its inputs post `currencyPrices[<ISO>]`, which the element's own save writes (BR-4). It reaches the
 * editor only through the element's edit screen, so it needs no permission of its own (BR-22).
 *
 * @author webdna
 * @since 4.0.0
 */
class CurrencyPricesField extends BaseNativeField
{
    public string $attribute = Purchasables::ATTRIBUTE;

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('commerce-currency-prices', 'Currency prices');
    }

    protected function useFieldset(): bool
    {
        return true;
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        $plugin = CurrencyPrices::getInstance();
        if ($element === null || !$plugin->getPurchasables()->isPricedElement($element)) {
            return null;
        }

        $storeId = $plugin->getPurchasables()->getStoreId($element);
        if ($storeId === null) {
            return null;
        }

        $store = Commerce::getInstance()->getStores()->getStoreById($storeId);
        $stored = $element->id ? $plugin->getPrices()->getPrices($element->id, $storeId) : [];

        /** @var CurrencyPricesBehavior|null $behavior */
        $behavior = $element->getBehavior(Purchasables::BEHAVIOR);
        $posted = $behavior?->currencyPrices !== null ? array_change_key_case($behavior->currencyPrices, CASE_UPPER) : null;

        $currencies = [];
        foreach ($plugin->getPrices()->getEligibleCurrencies($storeId) as $iso) {
            if ($posted !== null && array_key_exists($iso, $posted)) {
                // A failed save shows what was typed, so the editor can correct it.
                $value = is_array($posted[$iso]) ? ($posted[$iso]['value'] ?? null) : $posted[$iso];
            } else {
                $value = isset($stored[$iso]) ? Craft::$app->getFormatter()->asDecimal($stored[$iso]) : null;
            }

            $currencies[] = [
                'iso' => $iso,
                'value' => $value,
                'decimals' => Commerce::getInstance()->getCurrencies()->getSubunitFor($iso),
                'priced' => isset($stored[$iso]),
            ];
        }

        return Craft::$app->getView()->renderTemplate('commerce-currency-prices/_fieldlayoutelements/currency-prices', [
            'currencies' => $currencies,
            'storeCurrency' => $store?->getCurrency()?->getCode(),
            'static' => $static,
        ], View::TEMPLATE_MODE_CP);
    }
}
