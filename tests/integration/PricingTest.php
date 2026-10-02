<?php

namespace webdna\commerce\currencyprices\tests\integration;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\conditions\customers\CatalogPricingRuleCustomerCondition;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\gateways\Dummy;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\models\LineItem;
use craft\commerce\models\Sale;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\CatalogPricingRule as CatalogPricingRuleRecord;
use craft\commerce\services\Purchasables as CommercePurchasables;
use craft\elements\conditions\users\GroupConditionRule;
use craft\elements\User;
use craft\models\UserGroup;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\events\DefineCurrencyPriceEvent;
use webdna\commerce\currencyprices\services\Prices;
use webdna\commerce\currencyprices\services\Pricing;
use webdna\commerce\currencyprices\tests\Support\CommerceFixtures;
use webdna\commerce\currencyprices\tests\Support\CountingCommand;
use yii\base\Event;
use yii\log\Logger;

/**
 * Line pricing, order currency, availability and the event (task 1.3): a basket in another currency pays
 * the typed price in that currency (TS-2, BR-7, BR-9, BR-10), refuses or removes what has no price in it
 * (TS-3, BR-11), passes promotions on by ratio (TS-4), and lets a handler change the price (BR-8).
 */
class PricingTest extends Unit
{
    use CommerceFixtures;

    private Prices $prices;

    protected function _before(): void
    {
        $this->prices = CurrencyPrices::getInstance()->getPrices();
        $this->setUpGbpStore();
    }

    protected function _after(): void
    {
        Event::off(CurrencyPrices::class, CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE);
    }

    public function testABasketInAnotherCurrencyPaysTheTypedPriceInThatCurrency(): void
    {
        // A rate that isn't 1, so any exchange-rate conversion would show.
        $usd = Commerce::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso('USD', $this->store->id);
        $usd->rate = 1.25;
        $this->assertTrue(Commerce::getInstance()->getPaymentCurrencies()->savePaymentCurrency($usd));
        $this->resetPaymentCurrencies();

        $variant = $this->createPricedVariant(100, ['USD' => 120]);

        // TS-2 #1: what commerce/cart/update-cart does with a posted paymentCurrency.
        $order = $this->createCart('USD');
        $this->assertSame('USD', $order->getPaymentCurrency());
        $this->assertSame('USD', $order->currency);

        // #2
        $this->addToCart($order, $variant);
        $lineItem = $order->getLineItems()[0];
        $this->assertSame(120.0, $lineItem->getPrice());
        $this->assertSame(120.0, $order->getTotal());
        $this->assertSame('$120.00', $order->totalAsCurrency);
        $this->assertSame('$120.00', $order->toArray(['totalAsCurrency'])['totalAsCurrency']);
        $this->assertSame('$120.00', $lineItem->salePriceAsCurrency);
        $this->assertSame('$120.00', $lineItem->toArray(['salePriceAsCurrency'])['salePriceAsCurrency']);
        $this->assertSame(['currency' => 'USD', 'currencyPrice' => 120.0, 'ratio' => 1.0], $lineItem->getSnapshot()[Pricing::SNAPSHOT_KEY]);

        // #3
        $this->assertSame(120.0, $order->getPaymentAmount(), 'No exchange-rate conversion');
        $transaction = $this->payWithDummyGateway($order);
        $this->assertSame('success', $transaction->status, (string)$transaction->message);
        $this->assertSame('USD', $transaction->paymentCurrency);
        $this->assertEquals(120, $transaction->paymentAmount);
        $this->assertEquals(120, $transaction->amount);

        // #4
        $this->prices->savePrices($variant->id, $this->store->id, ['USD' => 130]);
        $order = Order::find()->id($order->id)->one();
        $this->assertTrue($order->isCompleted);
        $order->recalculate();
        $lineItem = $order->getLineItems()[0];
        $this->assertSame(120.0, $lineItem->getPrice());
        $this->assertSame('USD', $order->currency);
        $this->assertEquals(120, $lineItem->getSnapshot()[Pricing::SNAPSHOT_KEY]['currencyPrice']);
    }

    public function testAStoreCurrencyBasketIsLeftAsCommerceMadeIt(): void
    {
        $variant = $this->createPricedVariant(100, ['USD' => 120]);
        $order = $this->createCart('GBP');
        $this->addToCart($order, $variant);

        $lineItem = $order->getLineItems()[0];
        $this->assertSame('GBP', $order->currency);
        $this->assertSame(100.0, $lineItem->getPrice());
        $this->assertArrayNotHasKey(Pricing::SNAPSHOT_KEY, $lineItem->getSnapshot());
    }

    public function testAnItemWithNoPriceInTheBasketsCurrencyCannotBeAdded(): void
    {
        $variant = $this->createPricedVariant(100, ['EUR' => 110]);
        $order = $this->createCart('USD');

        $lineItem = Commerce::getInstance()->getLineItems()->create($order, ['purchasableId' => $variant->id]);
        $order->addLineItem($lineItem);
        $this->assertFalse($lineItem->validate());
        $this->assertSame('The item is not enabled for sale.', $lineItem->getFirstError('purchasableId'));

        // Without an order, availability is unchanged.
        $this->assertTrue(Commerce::getInstance()->getPurchasables()->isPurchasableAvailable($variant));
    }

    public function testSwitchingToACurrencyAnItemHasNoPriceInRemovesItAndSwitchingBackAddsItAgain(): void
    {
        $variant = $this->createPricedVariant(100, ['EUR' => 110]);
        $order = $this->createCart('GBP');
        $this->addToCart($order, $variant);
        $this->assertCount(1, $order->getLineItems());

        $order->setPaymentCurrency('USD');
        $this->assertTrue(Craft::$app->getElements()->saveElement($order));
        $this->assertCount(0, $order->getLineItems());
        $messages = array_map(fn($notice) => $notice->message, $order->getNotices());
        $this->assertContains($variant->getDescription() . ' is no longer available.', $messages);

        $order->setPaymentCurrency('GBP');
        $this->assertTrue(Craft::$app->getElements()->saveElement($order));
        $this->addToCart($order, $variant);
        $this->assertSame(100.0, $order->getLineItems()[0]->getPrice());
    }

    public function testCatalogPricingRulesReachTheCurrencyPriceByRatioForTheirCustomersOnly(): void
    {
        $variant = $this->createPricedVariant(100, ['USD' => 120]);
        $trade = $this->createUserInGroup('trade');

        // TS-4 #1–#2: 10% off for the trade group.
        $rule = new CatalogPricingRule([
            'name' => 'Trade 10%',
            'storeId' => $this->store->id,
            'apply' => CatalogPricingRuleRecord::APPLY_BY_PERCENT,
            'applyAmount' => -0.1,
            'applyPriceType' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE,
        ]);
        $condition = new CatalogPricingRuleCustomerCondition();
        $groupRule = new GroupConditionRule();
        $groupRule->setValues([$trade['group']->uid]);
        $condition->addConditionRule($groupRule);
        $rule->setCustomerCondition($condition);
        $this->assertTrue(Commerce::getInstance()->getCatalogPricingRules()->saveCatalogPricingRule($rule), implode(', ', $rule->getFirstErrors()));
        Commerce::getInstance()->getCatalogPricing()->generateCatalogPrices();

        $this->assertSame(108.0, $this->priceInCart($variant, 'USD', $trade['user']));
        $this->assertSame(90.0, $this->priceInCart($variant, 'GBP', $trade['user']));
        $this->assertSame(120.0, $this->priceInCart($variant, 'USD', null), 'A guest pays the currency price');

        // #3: a base promotional price.
        $variant->basePromotionalPrice = 80;
        $this->assertTrue(Craft::$app->getElements()->saveElement($variant));
        Commerce::getInstance()->getCatalogPricing()->generateCatalogPrices();
        $lineItem = $this->lineInCart($variant, 'USD', null);
        $this->assertSame(96.0, $lineItem->getPromotionalPrice());
        $this->assertSame(96.0, $lineItem->getSalePrice());
    }

    public function testASalePassesOnByRatio(): void
    {
        // TS-4 #4: any sale switches Commerce from catalog pricing to the sales system.
        $variant = $this->createPricedVariant(100, ['USD' => 120]);
        $sale = new Sale([
            'name' => '25% off',
            'apply' => 'byPercent',
            'applyAmount' => -0.25,
            'allGroups' => true,
            'allPurchasables' => true,
            'allCategories' => true,
            'enabled' => true,
        ]);
        $this->assertTrue(Commerce::getInstance()->getSales()->saveSale($sale), implode(', ', $sale->getFirstErrors()));
        $this->assertFalse(Commerce::getInstance()->getCatalogPricingRules()->canUseCatalogPricingRules());

        $lineItem = $this->lineInCart($variant, 'USD', null);
        $this->assertSame(120.0, $lineItem->getPrice());
        $this->assertSame(90.0, $lineItem->getPromotionalPrice());
        $this->assertSame(90.0, $lineItem->getSalePrice());
    }

    public function testABasePriceOfZeroGivesARatioOfOne(): void
    {
        // TS-4 #5
        $variant = $this->createPricedVariant(0, ['USD' => 15]);
        $lineItem = $this->lineInCart($variant, 'USD', null);

        $this->assertSame(15.0, $lineItem->getPrice());
        $this->assertNull($lineItem->getPromotionalPrice());
        $this->assertSame(1.0, $lineItem->getSnapshot()[Pricing::SNAPSHOT_KEY]['ratio']);
    }

    public function testAHandlerCanChangeThePriceAndSeesTheBasket(): void
    {
        $variant = $this->createPricedVariant(100, ['USD' => 120]);
        $seen = null;
        Event::on(CurrencyPrices::class, CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE, function(DefineCurrencyPriceEvent $event) use (&$seen) {
            $seen = $event;
            $event->price = 119;
            $event->promotionalPrice = 99.999;
        });

        $lineItem = $this->lineInCart($variant, 'USD', null);

        $this->assertSame($variant->id, $seen->purchasable->id);
        $this->assertSame('USD', $seen->currency);
        $this->assertInstanceOf(Order::class, $seen->order);
        $this->assertInstanceOf(LineItem::class, $seen->lineItem);
        $this->assertSame(119.0, $lineItem->getPrice());
        $this->assertSame(100.0, $lineItem->getPromotionalPrice(), 'Rounded to the currency');
    }

    public function testAPromotionalPriceThatIsNotBelowThePriceIsDropped(): void
    {
        // TN-8
        $variant = $this->createPricedVariant(100, ['USD' => 120]);
        Event::on(CurrencyPrices::class, CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE, function(DefineCurrencyPriceEvent $event) {
            $event->promotionalPrice = 130;
        });

        $lineItem = $this->lineInCart($variant, 'USD', null);

        $this->assertSame(120.0, $lineItem->getPrice());
        $this->assertNull($lineItem->getPromotionalPrice());
        $this->assertSame(120.0, $lineItem->getSalePrice());
    }

    public function testANegativePriceFromAHandlerIsClampedToZeroAndLogged(): void
    {
        // TN-13
        $variant = $this->createPricedVariant(100, ['USD' => 120]);
        Event::on(CurrencyPrices::class, CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE, function(DefineCurrencyPriceEvent $event) {
            $event->price = -5;
        });
        // Codeception's logger keeps no messages.
        $logger = Craft::getLogger();
        Craft::setLogger(new Logger(['flushInterval' => PHP_INT_MAX]));

        try {
            $lineItem = $this->lineInCart($variant, 'USD', null);
            $messages = Craft::getLogger()->messages;
        } finally {
            Craft::setLogger($logger);
        }

        $this->assertSame(0.0, $lineItem->getPrice());
        $logged = array_filter($messages, fn($message) => $message[1] === Logger::LEVEL_WARNING && is_string($message[0]) && str_contains($message[0], 'negative price (-5 USD)'));
        $this->assertNotEmpty($logged);
    }

    public function testAPaymentCurrencyTheStoreLacksIsResetAndTheOrderCurrencyFollows(): void
    {
        // TN-4
        $order = $this->createCart('JPY');

        $this->assertSame('GBP', $order->getPaymentCurrency());
        $this->assertSame('GBP', $order->currency);
    }

    public function testACompletedOrdersCurrencyIsNeverChanged(): void
    {
        // TN-5
        $variant = $this->createPricedVariant(100, ['USD' => 120]);
        $order = $this->createCart('USD');
        $this->addToCart($order, $variant);
        $this->assertTrue($this->payWithDummyGateway($order)->status === 'success');
        $this->assertTrue(Order::find()->id($order->id)->one()->isCompleted);
        $order = Order::find()->id($order->id)->one();

        $order->setPaymentCurrency('EUR');
        $this->assertTrue(Craft::$app->getElements()->saveElement($order));

        $order = Order::find()->id($order->id)->one();
        $this->assertSame('USD', $order->currency);
    }

    public function testARecalculationReadsTheBasketsPricesInOneQuery(): void
    {
        // BR-14
        $order = $this->createCart('USD');
        foreach ([120, 130, 140] as $price) {
            $this->addToCart($order, $this->createPricedVariant(100, ['USD' => $price]));
        }
        $this->assertCount(3, $order->getLineItems());
        CurrencyPrices::getInstance()->set('prices', ['class' => Prices::class]);

        $queries = CountingCommand::count(fn() => $order->recalculate(), 'currencyprices_prices');

        $this->assertSame(1, $queries);
        $this->assertEqualsCanonicalizing([120.0, 130.0, 140.0], array_map(fn(LineItem $lineItem) => $lineItem->getPrice(), $order->getLineItems()));
    }

    /**
     * @param array<string, float|int> $prices
     */
    private function createPricedVariant(float $basePrice, array $prices): Variant
    {
        $variant = $this->createVariant();
        $variant->basePrice = $basePrice;
        $variant->promotable = true;
        $this->assertTrue(Craft::$app->getElements()->saveElement($variant), implode(', ', $variant->getFirstErrors()));
        $this->prices->savePrices($variant->id, $this->store->id, $prices);

        return $variant;
    }

    /**
     * A saved cart with the payment currency set as `commerce/cart/update-cart` sets it.
     */
    private function createCart(string $paymentCurrency, ?User $customer = null): Order
    {
        $order = new Order(['storeId' => $this->store->id, 'number' => Commerce::getInstance()->getCarts()->generateCartNumber()]);
        if ($customer !== null) {
            $order->setCustomer($customer);
        } else {
            $order->setEmail('guest-' . uniqid() . '@example.com');
        }
        $order->paymentCurrency = $paymentCurrency;
        $this->assertTrue(Craft::$app->getElements()->saveElement($order), implode(', ', $order->getFirstErrors()));

        return $order;
    }

    private function addToCart(Order $order, Variant $variant): void
    {
        $lineItem = Commerce::getInstance()->getLineItems()->create($order, ['purchasableId' => $variant->id]);
        $order->addLineItem($lineItem);
        $this->assertTrue(Craft::$app->getElements()->saveElement($order), implode(', ', $order->getFirstErrors()));
    }

    private function lineInCart(Variant $variant, string $paymentCurrency, ?User $customer): LineItem
    {
        // Commerce memoises a purchasable by id whoever the customer is.
        Commerce::getInstance()->set('purchasables', ['class' => CommercePurchasables::class]);
        $order = $this->createCart($paymentCurrency, $customer);
        $this->addToCart($order, $variant);

        return $order->getLineItems()[0];
    }

    private function priceInCart(Variant $variant, string $paymentCurrency, ?User $customer): float
    {
        return $this->lineInCart($variant, $paymentCurrency, $customer)->getPrice();
    }

    /**
     * @return array{user: User, group: UserGroup}
     */
    private function createUserInGroup(string $handle): array
    {
        $group = new UserGroup(['name' => ucfirst($handle), 'handle' => $handle]);
        $this->assertTrue(Craft::$app->getUserGroups()->saveGroup($group));

        $user = new User(['username' => $handle . uniqid(), 'email' => $handle . uniqid() . '@example.com']);
        $this->assertTrue(Craft::$app->getElements()->saveElement($user, false));
        Craft::$app->getUsers()->assignUserToGroups($user->id, [$group->id]);

        return ['user' => $user, 'group' => $group];
    }

    private function payWithDummyGateway(Order $order): \craft\commerce\models\Transaction
    {
        $gateway = new Dummy(['name' => 'Dummy', 'handle' => 'dummy' . uniqid(), 'isFrontendEnabled' => true]);
        $this->assertTrue(Commerce::getInstance()->getGateways()->saveGateway($gateway), implode(', ', $gateway->getFirstErrors()));
        $order->gatewayId = $gateway->id;
        $this->assertTrue(Craft::$app->getElements()->saveElement($order));

        $form = $gateway->getPaymentFormModel();
        $form->setAttributes([
            'firstName' => 'Test',
            'lastName' => 'Shopper',
            'number' => '4242424242424242',
            'expiry' => '12/' . ((int)date('Y') + 2),
            'cvv' => '123',
        ], false);

        $redirect = null;
        $transaction = null;
        Commerce::getInstance()->getPayments()->processPayment($order, $form, $redirect, $transaction);
        $this->assertNotNull($transaction);

        return $transaction;
    }
}
