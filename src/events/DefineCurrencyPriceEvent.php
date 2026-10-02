<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\events;

use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use yii\base\Event;

/**
 * Raised as `CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE` once an item's price in a payment currency has
 * been worked out from its currency price (BR-7). A handler may change `price` and `promotionalPrice`
 * (BR-8). A negative price is clamped to 0 and logged, and a promotional price that is not below the
 * price is dropped, as Commerce does.
 *
 * ```php
 * Event::on(CurrencyPrices::class, CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE, function(DefineCurrencyPriceEvent $event) {
 *     $event->price = ceil($event->price);
 * });
 * ```
 *
 * @author webdna
 * @since 4.0.0
 */
class DefineCurrencyPriceEvent extends Event
{
    public Purchasable $purchasable;

    /**
     * @var string The payment currency's ISO code
     */
    public string $currency;

    /**
     * @var Order|null The order, when pricing a line in a basket
     */
    public ?Order $order = null;

    /**
     * @var LineItem|null The line, when pricing a line in a basket
     */
    public ?LineItem $lineItem = null;

    /**
     * @var float The price in the currency
     */
    public float $price;

    /**
     * @var float|null The promotional price in the currency, or null when there is none
     */
    public ?float $promotionalPrice = null;
}
