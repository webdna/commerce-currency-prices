<?php

namespace webdna\commerce\currencyprices\tests\Support;

use Craft;
use craft\commerce\elements\conditions\customers\CatalogPricingRuleCustomerCondition;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\commerce\models\Store;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\CatalogPricingRule as CatalogPricingRuleRecord;
use craft\commerce\services\PaymentCurrencies;
use craft\elements\conditions\users\GroupConditionRule;
use craft\elements\User;
use craft\models\UserGroup;
use webdna\commerce\currencyprices\CurrencyPrices;

/**
 * The integration tests' Commerce data: a GBP primary store with USD and EUR payment currencies, as in
 * the spec's examples, and variants of a `clothing` product type.
 */
trait CommerceFixtures
{
    private Store $store;

    private function setUpGbpStore(): void
    {
        $this->store = Commerce::getInstance()->getStores()->getPrimaryStore();
        $this->store->setCurrency('GBP');
        $this->assertTrue(Commerce::getInstance()->getStores()->saveStore($this->store));

        $this->addPaymentCurrencies($this->store->id, ['USD', 'EUR']);
    }

    /**
     * @param string[] $isos
     */
    private function addPaymentCurrencies(int $storeId, array $isos): void
    {
        $paymentCurrencies = Commerce::getInstance()->getPaymentCurrencies();

        foreach ($isos as $iso) {
            if ($paymentCurrencies->getPaymentCurrencyByIso($iso, $storeId) !== null) {
                continue;
            }

            $paymentCurrency = new PaymentCurrency(['storeId' => $storeId, 'iso' => $iso, 'rate' => 1]);
            $this->assertTrue($paymentCurrencies->savePaymentCurrency($paymentCurrency), implode(', ', $paymentCurrency->getFirstErrors()));
        }

        $this->resetPaymentCurrencies();
    }

    /**
     * Commerce's save doesn't clear the service's per-request list of a store's currencies.
     */
    private function resetPaymentCurrencies(): void
    {
        Commerce::getInstance()->set('paymentCurrencies', ['class' => PaymentCurrencies::class]);
    }

    private function getProductType(): ProductType
    {
        $productTypes = Commerce::getInstance()->getProductTypes();
        $productType = $productTypes->getProductTypeByHandle('clothing');

        if ($productType === null) {
            $productType = new ProductType([
                'name' => 'Clothing',
                'handle' => 'clothing',
                'skuFormat' => null,
            ]);

            $siteSettings = [];
            foreach (Craft::$app->getSites()->getAllSites() as $site) {
                $siteSetting = new ProductTypeSite(['siteId' => $site->id, 'hasUrls' => false]);
                $siteSetting->setProductType($productType);
                $siteSettings[$site->id] = $siteSetting;
            }
            $productType->setSiteSettings($siteSettings);

            $this->assertTrue($productTypes->saveProductType($productType), implode(', ', $productType->getFirstErrors()));
        }

        return $productType;
    }

    /**
     * A saved variant of a saved product. Saving the product with `setVariants()` does not save the
     * variants in the test environment, so the variant is saved on its own.
     */
    private function createVariant(): Variant
    {
        static $count = 0;
        $count++;

        $product = new Product(['typeId' => $this->getProductType()->id, 'title' => 'Shirt ' . $count]);
        $this->assertTrue(Craft::$app->getElements()->saveElement($product), implode(', ', $product->getFirstErrors()));

        $variant = new Variant(['title' => 'Shirt ' . $count, 'sku' => 'CP-' . $count . '-' . uniqid(), 'basePrice' => 10]);
        $variant->setPrimaryOwner($product);
        $variant->setOwner($product);
        $this->assertTrue(Craft::$app->getElements()->saveElement($variant), implode(', ', $variant->getFirstErrors()));

        return $variant;
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
        CurrencyPrices::getInstance()->getPrices()->savePrices($variant->id, $this->store->id, $prices);

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

    private function addToCart(Order $order, Variant $variant, int $qty = 1): void
    {
        $lineItem = Commerce::getInstance()->getLineItems()->create($order, ['purchasableId' => $variant->id, 'qty' => $qty]);
        $order->addLineItem($lineItem);
        $this->assertTrue(Craft::$app->getElements()->saveElement($order), implode(', ', $order->getFirstErrors()));
    }

    /**
     * @return array{user: User, group: UserGroup}
     */
    private function createUserInGroup(string $handle): array
    {
        // Groups live in project config, which outlasts a test, so each handle is new.
        $handle .= uniqid();
        $group = new UserGroup(['name' => ucfirst($handle), 'handle' => $handle]);
        $this->assertTrue(Craft::$app->getUserGroups()->saveGroup($group), implode(', ', $group->getFirstErrors()));

        $user = new User(['username' => $handle . uniqid(), 'email' => $handle . uniqid() . '@example.com']);
        $this->assertTrue(Craft::$app->getElements()->saveElement($user, false));
        Craft::$app->getUsers()->assignUserToGroups($user->id, [$group->id]);

        return ['user' => $user, 'group' => $group];
    }

    /**
     * A catalog pricing rule taking a percentage off every price in the store, for one customer group or
     * for everyone, with the catalog prices generated.
     */
    private function createCatalogPricingRule(float $percentOff, ?UserGroup $group = null): CatalogPricingRule
    {
        $rule = new CatalogPricingRule([
            'name' => $percentOff . '% off',
            'storeId' => $this->store->id,
            'apply' => CatalogPricingRuleRecord::APPLY_BY_PERCENT,
            'applyAmount' => -$percentOff / 100,
            'applyPriceType' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE,
        ]);
        if ($group !== null) {
            $condition = new CatalogPricingRuleCustomerCondition();
            $groupRule = new GroupConditionRule();
            $groupRule->setValues([$group->uid]);
            $condition->addConditionRule($groupRule);
            $rule->setCustomerCondition($condition);
        }
        $this->assertTrue(Commerce::getInstance()->getCatalogPricingRules()->saveCatalogPricingRule($rule), implode(', ', $rule->getFirstErrors()));
        Commerce::getInstance()->getCatalogPricing()->generateCatalogPrices();

        return $rule;
    }
}
