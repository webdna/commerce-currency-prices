<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\services;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\enums\LineItemType;
use craft\commerce\events\LineItemEvent;
use craft\commerce\events\OrderLineItemsRefreshEvent;
use craft\commerce\events\PurchasableAvailableEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin as Commerce;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineFieldsEvent;
use craft\events\ModelEvent;
use webdna\commerce\currencyprices\behaviors\OrderCurrencyBehavior;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\events\DefineCurrencyPriceEvent;
use yii\base\Component;

/**
 * Prices a basket in its payment currency (BR-7–BR-11).
 *
 * A line's price is its currency price scaled by the ratio Commerce's own price bears to the base price,
 * so catalog pricing rules and sales reach currency prices without a forked adjuster (B3). The order's
 * currency is kept equal to its payment currency, so Commerce never converts by exchange rate. An item
 * with no price in the payment currency is not available, and Commerce refuses or removes it.
 *
 * @author webdna
 * @since 4.0.0
 */
class Pricing extends Component
{
    /**
     * The key a line's snapshot records its currency pricing under (BR-9).
     */
    public const SNAPSHOT_KEY = 'currencyPrices';

    /**
     * The name of the behavior that formats orders' and lines' amounts in the order's currency.
     */
    public const CURRENCY_BEHAVIOR = 'currencyPricesOrderCurrency';

    /**
     * @var array<string, array{ids: int[], prices: array<int, float>}> An order's prices during its line
     * refresh, read in one query (BR-14), keyed by {@see self::_orderKey()}
     */
    private array $_orderPrices = [];

    /**
     * Gives a line in another currency its currency price, after Commerce has populated it (BR-7–BR-9).
     * A store-currency line is left exactly as Commerce made it (TN-7).
     */
    public function populateLineItem(LineItemEvent $event): void
    {
        $lineItem = $event->lineItem;
        if ($lineItem->type !== LineItemType::Purchasable) {
            return;
        }

        $order = $lineItem->getOrder();
        $purchasable = $lineItem->getPurchasable();
        if ($order === null || !$purchasable instanceof Purchasable) {
            return;
        }

        $currency = $this->getOrderCurrency($order);
        if ($currency === null) {
            return;
        }

        // Without a price the item is unavailable (BR-11): Commerce refuses or removes the line.
        $currencyPrice = $this->getOrderPrice($order, (int)$purchasable->id, $currency);
        if ($currencyPrice === null) {
            return;
        }

        $basePrice = (float)$purchasable->getBasePrice();
        $ratio = $this->ratio($lineItem->getPrice(), $basePrice);
        $promotionalPrice = $lineItem->getPromotionalPrice();
        $promotionalRatio = $promotionalPrice === null ? null : $this->ratio($promotionalPrice, $basePrice);

        $prices = $this->definePrices($purchasable, $currency, $currencyPrice, $ratio, $promotionalRatio, $order, $lineItem);
        $lineItem->setPrice($prices['price']);
        $lineItem->setPromotionalPrice($prices['promotionalPrice']);

        $snapshot = $lineItem->getSnapshot();
        $snapshot[self::SNAPSHOT_KEY] = [
            'currency' => $currency,
            'currencyPrice' => $currencyPrice,
            'ratio' => $ratio,
        ];
        $lineItem->setSnapshot($snapshot);
    }

    /**
     * Works out an item's price and promotional price in a currency from its currency price and Commerce's
     * ratios (BR-7), then lets a handler change them (BR-8). A negative price is clamped to 0 and logged
     * (TN-13), and a promotional price that is not below the price is dropped, as Commerce does (TN-8).
     *
     * @param float $ratio Commerce's price ÷ base price, 1 when there is no base price
     * @param float|null $promotionalRatio Commerce's promotional price ÷ base price, null when it has none
     * @return array{price: float, promotionalPrice: float|null}
     */
    public function definePrices(
        Purchasable $purchasable,
        string $currency,
        float $currencyPrice,
        float $ratio,
        ?float $promotionalRatio,
        ?Order $order = null,
        ?LineItem $lineItem = null,
    ): array {
        $event = new DefineCurrencyPriceEvent([
            'purchasable' => $purchasable,
            'currency' => $currency,
            'order' => $order,
            'lineItem' => $lineItem,
            'price' => Currency::round($currencyPrice * $ratio, $currency),
            'promotionalPrice' => $promotionalRatio === null ? null : Currency::round($currencyPrice * $promotionalRatio, $currency),
        ]);

        $plugin = CurrencyPrices::getInstance();
        if ($plugin !== null && $plugin->hasEventHandlers(CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE)) {
            $plugin->trigger(CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE, $event);
        }

        $price = $this->clamp($event->price, 'price', $purchasable, $currency);
        $promotionalPrice = $event->promotionalPrice === null ? null : $this->clamp($event->promotionalPrice, 'promotional price', $purchasable, $currency);

        if ($promotionalPrice !== null && $promotionalPrice >= $price) {
            $promotionalPrice = null;
        }

        return ['price' => $price, 'promotionalPrice' => $promotionalPrice];
    }

    /**
     * Asked about an item for an order in another currency, answers unavailable when the item has no price
     * in that currency (BR-11). Without an order, availability is unchanged.
     */
    public function purchasableAvailable(PurchasableAvailableEvent $event): void
    {
        if (!$event->isAvailable || $event->order === null || !$event->purchasable instanceof Purchasable) {
            return;
        }

        $currency = $this->getOrderCurrency($event->order);
        if ($currency !== null && $this->getOrderPrice($event->order, (int)$event->purchasable->id, $currency) === null) {
            $event->isAvailable = false;
        }
    }

    /**
     * Before an order is saved (BR-10).
     */
    public function beforeSaveOrder(ModelEvent $event): void
    {
        if ($event->sender instanceof Order) {
            $this->syncOrderCurrency($event->sender);
        }
    }

    /**
     * Before an order's lines are refreshed in a recalculation: sets its currency (BR-10), then reads the
     * prices of all its lines in one query (BR-14).
     */
    public function beforeLineItemsRefreshed(OrderLineItemsRefreshEvent $event): void
    {
        $order = $event->sender;
        if (!$order instanceof Order) {
            return;
        }

        $this->syncOrderCurrency($order);

        $currency = $this->getOrderCurrency($order);
        if ($currency === null) {
            return;
        }

        $ids = array_values(array_filter(array_map(fn(LineItem $lineItem) => (int)$lineItem->purchasableId, $event->lineItems)));
        $this->_orderPrices[$this->_orderKey($order, $currency)] = [
            'ids' => $ids,
            'prices' => CurrencyPrices::getInstance()->getPrices()->getPricesForPurchasables($ids, (int)$order->storeId, $currency),
        ];
    }

    public function afterLineItemsRefreshed(OrderLineItemsRefreshEvent $event): void
    {
        $order = $event->sender;
        if ($order instanceof Order) {
            unset($this->_orderPrices[$this->_orderKey($order, strtoupper($order->getPaymentCurrency()))]);
        }
    }

    /**
     * Keeps an incomplete order's currency equal to its payment currency, so Commerce never converts by
     * exchange rate (BR-10). A payment currency the store lacks is first reset to the store's, as Commerce
     * does when it next loads the order (TN-4). A completed order is never changed (TN-5).
     */
    public function syncOrderCurrency(Order $order): void
    {
        if ($order->isCompleted || !$order->storeId) {
            return;
        }

        $paymentCurrencies = Commerce::getInstance()->getPaymentCurrencies();
        if ($paymentCurrencies->getPaymentCurrencyByIso($order->getPaymentCurrency(), $order->storeId) === null) {
            $order->setPaymentCurrency((string)$order->getStore()->getCurrency()?->getCode());
        }

        if ($order->currency !== $order->getPaymentCurrency()) {
            $order->currency = $order->getPaymentCurrency();
        }
    }

    /**
     * Formats an order's and its lines' `…AsCurrency` amounts in the order's currency (BR-10).
     */
    public function defineOrderBehaviors(DefineBehaviorsEvent $event): void
    {
        $model = $event->sender;
        if ($model instanceof Order || $model instanceof LineItem) {
            $event->behaviors[self::CURRENCY_BEHAVIOR] = [
                'class' => OrderCurrencyBehavior::class,
                'currencyAttributes' => $model->currencyAttributes(),
            ];
        }
    }

    /**
     * The same amounts in `toArray()`, as an Ajax cart reads them. A class-level handler, so it runs
     * after Commerce's behavior has added its own.
     */
    public function defineCurrencyFields(DefineFieldsEvent $event): void
    {
        $model = $event->sender;
        $behavior = $model instanceof Order || $model instanceof LineItem ? $model->getBehavior(self::CURRENCY_BEHAVIOR) : null;
        if ($behavior instanceof OrderCurrencyBehavior) {
            $event->fields = array_merge($event->fields, $behavior->currencyFields());
        }
    }

    /**
     * The order's payment currency when it is one its store's items can be priced in, otherwise null: the
     * store currency, or one the store does not accept, is Commerce's to price.
     */
    public function getOrderCurrency(Order $order): ?string
    {
        if (!$order->storeId) {
            return null;
        }

        $currency = strtoupper($order->getPaymentCurrency());

        return in_array($currency, CurrencyPrices::getInstance()->getPrices()->getEligibleCurrencies($order->storeId), true) ? $currency : null;
    }

    /**
     * An item's price in the order's store and the given currency, from the order's one query while its
     * lines are refreshed, or else from the item's own prices (read once per request).
     */
    private function getOrderPrice(Order $order, int $purchasableId, string $currency): ?float
    {
        $orderPrices = $this->_orderPrices[$this->_orderKey($order, $currency)] ?? null;
        if ($orderPrices !== null && in_array($purchasableId, $orderPrices['ids'], true)) {
            return $orderPrices['prices'][$purchasableId] ?? null;
        }

        return CurrencyPrices::getInstance()->getPrices()->getPrice($purchasableId, (int)$order->storeId, $currency);
    }

    private function ratio(float $price, float $basePrice): float
    {
        return $basePrice > 0 ? $price / $basePrice : 1.0;
    }

    private function clamp(float $price, string $label, Purchasable $purchasable, string $currency): float
    {
        if ($price >= 0) {
            return Currency::round($price, $currency);
        }

        Craft::warning("A currency price handler set a negative $label ($price $currency) for purchasable $purchasable->id; using 0.", __METHOD__);

        return 0.0;
    }

    private function _orderKey(Order $order, string $currency): string
    {
        return spl_object_id($order) . ':' . $order->storeId . ':' . $currency;
    }
}
