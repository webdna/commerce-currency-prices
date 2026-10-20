<?php

namespace webdna\commerce\currencyprices\tests\integration;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\fieldlayoutelements\PurchasablePriceField;
use craft\commerce\fieldlayoutelements\PurchasableSkuField;
use craft\commerce\fieldlayoutelements\PurchasableStockField;
use craft\commerce\fieldlayoutelements\VariantTitleField;
use craft\commerce\Plugin as Commerce;
use craft\db\Table;
use craft\elements\User;
use craft\events\PluginEvent;
use craft\helpers\Db;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\services\Plugins;
use webdna\commerce\currencyprices\behaviors\CurrencyPricesBehavior;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\fieldlayoutelements\CurrencyPricesField;
use webdna\commerce\currencyprices\migrations\Install;
use webdna\commerce\currencyprices\services\Prices;
use webdna\commerce\currencyprices\services\Purchasables;
use webdna\commerce\currencyprices\tests\Support\CommerceFixtures;

/**
 * The layout element and saving (task 1.2): prices typed on an item are written in its own save (TS-1,
 * BR-2–BR-4), fail validation when negative or not a number (TN-1), follow drafts, revisions and
 * duplicates (TS-7, BR-5), survive their currency being removed and re-added (TN-3), and need nothing
 * beyond the right to edit the item (TN-12, BR-22).
 */
class CurrencyPricesFieldTest extends Unit
{
    use CommerceFixtures;

    private Prices $prices;

    protected function _before(): void
    {
        $this->prices = CurrencyPrices::getInstance()->getPrices();
        $this->setUpGbpStore();
    }

    public function testTheElementIsOfferedOnPurchasableLayoutsOnly(): void
    {
        $this->assertContains(CurrencyPricesField::class, $this->nativeFieldClasses(Variant::class));
        $this->assertNotContains(CurrencyPricesField::class, $this->nativeFieldClasses(Product::class));
    }

    public function testInstallAddsItBelowThePriceOfEachVariantLayoutOnce(): void
    {
        // A product type made in the CP has the price element on its variant layout.
        $productType = $this->getProductType();
        $layout = $productType->getVariantFieldLayout();
        $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout]);
        $tab->setElements([new VariantTitleField(), new PurchasableSkuField(), new PurchasablePriceField(), new PurchasableStockField()]);
        $layout->setTabs([$tab]);
        $this->assertTrue(Commerce::getInstance()->getProductTypes()->saveProductType($productType));
        $purchasables = CurrencyPrices::getInstance()->getPurchasables();

        $this->assertSame(1, $purchasables->addToVariantLayouts());
        $this->assertSame(0, $purchasables->addToVariantLayouts(), 'A layout that has it is left alone');

        $layout = Commerce::getInstance()->getProductTypes()->getProductTypeById($productType->id)->getVariantFieldLayout();
        $classes = array_map(fn($element) => $element::class, $layout->getTabs()[0]->getElements());
        $price = array_search(PurchasablePriceField::class, $classes, true);
        $this->assertSame(CurrencyPricesField::class, $classes[$price + 1] ?? null, 'Directly below the price');

        $this->assertSame(1, $purchasables->removeFromVariantLayouts());
        $layout = Commerce::getInstance()->getProductTypes()->getProductTypeById($productType->id)->getVariantFieldLayout();
        $this->assertEmpty($layout->getElementsByType(CurrencyPricesField::class));
    }

    public function testAVariantLayoutWithoutThePriceGetsItInItsOwnTab(): void
    {
        // A Commerce 4 product type with no variant fields has no variant layout after the upgrade, and on
        // the console Commerce defines no variant fields to put in one (task 3.2).
        $productType = $this->getProductType();
        $productType->getVariantFieldLayout()->setTabs([]);
        $this->assertTrue(Commerce::getInstance()->getProductTypes()->saveProductType($productType));

        $this->assertGreaterThanOrEqual(1, CurrencyPrices::getInstance()->getPurchasables()->addToVariantLayouts());

        $tabs = Commerce::getInstance()->getProductTypes()->getProductTypeById($productType->id)->getVariantFieldLayout()->getTabs();
        $this->assertCount(1, $tabs);
        $this->assertSame([CurrencyPricesField::class], array_map(fn($element) => $element::class, $tabs[0]->getElements()));
    }

    public function testInstallAddsItOnceCraftHasEnabledThePlugin(): void
    {
        $productType = $this->getProductType();
        $layout = $productType->getVariantFieldLayout();
        $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout]);
        $tab->setElements([new VariantTitleField(), new PurchasablePriceField()]);
        $layout->setTabs([$tab]);
        $this->assertTrue(Commerce::getInstance()->getProductTypes()->saveProductType($productType));
        $count = fn() => count(Commerce::getInstance()->getProductTypes()->getProductTypeById($productType->id)
            ->getVariantFieldLayout()->getElementsByType(CurrencyPricesField::class));

        // Craft runs the migration and afterInstall() before it enables the plugin; a layout tab drops an
        // element whose plugin is not enabled, so neither touches the layouts.
        (new Install())->safeUp();
        $plugin = CurrencyPrices::getInstance();
        (new \ReflectionMethod($plugin, 'afterInstall'))->invoke($plugin);
        $this->assertSame(0, $count());

        $plugins = Craft::$app->getPlugins();
        $plugins->trigger(Plugins::EVENT_AFTER_INSTALL_PLUGIN, new PluginEvent(['plugin' => Commerce::getInstance()]));
        $this->assertSame(0, $count(), 'Another plugin\'s install is ignored');

        $plugins->trigger(Plugins::EVENT_AFTER_INSTALL_PLUGIN, new PluginEvent(['plugin' => $plugin]));
        $this->assertSame(1, $count());
        $this->assertFalse($plugins->hasEventHandlers(Plugins::EVENT_AFTER_INSTALL_PLUGIN), 'The handler runs once');
    }

    public function testPricesPostedOnAVariantAreSavedInItsOwnSaveAndABlankIsNoPrice(): void
    {
        $variant = $this->createVariant();

        $this->saveWithPrices($variant, [
            'USD' => ['value' => '120', 'locale' => 'en-US'],
            'EUR' => ['value' => '', 'locale' => 'en-US'],
        ]);

        $this->assertSame(['USD' => 120.0], $this->prices->getPrices($variant->id, $this->store->id));
    }

    public function testZeroIsAPrice(): void
    {
        $variant = $this->createVariant();

        $this->saveWithPrices($variant, ['EUR' => '0']);

        $this->assertSame(0.0, $this->prices->getPrice($variant->id, $this->store->id, 'EUR'));
    }

    public function testALocalisedNumberIsRead(): void
    {
        $variant = $this->createVariant();

        $this->saveWithPrices($variant, ['EUR' => ['value' => '1.234,5', 'locale' => 'de-DE']]);

        $this->assertSame(1234.5, $this->prices->getPrice($variant->id, $this->store->id, 'EUR'));
    }

    public function testANegativePriceFailsValidationAndNothingIsSaved(): void
    {
        $variant = $this->createVariant();
        $this->saveWithPrices($variant, ['USD' => '120']);

        $this->post($variant, ['USD' => ['value' => '-5', 'locale' => 'en-US'], 'EUR' => '30']);

        $this->assertFalse(Craft::$app->getElements()->saveElement($variant));
        $this->assertSame(['Enter a price of 0 or more for USD.'], $variant->getErrors('currencyPrices'));
        $this->assertSame(['USD' => 120.0], $this->freshPrices($variant), 'Neither USD nor EUR changed');
    }

    public function testANonNumericPriceFailsValidationAndNothingIsSaved(): void
    {
        $variant = $this->createVariant();

        $this->post($variant, ['EUR' => 'abc']);

        $this->assertFalse(Craft::$app->getElements()->saveElement($variant));
        $this->assertSame(['Enter a price of 0 or more for EUR.'], $variant->getErrors('currencyPrices'));
        $this->assertSame([], $this->freshPrices($variant));
    }

    public function testASaveThatPostsNoPricesLeavesThemUntouched(): void
    {
        $variant = $this->createVariant();
        $this->saveWithPrices($variant, ['USD' => '120']);

        $resaved = Variant::find()->id($variant->id)->status(null)->one();
        $resaved->basePrice = 11;
        $this->assertTrue(Craft::$app->getElements()->saveElement($resaved));

        $this->assertSame(['USD' => 120.0], $this->freshPrices($variant));
    }

    public function testTheInputsShowEachCurrencysPriceAndAnUnpricedOneIsNotForSale(): void
    {
        $variant = $this->createVariant();
        $this->saveWithPrices($variant, ['USD' => '120']);

        $html = $this->renderElement($variant);

        $this->assertSame(2, substr_count($html, 'data-currency-price='));
        $this->assertStringContainsString('data-currency-price="USD"', $html);
        $this->assertStringContainsString('data-currency-price="EUR"', $html);
        $this->assertMatchesRegularExpression('/value="120[^"]*"[^>]*data-currency-price="USD"|data-currency-price="USD"[^>]*value="120/', $html);
        $this->assertStringContainsString('No price — not for sale in EUR', $html);
        $this->assertStringNotContainsString('not for sale in USD', $html);
        $this->assertStringNotContainsString('data-currency-prices-none', $html);
    }

    public function testAStoreWithNoOtherCurrenciesShowsTheNotice(): void
    {
        $variant = $this->createVariant();
        $this->removePaymentCurrencies(['USD', 'EUR']);

        $html = $this->renderElement($variant);

        $this->assertStringContainsString('data-currency-prices-none', $html);
        $this->assertStringContainsString('This store only accepts GBP.', $html);
        // Commerce 5's name for the screen (A#9)
        $this->assertStringContainsString('Commerce → Store Management → Payment Currencies', $html);
        $this->assertStringNotContainsString('data-currency-price=', $html);
    }

    public function testARemovedCurrencysPricesApplyAgainWhenItIsReAdded(): void
    {
        $variant = $this->createVariant();
        $this->saveWithPrices($variant, ['USD' => '120', 'EUR' => '110']);

        $this->removePaymentCurrencies(['USD']);
        $this->assertSame(['EUR'], $this->prices->getEligibleCurrencies($this->store->id));
        $this->saveWithPrices($variant, ['USD' => '', 'EUR' => '111']);

        $this->addPaymentCurrencies($this->store->id, ['USD']);
        $this->assertSame(['EUR' => 111.0, 'USD' => 120.0], $this->sorted($this->freshPrices($variant)), 'A posted currency the store no longer accepts is ignored');
    }

    public function testAVariantDraftHasItsOwnPricesUntilItIsApplied(): void
    {
        $variant = $this->createVariant();
        $this->saveWithPrices($variant, ['USD' => '120']);

        $draft = Craft::$app->getDrafts()->createDraft($variant, 1);
        $this->assertSame(['USD' => 120.0], $this->freshPrices($draft), 'Copied when the draft is created');

        $this->saveWithPrices($draft, ['USD' => '140']);
        $this->assertSame(['USD' => 120.0], $this->freshPrices($variant), 'The live variant is unchanged');

        Craft::$app->getDrafts()->applyDraft($draft);
        $this->assertSame(['USD' => 140.0], $this->freshPrices($variant), 'Published');
    }

    public function testAProductDraftsVariantPricesArePublishedWithItAndARevisionRestoresThem(): void
    {
        $variant = $this->createVariant();
        $this->saveWithPrices($variant, ['USD' => '120']);
        /** @var Product $product */
        $product = $variant->getOwner();
        $revisionId = Craft::$app->getRevisions()->createRevision($product, 1, force: true);

        // An editor changing a variant inside a product draft gets a copy owned by the draft that tracks
        // the live variant, as NestedElementManager::duplicateNestedElements() makes it.
        $draft = Craft::$app->getDrafts()->createDraft($product, 1);
        $draftVariant = Craft::$app->getElements()->duplicateElement($variant, [
            'canonicalId' => $variant->id,
            'primaryOwner' => $draft,
            'owner' => $draft,
        ]);
        $this->assertSame(['USD' => 120.0], $this->freshPrices($draftVariant), 'Copied for the draft');

        // ElementsController::actionSaveNestedElementForDerivative(): the draft owns only the copy.
        Db::delete(Table::ELEMENTS_OWNERS, ['elementId' => $variant->id, 'ownerId' => $draft->id]);
        $this->saveWithPrices($draftVariant, ['USD' => '140']);
        $this->assertSame(['USD' => 120.0], $this->freshPrices($variant), 'The live variant is unchanged');

        // The editor's submit marks the draft's variants as changed, which is what makes applying copy them.
        Db::upsert(Table::CHANGEDATTRIBUTES, [
            'elementId' => $draft->id,
            'siteId' => $draft->siteId,
            'attribute' => 'variants',
            'propagated' => false,
            'userId' => 1,
        ]);
        $draft = Product::find()->id($draft->id)->drafts()->provisionalDrafts(null)->status(null)->one();

        Craft::$app->getDrafts()->applyDraft($draft);
        $this->assertSame(['USD' => 140.0], $this->freshPrices($variant), 'Published');

        $revision = Product::find()->id($revisionId)->revisions()->status(null)->one();
        $this->assertNotNull($revision);
        $revisionVariant = Variant::find()->ownerId($revision->id)->revisions(null)->status(null)->one();
        $this->assertSame(['USD' => 120.0], $this->freshPrices($revisionVariant), 'The revision kept its prices');

        Craft::$app->getRevisions()->revertToRevision($revision, 1);
        $this->assertSame(['USD' => 120.0], $this->freshPrices($variant), 'Reverted');
    }

    public function testADuplicatedProductsVariantsCarryTheirPrices(): void
    {
        $variant = $this->createVariant();
        $this->saveWithPrices($variant, ['USD' => '120', 'EUR' => '110']);

        $copy = Craft::$app->getElements()->duplicateElement($variant->getOwner());
        $copyVariant = Variant::find()->ownerId($copy->id)->status(null)->one();

        $this->assertNotSame($variant->id, $copyVariant->id);
        $this->assertSame(['EUR' => 110.0, 'USD' => 120.0], $this->sorted($this->freshPrices($copyVariant)));
    }

    public function testAnEditorWithoutPermissionToEditTheProductCannotReachThePrices(): void
    {
        $variant = $this->createVariant();
        $editor = new User(['username' => 'editor' . uniqid(), 'email' => uniqid() . '@example.com']);
        $this->assertTrue(Craft::$app->getElements()->saveElement($editor, false));

        $this->assertFalse(Craft::$app->getElements()->canView($variant, $editor));
        $this->assertFalse(Craft::$app->getElements()->canSave($variant, $editor));
        $this->assertDirectoryDoesNotExist(dirname(__DIR__, 2) . '/src/controllers', 'No route writes prices outside the element save');
    }

    /**
     * @param array<string, mixed> $prices
     */
    private function saveWithPrices(Variant $variant, array $prices): void
    {
        $this->post($variant, $prices);
        $this->assertTrue(Craft::$app->getElements()->saveElement($variant), implode(', ', $variant->getFirstErrors()));
    }

    /**
     * Sets prices as the layout element's inputs post them.
     *
     * @param array<string, mixed> $prices
     */
    private function post(Variant $variant, array $prices): void
    {
        /** @var CurrencyPricesBehavior $behavior */
        $behavior = $variant->getBehavior(Purchasables::BEHAVIOR);
        $behavior->currencyPrices = $prices;
    }

    /**
     * @return array<string, float>
     */
    private function freshPrices(Variant $variant): array
    {
        // A fresh service, so nothing memoised in this request answers for the database.
        return (new Prices())->getPrices($variant->id, $this->store->id);
    }

    /**
     * @param string[] $isos
     */
    private function removePaymentCurrencies(array $isos): void
    {
        $paymentCurrencies = Commerce::getInstance()->getPaymentCurrencies();
        foreach ($isos as $iso) {
            $paymentCurrency = $paymentCurrencies->getPaymentCurrencyByIso($iso, $this->store->id);
            $this->assertNotNull($paymentCurrency);
            $this->assertTrue($paymentCurrencies->deletePaymentCurrencyById($paymentCurrency->id));
        }

        $this->resetPaymentCurrencies();
    }

    private function renderElement(Variant $variant): string
    {
        $fresh = Variant::find()->id($variant->id)->status(null)->one();

        return (string)(new CurrencyPricesField())->formHtml($fresh);
    }

    /**
     * @return string[]
     */
    private function nativeFieldClasses(string $type): array
    {
        $layout = new FieldLayout(['type' => $type]);

        return array_map(fn($field) => $field::class, $layout->getAvailableNativeFields());
    }

    /**
     * @param array<string, float> $prices
     * @return array<string, float>
     */
    private function sorted(array $prices): array
    {
        ksort($prices);

        return $prices;
    }
}
