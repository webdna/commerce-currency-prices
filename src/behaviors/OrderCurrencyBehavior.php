<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\behaviors;

use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;

/**
 * Formats an order's and its lines' `…AsCurrency` amounts in the order's currency. Commerce formats them
 * in the store currency whatever the order's currency is, so a dollar basket (BR-10) would show pounds.
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
        $order = match (true) {
            $this->owner instanceof Order => $this->owner,
            $this->owner instanceof LineItem => $this->owner->getOrder(),
            default => null,
        };

        return $order?->currency ?: parent::getDefaultCurrency();
    }
}
