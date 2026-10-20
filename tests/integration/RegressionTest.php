<?php

namespace webdna\commerce\currencyprices\tests\integration;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\conditions\orders\DiscountOrderCondition;
use craft\commerce\elements\conditions\orders\ShippingRuleOrderCondition;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\events\LineItemEvent;
use craft\commerce\models\Discount;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin as Commerce;
use craft\commerce\services\Discounts;
use craft\commerce\services\LineItems;
use craft\commerce\services\ShippingMethods;
use craft\commerce\services\ShippingRules;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\tests\Support\CommerceFixtures;
use yii\base\Event;

/**
 * The spec's regression checks (task 5.1, §7): what worked before the plugin still works with it. Discounts
 * and shipping rules without the payment currency condition apply as entered (BR-13), Commerce's own
 * payment-currency saves are untouched (3.x hooked them), and a site's own line-pricing handler keeps the
 * order Craft registers it in (heathfield's case). The store-currency basket is PricingTest's TN-7 test.
 */
class RegressionTest extends Unit
{
    use CommerceFixtures;

    /**
     * @var callable|null
     */
    private $siteHandler = null;

    protected function _before(): void
    {
        $this->setUpGbpStore();

        // Commerce keeps each store's discounts and shipping for the request.
        Commerce::getInstance()->set('discounts', ['class' => Discounts::class]);
        Commerce::getInstance()->set('shippingMethods', ['class' => ShippingMethods::class]);
        Commerce::getInstance()->set('shippingRules', ['class' => ShippingRules::class]);
    }

    protected function _after(): void
    {
        if ($this->siteHandler !== null) {
            Event::off(LineItems::class, LineItems::EVENT_POPULATE_LINE_ITEM, $this->siteHandler);
            $this->siteHandler = null;
        }
    }

    public function testAFixedDiscountWithNoConditionAppliesAsEnteredInEveryCurrency(): void
    {
        $discount = new Discount([
            'storeId' => $this->store->id,
            'name' => '5 off',
            'enabled' => true,
            'allPurchasables' => true,
            'allCategories' => true,
            'baseDiscount' => -5,
        ]);
        $discount->setOrderCondition(['class' => DiscountOrderCondition::class, 'storeId' => $this->store->id, 'conditionRules' => []]);
        $this->assertTrue(Commerce::getInstance()->getDiscounts()->saveDiscount($discount), implode(', ', $discount->getFirstErrors()));
        $variant = $this->createPricedVariant(60, ['USD' => 70]);

        $this->assertSame(-5.0, $this->basket('GBP', $variant)->getTotalDiscount());
        $this->assertSame(-5.0, $this->basket('USD', $variant)->getTotalDiscount());
    }

    public function testAShippingRuleWithNoConditionChargesAsEnteredInEveryCurrency(): void
    {
        $method = $this->createFlatShipping(7);
        $variant = $this->createPricedVariant(60, ['USD' => 70]);

        $this->assertSame(7.0, $this->basket('GBP', $variant, $method->getHandle())->getTotalShippingCost());
        $this->assertSame(7.0, $this->basket('USD', $variant, $method->getHandle())->getTotalShippingCost());
    }

    /**
     * A shipping option's price is formatted in the basket's currency, as its order and lines are (BR-10).
     * Commerce formatted it in the store currency: "US$7.00" in a pound basket (A#10).
     */
    public function testAShippingOptionsPriceIsFormattedInTheBasketsCurrency(): void
    {
        $method = $this->createFlatShipping(7);
        $variant = $this->createPricedVariant(60, ['USD' => 70]);

        $usd = $this->basket('USD', $variant)->getAvailableShippingMethodOptions()[$method->getHandle()];
        $gbp = $this->basket('GBP', $variant)->getAvailableShippingMethodOptions()[$method->getHandle()];

        $this->assertSame('$7.00', $usd->priceAsCurrency);
        $this->assertSame('£7.00', $gbp->priceAsCurrency);
    }

    /**
     * TN-3 too: removing a payment currency keeps its prices, and they apply again when it is re-added.
     */
    public function testCommercesPaymentCurrencySavesStillWorkAndARemovedCurrencysPricesReturn(): void
    {
        $paymentCurrencies = Commerce::getInstance()->getPaymentCurrencies();
        $prices = CurrencyPrices::getInstance()->getPrices();

        // resetPaymentCurrencies() swaps the service, so it is fetched again after each reset.
        $jpy = new PaymentCurrency(['storeId' => $this->store->id, 'iso' => 'JPY', 'rate' => 190]);
        $this->assertTrue($paymentCurrencies->savePaymentCurrency($jpy), implode(', ', $jpy->getFirstErrors()));
        $jpy->rate = 200;
        $this->assertTrue($paymentCurrencies->savePaymentCurrency($jpy), implode(', ', $jpy->getFirstErrors()));
        $this->resetPaymentCurrencies();
        $paymentCurrencies = Commerce::getInstance()->getPaymentCurrencies();
        $this->assertSame(200.0, (float)$paymentCurrencies->getPaymentCurrencyByIso('JPY', $this->store->id)?->rate);
        $variant = $this->createPricedVariant(60, ['JPY' => 12000]);

        $this->assertTrue($paymentCurrencies->deletePaymentCurrencyById($jpy->id));
        $this->resetPaymentCurrencies();
        $this->assertNull(Commerce::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso('JPY', $this->store->id));
        $this->assertNotContains('JPY', $prices->getEligibleCurrencies($this->store->id));

        $this->addPaymentCurrencies($this->store->id, ['JPY']);
        $this->assertSame(12000.0, $this->basket('JPY', $variant)->getLineItems()[0]->getPrice());
    }

    /**
     * A site module that attaches its handlers in `Craft::$app->onInit()`, as heathfield does, registers
     * after the plugin, so its price is the one that stands.
     */
    public function testASiteHandlerRegisteredAfterThePluginHasTheLastWord(): void
    {
        $this->siteHandler = fn(LineItemEvent $event) => $event->lineItem->setPrice(99);
        Event::on(LineItems::class, LineItems::EVENT_POPULATE_LINE_ITEM, $this->siteHandler);
        $variant = $this->createPricedVariant(100, ['USD' => 120]);

        $this->assertSame(99.0, $this->basket('USD', $variant)->getLineItems()[0]->getPrice());
    }

    /**
     * One prepended ahead of the plugin's is treated like a promotion: the price it sets moves the currency
     * price by ratio (BR-7), here 99 / 100 × 120.
     */
    public function testASiteHandlerRegisteredBeforeThePluginReachesTheCurrencyPriceByRatio(): void
    {
        $this->siteHandler = fn(LineItemEvent $event) => $event->lineItem->setPrice(99);
        Event::on(LineItems::class, LineItems::EVENT_POPULATE_LINE_ITEM, $this->siteHandler, null, false);
        $variant = $this->createPricedVariant(100, ['USD' => 120]);

        $this->assertSame(118.8, $this->basket('USD', $variant)->getLineItems()[0]->getPrice());
        $this->assertSame(99.0, $this->basket('GBP', $variant)->getLineItems()[0]->getPrice());
    }

    private function createFlatShipping(float $rate): ShippingMethod
    {
        $method = new ShippingMethod(['storeId' => $this->store->id, 'name' => 'Standard', 'handle' => 'standard' . uniqid(), 'enabled' => true]);
        $this->assertTrue(Commerce::getInstance()->getShippingMethods()->saveShippingMethod($method), implode(', ', $method->getFirstErrors()));
        $rule = new ShippingRule([
            'storeId' => $this->store->id,
            'methodId' => $method->id,
            'name' => "$rate flat",
            'enabled' => true,
            'baseRate' => $rate,
        ]);
        $rule->setOrderCondition(['class' => ShippingRuleOrderCondition::class, 'storeId' => $this->store->id, 'conditionRules' => []]);
        $this->assertTrue(Commerce::getInstance()->getShippingRules()->saveShippingRule($rule), implode(', ', $rule->getFirstErrors()));

        return $method;
    }

    private function basket(string $paymentCurrency, Variant $variant, ?string $shippingMethodHandle = null): Order
    {
        $order = $this->createCart($paymentCurrency);
        if ($shippingMethodHandle !== null) {
            $order->shippingMethodHandle = $shippingMethodHandle;
        }
        $this->addToCart($order, $variant);

        return $order;
    }
}
