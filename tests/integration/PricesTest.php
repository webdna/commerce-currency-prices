<?php

namespace webdna\commerce\currencyprices\tests\integration;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\models\LineItem;
use craft\commerce\models\Store;
use craft\commerce\Plugin as Commerce;
use craft\db\Query;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\records\PriceRecord;
use webdna\commerce\currencyprices\services\Prices;
use webdna\commerce\currencyprices\tests\Support\CommerceFixtures;
use webdna\commerce\currencyprices\tests\Support\CountingCommand;
use yii\base\InvalidArgumentException;
use yii\db\IntegrityException;

/**
 * Price storage (task 1.1): one row per purchasable × store × payment currency (§4, BR-1), read in one
 * query per order and once per item per request (BR-14), removed with its variant (TN-2), separate per
 * store (TN-6), and one row however many saves land on it (TN-14).
 *
 * The primary store sells in GBP with USD and EUR payment currencies, as in the spec's examples.
 */
class PricesTest extends Unit
{
    use CommerceFixtures;

    private Prices $prices;

    protected function _before(): void
    {
        $this->prices = CurrencyPrices::getInstance()->getPrices();
        $this->setUpGbpStore();
    }

    public function testOnlyTheStoresOtherPaymentCurrenciesArePriceable(): void
    {
        $eligible = $this->prices->getEligibleCurrencies($this->store->id);
        sort($eligible);

        $this->assertSame(['EUR', 'USD'], $eligible);
    }

    public function testThePricesOfOneItemInOneStoreAreSavedAndRead(): void
    {
        $variant = $this->createVariant();

        $this->prices->savePrices($variant->id, $this->store->id, ['USD' => '12.50', 'eur' => 0]);

        $this->assertSame(['EUR' => 0.0, 'USD' => 12.5], $this->sorted($this->prices->getPrices($variant->id, $this->store->id)));
        $this->assertSame(12.5, $this->prices->getPrice($variant->id, $this->store->id, 'usd'));
        $this->assertSame(0.0, $this->prices->getPrice($variant->id, $this->store->id, 'EUR'), '0 is a price, not an absent one');
    }

    public function testABlankPriceDeletesTheRowAndAnAbsentPriceIsNull(): void
    {
        $variant = $this->createVariant();
        $this->prices->savePrices($variant->id, $this->store->id, ['USD' => 10, 'EUR' => 9]);

        $this->prices->savePrices($variant->id, $this->store->id, ['USD' => '']);

        $this->assertNull($this->prices->getPrice($variant->id, $this->store->id, 'USD'));
        $this->assertSame(9.0, $this->prices->getPrice($variant->id, $this->store->id, 'EUR'), 'A currency not given is untouched');
        $this->assertSame(1, $this->countRows($variant->id));
    }

    public function testTheStoreCurrencyCannotBePriced(): void
    {
        $variant = $this->createVariant();

        $this->expectException(InvalidArgumentException::class);
        $this->prices->savePrices($variant->id, $this->store->id, ['GBP' => 10]);
    }

    public function testACurrencyTheStoreDoesNotAcceptCannotBePriced(): void
    {
        $variant = $this->createVariant();

        $this->expectException(InvalidArgumentException::class);
        $this->prices->savePrices($variant->id, $this->store->id, ['AUD' => 10]);
    }

    public function testANegativePriceIsRefused(): void
    {
        $variant = $this->createVariant();

        $this->expectException(InvalidArgumentException::class);
        $this->prices->savePrices($variant->id, $this->store->id, ['USD' => -1]);
    }

    public function testAnOrdersPricesAreReadInOneQuery(): void
    {
        $priced = [$this->createVariant(), $this->createVariant(), $this->createVariant()];
        $unpriced = $this->createVariant();
        foreach ($priced as $i => $variant) {
            $this->prices->savePrices($variant->id, $this->store->id, ['USD' => 10 + $i, 'EUR' => 20 + $i]);
        }

        $order = new Order(['storeId' => $this->store->id]);
        $order->setPaymentCurrency('USD');
        $order->setLineItems(array_map(
            fn(Variant $variant) => new LineItem(['purchasableId' => $variant->id]),
            [...$priced, $unpriced],
        ));

        $prices = null;
        $queries = CountingCommand::count(function() use ($order, &$prices) {
            $prices = $this->prices->getPricesForOrder($order);
        });

        $this->assertSame(1, $queries);
        $this->assertSame([
            $priced[0]->id => 10.0,
            $priced[1]->id => 11.0,
            $priced[2]->id => 12.0,
        ], $prices, 'Only USD, and the unpriced line is absent');
    }

    public function testOneItemsPricesAreReadOncePerRequest(): void
    {
        $variant = $this->createVariant();
        $this->prices->savePrices($variant->id, $this->store->id, ['USD' => 10, 'EUR' => 9]);

        $queries = CountingCommand::count(function() use ($variant) {
            $this->prices->getPrice($variant->id, $this->store->id, 'USD');
            $this->prices->getPrice($variant->id, $this->store->id, 'EUR');
            $this->prices->getPrices($variant->id, $this->store->id);
        });

        $this->assertSame(1, $queries);
    }

    public function testASaveIsReadBackInTheSameRequest(): void
    {
        $variant = $this->createVariant();
        $this->prices->savePrices($variant->id, $this->store->id, ['USD' => 10]);
        $this->prices->getPrices($variant->id, $this->store->id);

        $this->prices->savePrices($variant->id, $this->store->id, ['USD' => 11]);

        $this->assertSame(11.0, $this->prices->getPrice($variant->id, $this->store->id, 'USD'));
    }

    /**
     * TN-2.
     */
    public function testDeletingAPricedVariantRemovesItsRows(): void
    {
        $variant = $this->createVariant();
        $this->prices->savePrices($variant->id, $this->store->id, ['USD' => 10, 'EUR' => 9]);
        $this->assertSame(2, $this->countRows($variant->id));

        $this->assertTrue(Craft::$app->getElements()->deleteElement($variant, true));

        $this->assertSame(0, $this->countRows($variant->id));
    }

    /**
     * TN-6.
     */
    public function testTwoStoresUsdPricesAreSeparate(): void
    {
        $storeB = new Store(['name' => 'Store B', 'handle' => 'storeB']);
        $storeB->setCurrency('EUR');
        $this->assertTrue(Commerce::getInstance()->getStores()->saveStore($storeB));
        $this->addPaymentCurrencies($storeB->id, ['USD']);

        $variant = $this->createVariant();
        $this->prices->savePrices($variant->id, $this->store->id, ['USD' => 10]);
        $this->prices->savePrices($variant->id, $storeB->id, ['USD' => 20]);

        $this->prices->savePrices($variant->id, $this->store->id, ['USD' => 15]);

        $this->assertSame(15.0, $this->prices->getPrice($variant->id, $this->store->id, 'USD'));
        $this->assertSame(20.0, $this->prices->getPrice($variant->id, $storeB->id, 'USD'), "Store A's save leaves store B's USD price");
        $this->assertSame([$variant->id => 20.0], $this->prices->getPricesForPurchasables([$variant->id], $storeB->id, 'USD'));
        $this->assertSame(2, $this->countRows($variant->id));
    }

    /**
     * TN-14. Each save is one upsert on the unique key, so two editors' saves leave one row holding the
     * last value, and the table itself refuses a second row.
     */
    public function testTheLastOfTwoSavesWinsWithNoDuplicateRow(): void
    {
        $variant = $this->createVariant();
        $otherEditor = new Prices();

        $this->prices->savePrices($variant->id, $this->store->id, ['USD' => 10]);
        $otherEditor->savePrices($variant->id, $this->store->id, ['USD' => 12]);

        $this->assertSame(1, $this->countRows($variant->id));
        $this->assertSame(12.0, (new Prices())->getPrice($variant->id, $this->store->id, 'USD'));

        $this->expectException(IntegrityException::class);
        Craft::$app->getDb()->createCommand()->insert(PriceRecord::tableName(), [
            'purchasableId' => $variant->id,
            'storeId' => $this->store->id,
            'currency' => 'USD',
            'price' => 13,
        ])->execute();
    }

    public function testCopyingReplacesTheTargetsRowsInEveryStore(): void
    {
        $storeB = new Store(['name' => 'Store B', 'handle' => 'storeB']);
        $storeB->setCurrency('EUR');
        $this->assertTrue(Commerce::getInstance()->getStores()->saveStore($storeB));
        $this->addPaymentCurrencies($storeB->id, ['USD']);

        $source = $this->createVariant();
        $target = $this->createVariant();
        $this->prices->savePrices($source->id, $this->store->id, ['USD' => 10]);
        $this->prices->savePrices($source->id, $storeB->id, ['USD' => 20]);
        $this->prices->savePrices($target->id, $this->store->id, ['USD' => 99, 'EUR' => 98]);
        $this->prices->getPrices($target->id, $this->store->id);

        $this->prices->copyPrices($source->id, $target->id);

        $this->assertSame(['USD' => 10.0], $this->prices->getPrices($target->id, $this->store->id));
        $this->assertSame(['USD' => 20.0], $this->prices->getPrices($target->id, $storeB->id));
        $this->assertSame(['USD' => 10.0], $this->prices->getPrices($source->id, $this->store->id), 'The source is untouched');
    }

    private function countRows(int $purchasableId): int
    {
        return (int)(new Query())->from(PriceRecord::tableName())->where(['purchasableId' => $purchasableId])->count();
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
