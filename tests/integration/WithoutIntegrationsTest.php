<?php

namespace webdna\commerce\currencyprices\tests\integration;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Variant;
use craft\models\FieldLayout;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\fieldlayoutelements\CurrencyPricesField;
use webdna\commerce\currencyprices\services\Purchasables;
use webdna\commerce\currencyprices\tests\Support\CommerceFixtures;

/**
 * This suite installs neither Digital Products nor Events, though both are in vendor/ for the
 * `integrations` suite. The plugin runs, and variants are edited and priced, without them (TS-6 #3, BR-17).
 */
class WithoutIntegrationsTest extends Unit
{
    use CommerceFixtures;

    protected function _before(): void
    {
        $this->setUpGbpStore();
    }

    public function testNeitherIntegrationIsInstalledAndOnlyPurchasablesArePriced(): void
    {
        $plugins = Craft::$app->getPlugins();
        $this->assertFalse($plugins->isPluginInstalled('digital-products'));
        $this->assertFalse($plugins->isPluginInstalled(Purchasables::EVENTS_HANDLE));

        $this->assertSame([Purchasable::class], CurrencyPrices::getInstance()->getPurchasables()->getPricedElementTypes());
    }

    public function testAnEventsTicketTypeLayoutIsOfferedNothingWhileEventsIsNotInstalled(): void
    {
        $layout = new FieldLayout(['type' => Purchasables::EVENTS_TICKET_TYPE]);

        $this->assertNotContains(CurrencyPricesField::class, array_map(fn($field) => $field::class, $layout->getAvailableNativeFields()));
    }

    public function testAVariantIsStillEditedAndPricedInAnotherCurrency(): void
    {
        $variant = $this->createPricedVariant(100, ['USD' => 120]);

        $fresh = Variant::find()->id($variant->id)->status(null)->one();
        $this->assertStringContainsString('data-currency-price="USD"', (string)(new CurrencyPricesField())->formHtml($fresh));

        $order = $this->createCart('USD');
        $this->addToCart($order, $variant);
        $this->assertSame(120.0, $order->getLineItems()[0]->getPrice());
    }
}
