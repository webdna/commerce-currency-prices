<?php

namespace webdna\commerce\currencyprices\tests\integration;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\conditions\orders\DiscountOrderCondition;
use craft\commerce\elements\conditions\orders\OrderCondition;
use craft\commerce\elements\conditions\orders\ShippingMethodOrderCondition;
use craft\commerce\elements\conditions\orders\ShippingRuleOrderCondition;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\models\Discount;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin as Commerce;
use craft\commerce\services\Discounts;
use craft\commerce\services\ShippingMethods;
use craft\commerce\services\ShippingRules;
use webdna\commerce\currencyprices\elements\conditions\PaymentCurrencyConditionRule;
use webdna\commerce\currencyprices\tests\Support\CommerceFixtures;

/**
 * The Payment Currency condition rule (task 1.4): a discount or shipping rule per currency applies only
 * to baskets in that currency, and one with no condition applies whatever the currency (TS-5, BR-12,
 * BR-13). The variant costs £60, $70 and €70, so each basket clears every threshold in its own figures.
 */
class PaymentCurrencyConditionTest extends Unit
{
    use CommerceFixtures;

    protected function _before(): void
    {
        $this->setUpGbpStore();

        // Commerce keeps each store's discounts and shipping for the request.
        Commerce::getInstance()->set('discounts', ['class' => Discounts::class]);
        Commerce::getInstance()->set('shippingMethods', ['class' => ShippingMethods::class]);
        Commerce::getInstance()->set('shippingRules', ['class' => ShippingRules::class]);
    }

    public function testTheRuleIsOfferedOnDiscountsShippingMethodsAndShippingRules(): void
    {
        foreach ([DiscountOrderCondition::class, ShippingMethodOrderCondition::class, ShippingRuleOrderCondition::class] as $class) {
            $condition = Craft::$app->getConditions()->createCondition(['class' => $class, 'storeId' => $this->store->id]);
            $this->assertArrayHasKey(PaymentCurrencyConditionRule::class, $condition->getSelectableConditionRules(), $class);
        }
    }

    public function testTheRuleOffersTheStoresPaymentCurrenciesIncludingTheStoreCurrencyAndSaysFixedAmountsNeedIt(): void
    {
        $condition = Craft::$app->getConditions()->createCondition(['class' => DiscountOrderCondition::class, 'storeId' => $this->store->id]);
        $html = $condition->createConditionRule(PaymentCurrencyConditionRule::class)->getHtml();

        foreach (['GBP', 'USD', 'EUR'] as $iso) {
            $this->assertStringContainsString('value="' . $iso . '"', $html);
        }
        $this->assertStringContainsString('is one of', $html);
        $this->assertStringContainsString('is not one of', $html);
        $this->assertStringContainsString('Fixed amounts apply as entered', $html);
    }

    public function testEachCurrencysFixedDiscountAppliesOnlyToBasketsInThatCurrency(): void
    {
        $this->createDiscount('$6 off over $65', -6, 65, 'USD');
        $this->createDiscount('£5 off over £50', -5, 50, 'GBP');
        $variant = $this->createPricedVariant(60, ['USD' => 70, 'EUR' => 70]);

        $this->assertSame(-6.0, $this->basket('USD', $variant)->getTotalDiscount());
        $this->assertSame(-5.0, $this->basket('GBP', $variant)->getTotalDiscount());
        $this->assertSame(0.0, $this->basket('EUR', $variant)->getTotalDiscount());
    }

    public function testIsNotOneOfExcludesTheListedCurrencies(): void
    {
        $this->createDiscount('£5 off outside the US', -5, 0, 'USD', 'ni');
        $variant = $this->createPricedVariant(60, ['USD' => 70, 'EUR' => 70]);

        $this->assertSame(0.0, $this->basket('USD', $variant)->getTotalDiscount());
        $this->assertSame(-5.0, $this->basket('EUR', $variant)->getTotalDiscount());
    }

    public function testEachCurrencysShippingRuleAppliesOnlyToBasketsInThatCurrency(): void
    {
        $method = new ShippingMethod(['storeId' => $this->store->id, 'name' => 'Standard', 'handle' => 'standard' . uniqid(), 'enabled' => true]);
        $this->assertTrue(Commerce::getInstance()->getShippingMethods()->saveShippingMethod($method), implode(', ', $method->getFirstErrors()));
        $this->createShippingRule($method, '$9 flat', 9, 'USD', 0);
        $this->createShippingRule($method, '£7 flat', 7, 'GBP', 1);
        $variant = $this->createPricedVariant(60, ['USD' => 70]);

        $this->assertSame(9.0, $this->basket('USD', $variant, $method->getHandle())->getTotalShippingCost());
        $this->assertSame(7.0, $this->basket('GBP', $variant, $method->getHandle())->getTotalShippingCost());
    }

    public function testAPercentageDiscountWithNoConditionAppliesInTheBasketsCurrency(): void
    {
        $this->createDiscount('10% off', 0, 0, null, 'in', -0.1);
        $variant = $this->createPricedVariant(60, ['USD' => 70]);

        $order = $this->basket('USD', $variant);
        $this->assertSame('USD', $order->currency);
        $this->assertSame(-7.0, $order->getTotalDiscount());
    }

    public function testTheRuleFiltersTheOrderIndex(): void
    {
        $variant = $this->createPricedVariant(60, ['USD' => 70]);
        $usd = $this->basket('USD', $variant);
        $gbp = $this->basket('GBP', $variant);

        /** @var PaymentCurrencyConditionRule $rule */
        $rule = Craft::$app->getConditions()->createCondition(OrderCondition::class)->createConditionRule([
            'class' => PaymentCurrencyConditionRule::class,
            'values' => ['USD'],
        ]);
        $query = Order::find()->isCompleted(false)->id([$usd->id, $gbp->id]);
        $rule->modifyQuery($query);

        $this->assertSame([$usd->id], array_map('intval', $query->ids()));
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

    private function createDiscount(string $name, float $baseDiscount, float $purchaseTotal, ?string $currency, string $operator = 'in', float $percentDiscount = 0): void
    {
        $discount = new Discount([
            'storeId' => $this->store->id,
            'name' => $name,
            'enabled' => true,
            'allPurchasables' => true,
            'allCategories' => true,
            'baseDiscount' => $baseDiscount,
            'percentDiscount' => $percentDiscount,
            'purchaseTotal' => $purchaseTotal,
        ]);
        $discount->setOrderCondition($this->currencyCondition(DiscountOrderCondition::class, $currency, $operator));

        $this->assertTrue(Commerce::getInstance()->getDiscounts()->saveDiscount($discount), implode(', ', $discount->getFirstErrors()));
    }

    private function createShippingRule(ShippingMethod $method, string $name, float $baseRate, string $currency, int $priority): void
    {
        $rule = new ShippingRule([
            'storeId' => $this->store->id,
            'methodId' => $method->id,
            'name' => $name,
            'enabled' => true,
            'baseRate' => $baseRate,
            'priority' => $priority,
        ]);
        $rule->setOrderCondition($this->currencyCondition(ShippingRuleOrderCondition::class, $currency));

        $this->assertTrue(Commerce::getInstance()->getShippingRules()->saveShippingRule($rule), implode(', ', $rule->getFirstErrors()));
    }

    /**
     * A condition's config as the CP posts it: no rule when there is no currency.
     *
     * @return array<string, mixed>
     */
    private function currencyCondition(string $class, ?string $currency, string $operator = 'in'): array
    {
        return [
            'class' => $class,
            'storeId' => $this->store->id,
            'conditionRules' => $currency === null ? [] : [[
                'class' => PaymentCurrencyConditionRule::class,
                'operator' => $operator,
                'values' => [$currency],
            ]],
        ];
    }
}
