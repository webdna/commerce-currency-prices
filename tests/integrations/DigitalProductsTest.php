<?php

namespace webdna\commerce\currencyprices\tests\integrations;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\Plugin as Commerce;
use craft\commerce\services\Purchasables as CommercePurchasables;
use craft\digitalproducts\elements\Product as DigitalProduct;
use craft\models\FieldLayout;
use craft\web\Request as WebRequest;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\fieldlayoutelements\CurrencyPricesField;
use webdna\commerce\currencyprices\services\Prices;
use webdna\commerce\currencyprices\services\Purchasables;
use webdna\commerce\currencyprices\tests\Support\CommerceFixtures;
use webdna\commerce\currencyprices\tests\Support\OtherPurchasablesFixtures;
use yii\base\Request;

/**
 * Digital Products 4.1 (task 2.2): a digital product type's layout offers the Currency prices element, a
 * digital product's prices are saved in its own save, and a basket in another currency charges them
 * (TS-6 #1–#2, BR-2). Prices posted to Digital Products' own save action reach the product (A#8).
 */
class DigitalProductsTest extends Unit
{
    use CommerceFixtures;
    use OtherPurchasablesFixtures;

    /**
     * The application's own request, put back after a test that replaced it.
     */
    private ?Request $request = null;

    protected function _before(): void
    {
        $this->setUpGbpStore();
        Commerce::getInstance()->set('purchasables', ['class' => CommercePurchasables::class]);
    }

    protected function _after(): void
    {
        if ($this->request !== null) {
            Craft::$app->set('request', $this->request);
            $this->request = null;
        }
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

    /**
     * Digital Products saves a product from its own action, which never hands the layout element's posted
     * prices to the product (A#8). The prices posted with it are saved.
     */
    public function testPricesPostedToDigitalProductsOwnSaveActionAreSaved(): void
    {
        $product = $this->createDigitalProduct(10);
        $this->postToAction(Purchasables::DIGITAL_PRODUCTS_SAVE_ACTION, $product->id, [
            'USD' => ['value' => '12.50', 'locale' => 'en'],
            'EUR' => ['value' => '', 'locale' => 'en'],
        ]);

        $this->assertTrue(Craft::$app->getElements()->saveElement($this->fresh($product)));

        $this->assertSame(['USD' => 12.5], (new Prices())->getPrices($product->id, $this->store->id));
    }

    public function testANegativePricePostedToDigitalProductsSaveActionFailsTheSave(): void
    {
        $product = $this->createDigitalProduct(10, ['USD' => 12.5]);
        $this->postToAction(Purchasables::DIGITAL_PRODUCTS_SAVE_ACTION, $product->id, ['USD' => ['value' => '-5', 'locale' => 'en']]);
        $fresh = $this->fresh($product);

        $this->assertFalse(Craft::$app->getElements()->saveElement($fresh));
        $this->assertContains('Enter a price of 0 or more for USD.', $fresh->getErrors(Purchasables::ATTRIBUTE));
        $this->assertSame(['USD' => 12.5], (new Prices())->getPrices($product->id, $this->store->id));
    }

    public function testPricesAreNotTakenFromAnotherActionOrForAnotherProduct(): void
    {
        $product = $this->createDigitalProduct(10, ['USD' => 12.5]);
        $other = $this->createDigitalProduct(10);
        $posted = ['USD' => ['value' => '99', 'locale' => 'en']];

        // Craft's own element editor posts the element's attributes namespaced, and Craft hands them over itself.
        $this->postToAction(['elements', 'save'], $product->id, $posted);
        $this->assertTrue(Craft::$app->getElements()->saveElement($this->fresh($product)));

        // A product saved while another one's save action runs.
        $this->postToAction(Purchasables::DIGITAL_PRODUCTS_SAVE_ACTION, $other->id, $posted);
        $this->assertTrue(Craft::$app->getElements()->saveElement($this->fresh($product)));

        $this->assertSame(['USD' => 12.5], (new Prices())->getPrices($product->id, $this->store->id));
    }

    /**
     * Makes the current request a POST to the given action, as Craft resolves one, with the product's id and
     * the layout element's inputs in the body.
     *
     * @param string[] $actionSegments
     * @param array<string, array{value: string, locale: string}> $prices
     */
    private function postToAction(array $actionSegments, int $productId, array $prices): void
    {
        $request = new class($actionSegments) extends WebRequest {
            /** @param string[] $segments */
            public function __construct(private readonly array $segments)
            {
                parent::__construct();
            }

            public function getActionSegments(): array
            {
                return $this->segments;
            }

            public function getMethod(): string
            {
                return 'POST';
            }
        };
        $request->setBodyParams(['productId' => (string)$productId, Purchasables::ATTRIBUTE => $prices]);

        $this->request ??= Craft::$app->getRequest();
        Craft::$app->set('request', $request);
    }

    private function fresh(DigitalProduct $product): DigitalProduct
    {
        /** @var DigitalProduct */
        return DigitalProduct::find()->id($product->id)->status(null)->one();
    }
}
