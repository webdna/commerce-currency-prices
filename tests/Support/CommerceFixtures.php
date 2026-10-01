<?php

namespace webdna\commerce\currencyprices\tests\Support;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\commerce\models\Store;
use craft\commerce\Plugin as Commerce;
use craft\commerce\services\PaymentCurrencies;

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
}
