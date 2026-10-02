<?php

namespace webdna\commerce\currencyprices\tests\integration;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin as Commerce;
use craft\commerce\services\Purchasables as CommercePurchasables;
use craft\elements\User;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayoutTab;
use craft\web\View;
use Twig\Error\SyntaxError;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\events\DefineCurrencyPriceEvent;
use webdna\commerce\currencyprices\fields\CurrencyField;
use webdna\commerce\currencyprices\services\Prices;
use webdna\commerce\currencyprices\tests\Support\CommerceFixtures;
use webdna\commerce\currencyprices\tests\Support\CountingCommand;
use webdna\commerce\currencyprices\twigextensions\CurrencyPricesTwigExtension;
use yii\base\Event;
use yii\log\Logger;

/**
 * Templates and field (task 1.5): the Twig filters show what the basket charges (TS-10, BR-15), and the
 * Currencies field offers the store's payment currencies and keeps a 3.x value (BR-16, TN-15).
 */
class TemplatesTest extends Unit
{
    use CommerceFixtures;

    protected function _before(): void
    {
        $this->setUpGbpStore();
        CurrencyPrices::getInstance()->set('prices', ['class' => Prices::class]);
    }

    protected function _after(): void
    {
        Event::off(CurrencyPrices::class, CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE);
        Craft::$app->getUser()->setIdentity(null);
    }

    public function testThePageShowsWhatTheBasketChargesTheCurrentCustomer(): void
    {
        // TS-10 #1: the 10% trade rule from TS-4.
        $variant = $this->createPricedVariant(100, ['USD' => 120]);
        $trade = $this->createUserInGroup('trade');
        $this->createCatalogPricingRule(10, $trade['group']);

        $this->assertSame('120', $this->render("{{ variant|currencyPrice('USD') }}", $this->asCustomer($variant, null)), 'A guest');

        $tradeVariant = $this->asCustomer($variant, $trade['user']);
        $this->assertSame('108', $this->render("{{ variant|currencyPrice('USD') }}", $tradeVariant));
        $this->assertSame(108.0, $this->linePrice($variant, $trade['user']));
    }

    public function testAHandlerChangesThePageAndTheBasketAlike(): void
    {
        // TS-10 #2, BR-8
        $variant = $this->createPricedVariant(100, ['USD' => 119.50]);
        $seen = null;
        Event::on(CurrencyPrices::class, CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE, function(DefineCurrencyPriceEvent $event) use (&$seen) {
            $seen = $event;
            $event->price = floor($event->price);
        });

        $this->assertSame('119', $this->render("{{ variant|currencyPrice('USD') }}", $variant));
        $this->assertNull($seen->order, 'No order outside a basket');
        $this->assertNull($seen->lineItem);
        $this->assertSame(119.0, $this->linePrice($variant, null));
    }

    public function testACurrencyTheStoreDoesNotAcceptGivesNullAndAWarning(): void
    {
        // TS-10 #3
        $variant = $this->createPricedVariant(100, ['USD' => 120]);
        $logger = Craft::getLogger();
        Craft::setLogger(new Logger(['flushInterval' => PHP_INT_MAX]));

        try {
            $extension = $this->extension();
            $this->assertNull($extension->currencyPrice($variant, 'JPY'));
            $this->assertNull($extension->currencyPromotionalPrice($variant, 'JPY'));
            $this->assertNull($extension->currencySalePrice($variant, 'JPY'));
            $messages = Craft::getLogger()->messages;
        } finally {
            Craft::setLogger($logger);
        }

        $logged = array_filter($messages, fn($message) => $message[1] === Logger::LEVEL_WARNING && is_string($message[0]) && str_contains($message[0], 'in JPY'));
        $this->assertCount(3, $logged);
    }

    public function testACurrencyWithNoPriceGivesNull(): void
    {
        $variant = $this->createPricedVariant(100, ['USD' => 120]);
        $extension = $this->extension();

        $this->assertNull($extension->currencyPrice($variant, 'EUR'));
        $this->assertNull($extension->currencyPromotionalPrice($variant, 'EUR'));
        $this->assertNull($extension->currencySalePrice($variant, 'EUR'));
        $this->assertSame('', $this->render("{{ variant|currencyPrice('EUR') }}", $variant));
    }

    public function testTheStoreCurrencyGivesCommercesOwnValues(): void
    {
        $variant = $this->createPricedVariant(100, ['USD' => 120]);
        $variant->basePromotionalPrice = 80;
        $this->assertTrue(Craft::$app->getElements()->saveElement($variant));
        $variant = $this->asCustomer($variant, null);
        $extension = $this->extension();

        $this->assertSame($variant->getPrice(), $extension->currencyPrice($variant, 'GBP'));
        $this->assertSame(80.0, $extension->currencyPromotionalPrice($variant, 'gbp'));
        $this->assertSame($variant->getSalePrice(), $extension->currencySalePrice($variant, 'GBP'));
    }

    public function testPromotionalAndSalePricesFollowTheRatio(): void
    {
        $variant = $this->createPricedVariant(100, ['USD' => 120]);
        $extension = $this->extension();

        $this->assertNull($extension->currencyPromotionalPrice($variant, 'USD'));
        $this->assertSame(120.0, $extension->currencySalePrice($variant, 'USD'), 'Falls back to the price');

        // TS-4 #3: a base promotional price of £80.
        $variant->basePromotionalPrice = 80;
        $this->assertTrue(Craft::$app->getElements()->saveElement($variant));
        $variant = $this->asCustomer($variant, null);

        $this->assertSame(120.0, $extension->currencyPrice($variant, 'USD'));
        $this->assertSame(96.0, $extension->currencyPromotionalPrice($variant, 'USD'));
        $this->assertSame(96.0, $extension->currencySalePrice($variant, 'USD'));
    }

    public function testThe3xFormatArgumentStillFormats(): void
    {
        $variant = $this->createPricedVariant(100, ['USD' => 120]);

        $this->assertSame(
            Craft::$app->getFormatter()->asCurrency(120, 'USD'),
            $this->render("{{ variant|currencyPrice('USD', true) }}", $variant),
        );
        $this->assertSame(
            Craft::$app->getFormatter()->asCurrency(120, 'USD', [], [], true),
            $this->render("{{ variant|currencySalePrice('USD', true, true) }}", $variant),
        );
    }

    public function testAnItemsPricesAreReadOncePerRequest(): void
    {
        // BR-14
        $variant = $this->createPricedVariant(100, ['USD' => 120, 'EUR' => 110]);
        CurrencyPrices::getInstance()->set('prices', ['class' => Prices::class]);
        $template = "{{ variant|currencyPrice('USD') }} {{ variant|currencySalePrice('USD') }} {{ variant|currencyPromotionalPrice('EUR') }} {{ variant|currencyPrice('EUR') }}";

        $queries = CountingCommand::count(fn() => $this->render($template . $template, $variant), 'currencyprices_prices');

        $this->assertSame(1, $queries);
    }

    public function testThe3xAddonFiltersAreGone(): void
    {
        // TN-16
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unknown "currencyAddonDiscountPrice" filter');

        $this->render("{{ 1|currencyAddonDiscountPrice('USD') }}", null);
    }

    public function testTheCurrenciesFieldOffersTheStoresPaymentCurrencies(): void
    {
        // BR-16
        $field = new CurrencyField();

        $this->assertEqualsCanonicalizing(['GBP', 'USD', 'EUR'], array_keys($field->getCurrencyOptions()));
    }

    public function testACurrenciesValueSavedUnder3xIsShownAndSelectable(): void
    {
        // TN-15: 3.x stored the ISO code as a plain string, which is what the field stores now.
        $field = $this->createCurrencyFieldOnUsers();
        $user = new User(['username' => 'customer' . uniqid(), 'email' => 'customer' . uniqid() . '@example.com']);
        $user->setFieldValue($field->handle, 'USD');
        $this->assertTrue(Craft::$app->getElements()->saveElement($user, false));

        $user = User::find()->id($user->id)->one();
        $this->assertSame('USD', $user->getFieldValue($field->handle));

        $html = $this->inputHtml($field, $user);
        $this->assertMatchesRegularExpression('/<option value="USD" selected>/', $html);
        foreach (['GBP', 'EUR'] as $iso) {
            $this->assertStringContainsString('<option value="' . $iso . '"', $html);
        }

        // A value the store no longer accepts is still shown, so a save does not drop it.
        $this->assertArrayHasKey('AUD', $field->getCurrencyOptions('AUD', $user));
    }

    private function createCurrencyFieldOnUsers(): CurrencyField
    {
        $fields = Craft::$app->getFields();
        $field = new CurrencyField(['name' => 'Currency', 'handle' => 'currency' . uniqid()]);
        $this->assertTrue($fields->saveField($field), implode(', ', $field->getFirstErrors()));

        $layout = $fields->getLayoutByType(User::class);
        $tab = new FieldLayoutTab(['layout' => $layout, 'name' => 'Content']);
        $tab->setElements([new CustomField($field)]);
        $layout->setTabs([...$layout->getTabs(), $tab]);
        $this->assertTrue(Craft::$app->getUsers()->saveLayout($layout));

        return $field;
    }

    private function inputHtml(CurrencyField $field, User $user): string
    {
        $view = Craft::$app->getView();
        $mode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        try {
            return $field->getInputHtml($user->getFieldValue($field->handle), $user);
        } finally {
            $view->setTemplateMode($mode);
        }
    }

    /**
     * The variant as a page would load it for this customer: Commerce gives each customer their own
     * catalog price when the element is queried.
     */
    private function asCustomer(Variant $variant, ?User $customer): Variant
    {
        Craft::$app->getUser()->setIdentity($customer);

        return Variant::find()->id($variant->id)->status(null)->one();
    }

    private function linePrice(Variant $variant, ?User $customer): float
    {
        // Commerce memoises a purchasable by id whoever the customer is.
        Commerce::getInstance()->set('purchasables', ['class' => CommercePurchasables::class]);
        $order = $this->createCart('USD', $customer);
        $this->addToCart($order, $variant);

        return $order->getLineItems()[0]->getPrice();
    }

    private function render(string $template, ?Variant $variant): string
    {
        return trim(Craft::$app->getView()->renderString($template, ['variant' => $variant]));
    }

    private function extension(): CurrencyPricesTwigExtension
    {
        return new CurrencyPricesTwigExtension();
    }
}
