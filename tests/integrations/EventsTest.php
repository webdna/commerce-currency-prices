<?php

namespace webdna\commerce\currencyprices\tests\integrations;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin as Commerce;
use craft\commerce\services\Purchasables as CommercePurchasables;
use craft\models\FieldLayout;
use verbb\events\elements\Event;
use verbb\events\elements\Session;
use verbb\events\elements\Ticket;
use verbb\events\elements\TicketType;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\fieldlayoutelements\CurrencyPricesField;
use webdna\commerce\currencyprices\services\Prices;
use webdna\commerce\currencyprices\tests\Support\CommerceFixtures;
use webdna\commerce\currencyprices\tests\Support\CountingCommand;
use webdna\commerce\currencyprices\tests\Support\OtherPurchasablesFixtures;

/**
 * Events 3.1 (task 2.2). A ticket is generated per session and ticket type and has no layout of its own;
 * its price is the ticket type's. So the Currency prices element sits on the event type's ticket-type
 * layout, the ticket type holds the prices, and every ticket of that type is priced from them (TS-6 #1–#2,
 * BR-2, Appendix A #6).
 */
class EventsTest extends Unit
{
    use CommerceFixtures;
    use OtherPurchasablesFixtures;

    protected function _before(): void
    {
        $this->setUpGbpStore();
        Commerce::getInstance()->set('purchasables', ['class' => CommercePurchasables::class]);
    }

    public function testTheElementIsOfferedOnTheTicketTypeLayoutButNotTheSessionOrEventLayouts(): void
    {
        $this->assertContains(CurrencyPricesField::class, $this->nativeFieldClasses(TicketType::class));
        $this->assertNotContains(CurrencyPricesField::class, $this->nativeFieldClasses(Session::class));
        $this->assertNotContains(CurrencyPricesField::class, $this->nativeFieldClasses(Event::class));
    }

    public function testPricesPostedOnATicketTypeAreSavedForItAndShownInItsInputs(): void
    {
        ['ticketType' => $ticketType] = $this->createEventWithTickets(20, ['USD' => '25', 'EUR' => '']);

        $this->assertSame(['USD' => 25.0], (new Prices())->getPrices($ticketType->id, $this->store->id));

        $fresh = TicketType::find()->id($ticketType->id)->status(null)->one();
        $html = (string)(new CurrencyPricesField())->formHtml($fresh);
        $this->assertStringContainsString('data-currency-price="USD"', $html);
        $this->assertStringContainsString('No price — not for sale in EUR', $html);
    }

    public function testAUsdBasketChargesEveryTicketOfTheTypeItsUsdPrice(): void
    {
        ['tickets' => $tickets] = $this->createEventWithTickets(20, ['USD' => 25], sessions: 2);
        $order = $this->createCart('USD');

        $this->addToCart($order, $tickets[0], 2);
        $this->addToCart($order, $tickets[1]);

        $this->assertSame([25.0, 25.0], array_map(fn(LineItem $lineItem) => $lineItem->getPrice(), $order->getLineItems()));
        $this->assertSame(75.0, $order->getTotal());
        $this->assertSame('USD', $order->currency);
    }

    public function testATicketWhoseTypeHasNoPriceInTheBasketsCurrencyCannotBeAdded(): void
    {
        ['tickets' => $tickets] = $this->createEventWithTickets(20, ['USD' => 25]);
        $order = $this->createCart('EUR');

        $this->assertFalse(Commerce::getInstance()->getPurchasables()->isPurchasableAvailable($tickets[0], $order));
        $this->assertTrue(Commerce::getInstance()->getPurchasables()->isPurchasableAvailable($tickets[0], $this->createCart('USD')));
    }

    public function testATicketIsPricedInTemplatesFromItsType(): void
    {
        ['tickets' => $tickets] = $this->createEventWithTickets(20, ['USD' => 25]);

        $this->assertSame('25', Craft::$app->getView()->renderString('{{ ticket|currencyPrice("USD") }}', ['ticket' => $tickets[0]]));
    }

    public function testADuplicatedEventsTicketTypeCarriesItsPrices(): void
    {
        ['event' => $event] = $this->createEventWithTickets(20, ['USD' => 25]);

        $copy = Craft::$app->getElements()->duplicateElement($event);

        $copiedType = TicketType::find()->ownerId($copy->id)->status(null)->one();
        $this->assertNotNull($copiedType);
        $this->assertSame(['USD' => 25.0], (new Prices())->getPrices($copiedType->id, $this->store->id));
    }

    public function testARecalculationReadsABasketOfTicketsDigitalProductsAndVariantsInOneQuery(): void
    {
        ['tickets' => $tickets] = $this->createEventWithTickets(20, ['USD' => 25]);
        $product = $this->createDigitalProduct(10, ['USD' => 12.5]);
        $variant = $this->createPricedVariant(100, ['USD' => 120]);
        $order = $this->createCart('USD');
        $this->addToCart($order, $tickets[0]);
        $this->addToCart($order, $product);
        $this->addToCart($order, $variant);

        $queries = CountingCommand::count(fn() => $order->recalculate(), 'currencyprices_prices');

        $this->assertSame(1, $queries);
        $this->assertEqualsCanonicalizing([25.0, 12.5, 120.0], array_map(fn(LineItem $lineItem) => $lineItem->getPrice(), $order->getLineItems()));
    }

    public function testTicketsAreNotPricedFromATicketTypeWithTheTicketsOwnId(): void
    {
        ['tickets' => $tickets, 'ticketType' => $ticketType] = $this->createEventWithTickets(20, ['USD' => 25]);

        $this->assertInstanceOf(Ticket::class, $tickets[0]);
        $this->assertSame($ticketType->id, CurrencyPrices::getInstance()->getPurchasables()->getPriceOwnerId($tickets[0]));
        $this->assertSame([], (new Prices())->getPrices($tickets[0]->id, $this->store->id), 'Nothing is stored against the generated ticket');
    }

    /**
     * @return string[]
     */
    private function nativeFieldClasses(string $type): array
    {
        $layout = new FieldLayout(['type' => $type]);

        return array_map(fn($field) => $field::class, $layout->getAvailableNativeFields());
    }
}
