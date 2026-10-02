<?php

namespace webdna\commerce\currencyprices\tests\Support;

use Craft;
use craft\base\Element;
use craft\commerce\Plugin as Commerce;
use craft\digitalproducts\elements\Product as DigitalProduct;
use craft\digitalproducts\models\ProductType as DigitalProductType;
use craft\digitalproducts\models\ProductTypeSite as DigitalProductTypeSite;
use craft\digitalproducts\Plugin as DigitalProducts;
use DateTime;
use verbb\events\elements\Event;
use verbb\events\elements\Session;
use verbb\events\elements\Ticket;
use verbb\events\elements\TicketType;
use verbb\events\Events;
use verbb\events\models\EventType;
use verbb\events\models\EventTypeSite;
use webdna\commerce\currencyprices\behaviors\CurrencyPricesBehavior;
use webdna\commerce\currencyprices\services\Purchasables;

/**
 * Digital products and Events tickets for the `integrations` suite, which installs both plugins.
 * Use with {@see CommerceFixtures}.
 */
trait OtherPurchasablesFixtures
{
    private function getDigitalProductType(): DigitalProductType
    {
        $productTypes = DigitalProducts::getInstance()->getProductTypes();
        $productType = $productTypes->getProductTypeByHandle('downloads');

        if ($productType === null) {
            $productType = new DigitalProductType(['name' => 'Downloads', 'handle' => 'downloads']);

            $siteSettings = [];
            foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
                $siteSettings[$siteId] = new DigitalProductTypeSite(['siteId' => $siteId, 'hasUrls' => false, 'uriFormat' => null, 'template' => null]);
            }
            $productType->setSiteSettings($siteSettings);

            $this->assertTrue($productTypes->saveProductType($productType), implode(', ', $productType->getFirstErrors()));
        }

        return $productType;
    }

    /**
     * A live digital product at a price in the store currency, with its currency prices posted and saved
     * as the layout element posts them.
     *
     * @param array<string, float|int|string|null> $prices
     */
    private function createDigitalProduct(float $price, array $prices = []): DigitalProduct
    {
        $product = new DigitalProduct([
            'typeId' => $this->getDigitalProductType()->id,
            'title' => 'Ebook ' . uniqid(),
            'sku' => 'DP-' . uniqid(),
            'price' => $price,
            'taxCategoryId' => Commerce::getInstance()->getTaxCategories()->getDefaultTaxCategory()->id,
            'postDate' => new DateTime('-1 minute'),
        ]);
        $this->postPrices($product, $prices);
        $this->assertTrue(Craft::$app->getElements()->saveElement($product), implode(', ', $product->getFirstErrors()));

        return $product;
    }

    private function getEventType(): EventType
    {
        $eventTypes = Events::$plugin->getEventTypes();
        $eventType = $eventTypes->getEventTypeByHandle('concerts');

        if ($eventType === null) {
            $eventType = new EventType(['name' => 'Concerts', 'handle' => 'concerts']);

            $siteSettings = [];
            foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
                $siteSettings[$siteId] = new EventTypeSite(['siteId' => $siteId, 'hasUrls' => false, 'enabledByDefault' => true, 'uriFormat' => null, 'template' => null]);
            }
            $eventType->setSiteSettings($siteSettings);

            $this->assertTrue($eventTypes->saveEventType($eventType), implode(', ', $eventType->getFirstErrors()));
        }

        return $eventType;
    }

    /**
     * A live event with sessions and one ticket type at a price in the store currency, its currency prices
     * posted on the ticket type, and its tickets generated as Events does from the CP.
     *
     * @param array<string, float|int|string|null> $prices
     * @return array{event: Event, ticketType: TicketType, tickets: Ticket[]}
     */
    private function createEventWithTickets(float $price, array $prices = [], int $sessions = 1): array
    {
        $elements = Craft::$app->getElements();

        $event = new Event(['typeId' => $this->getEventType()->id, 'title' => 'Concert ' . uniqid(), 'enabled' => true]);
        $this->assertTrue($elements->saveElement($event), implode(', ', $event->getFirstErrors()));

        for ($i = 1; $i <= $sessions; $i++) {
            $session = new Session(['startDate' => new DateTime("+$i week"), 'endDate' => new DateTime("+$i week +2 hours"), 'enabled' => true]);
            $session->setPrimaryOwner($event);
            $session->setOwner($event);
            $this->assertTrue($elements->saveElement($session), implode(', ', $session->getFirstErrors()));
        }

        $ticketType = new TicketType(['title' => 'General admission', 'price' => $price, 'enabled' => true]);
        $ticketType->setPrimaryOwner($event);
        $ticketType->setOwner($event);
        $this->postPrices($ticketType, $prices);
        $this->assertTrue($elements->saveElement($ticketType), implode(', ', $ticketType->getFirstErrors()));

        $event->updateTickets();

        /** @var Ticket[] $tickets */
        $tickets = Ticket::find()->eventId($event->id)->typeId($ticketType->id)->status(null)->all();
        $this->assertCount($sessions, $tickets, 'One ticket per session');

        return ['event' => $event, 'ticketType' => $ticketType, 'tickets' => $tickets];
    }

    /**
     * Sets prices as the layout element's inputs post them.
     *
     * @param array<string, float|int|string|null> $prices
     */
    private function postPrices(Element $element, array $prices): void
    {
        if (empty($prices)) {
            return;
        }

        /** @var CurrencyPricesBehavior|null $behavior */
        $behavior = $element->getBehavior(Purchasables::BEHAVIOR);
        $this->assertNotNull($behavior, $element::class . ' carries the currency prices behavior');
        $behavior->currencyPrices = $prices;
    }
}
