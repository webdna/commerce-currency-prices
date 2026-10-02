<?php

namespace webdna\commerce\currencyprices\tests\integrations;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\Plugin as Commerce;
use craft\commerce\services\Purchasables as CommercePurchasables;
use craft\digitalproducts\elements\Product as DigitalProduct;
use craft\models\FieldLayout;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\fieldlayoutelements\CurrencyPricesField;
use webdna\commerce\currencyprices\services\Prices;
use webdna\commerce\currencyprices\tests\Support\CommerceFixtures;
use webdna\commerce\currencyprices\tests\Support\OtherPurchasablesFixtures;

/**
 * Digital Products 4.1 (task 2.2): a digital product type's layout offers the Currency prices element, a
 * digital product's prices are saved in its own save, and a basket in another currency charges them
 * (TS-6 #1–#2, BR-2).
 */
class DigitalProductsTest extends Unit
{
    use CommerceFixtures;
    use OtherPurchasablesFixtures;

    protected function _before(): void
    {
        $this->setUpGbpStore();
        Commerce::getInstance()->set('purchasables', ['class' => CommercePurchasables::class]);
    }

    public function testTheElementIsOfferedOnADigitalProductTypesLayout(): void
    {
        $layout = new FieldLayout(['type' => DigitalProduct::class]);

        $this->assertContains(CurrencyPricesField::class, array_map(fn($field) => $field::class, $layout->getAvailableNativeFields()));
    }

    public function testPricesPostedOnADigitalProductAreSavedAndShownInItsInputs(): void
    {
        $product = $this->createDigitalProduct(10, ['USD' => '12.50', 'EUR' => '']);

        $this->assertSame(['USD' => 12.5], (new Prices())->getPrices($product->id, $this->store->id));

        $fresh = DigitalProduct::find()->id($product->id)->status(null)->one();
        $html = (string)(new CurrencyPricesField())->formHtml($fresh);
        $this->assertStringContainsString('data-currency-price="USD"', $html);
        $this->assertStringContainsString('data-currency-price="EUR"', $html);
        $this->assertStringContainsString('No price — not for sale in EUR', $html);
    }

    public function testAUsdBasketChargesTheDigitalProductsUsdPrice(): void
    {
        $product = $this->createDigitalProduct(10, ['USD' => 12.5]);
        $order = $this->createCart('USD');

        $this->addToCart($order, $product, 2);

        $lineItem = $order->getLineItems()[0];
        $this->assertSame(12.5, $lineItem->getPrice());
        $this->assertSame(25.0, $order->getTotal());
        $this->assertSame('USD', $order->currency);
    }

    public function testADigitalProductWithNoPriceInTheBasketsCurrencyCannotBeAdded(): void
    {
        $product = $this->createDigitalProduct(10, ['USD' => 12.5]);
        $order = $this->createCart('EUR');

        $this->assertFalse(Commerce::getInstance()->getPurchasables()->isPurchasableAvailable($product, $order));
    }

    public function testADuplicatedDigitalProductCarriesItsPrices(): void
    {
        $product = $this->createDigitalProduct(10, ['USD' => 12.5]);

        $copy = Craft::$app->getElements()->duplicateElement($product, ['sku' => 'DP-COPY-' . uniqid()]);

        $this->assertSame(['USD' => 12.5], (new Prices())->getPrices($copy->id, $this->store->id));
    }

    public function testThePriceAppliesToTheDigitalProductInTemplates(): void
    {
        $product = $this->createDigitalProduct(10, ['USD' => 12.5]);

        $this->assertSame(12.5, CurrencyPrices::getInstance()->getPrices()->getPrice($product->id, $this->store->id, 'USD'));
        $this->assertSame('12.5', Craft::$app->getView()->renderString('{{ product|currencyPrice("USD") }}', ['product' => $product]));
    }
}
