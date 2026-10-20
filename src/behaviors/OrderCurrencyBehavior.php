<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\behaviors;

use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\ShippingMethodOption;
use ReflectionMethod;
use yii\base\InvalidConfigException;

/**
 * Formats an order's, its lines' and its shipping options' `…AsCurrency` amounts in the order's currency.
 * Commerce formats them in the store currency whatever the order's currency is, so a dollar basket (BR-10)
 * would show pounds.
 *
 * It is attached ahead of Commerce's own behavior, so it answers first. It adds no `toArray()` fields
 * itself: `Pricing::defineCurrencyFields()` does that, after Commerce's.
 *
 * @author webdna
 * @since 4.0.0
 */
class OrderCurrencyBehavior extends CurrencyAttributeBehavior
{
    public function events(): array
    {
        return [];
    }

    public function getDefaultCurrency(): string
    {
        if ($this->owner instanceof ShippingMethodOption) {
            return $this->shippingOptionCurrency($this->owner) ?: parent::getDefaultCurrency();
        }

        $order = match (true) {
            $this->owner instanceof Order => $this->owner,
            $this->owner instanceof LineItem => $this->owner->getOrder(),
            default => null,
        };

        return $order?->currency ?: parent::getDefaultCurrency();
    }

    /**
     * A shipping option keeps its order private, and only its own protected `getCurrency()` reads the
     * order's currency. Null when it has no order yet.
     */
    private function shippingOptionCurrency(ShippingMethodOption $option): ?string
    {
        try {
            return (string)(new ReflectionMethod($option, 'getCurrency'))->invoke($option);
        } catch (InvalidConfigException) {
            return null;
        }
    }
}
