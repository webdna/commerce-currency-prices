<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\db\Query;
use craft\helpers\Db;
use webdna\commerce\currencyprices\records\PriceRecord;
use yii\base\Component;
use yii\base\InvalidArgumentException;

/**
 * Reads and writes the typed currency prices (spec §4).
 *
 * Prices are keyed by purchasable, store and ISO code (BR-1). An absent row means "no price" and is
 * returned as null, never 0 (B3). An order's prices are read in one query (BR-14), and one item's prices
 * once per request.
 *
 * @author webdna
 * @since 4.0.0
 */
class Prices extends Component
{
    /**
     * @var array<string, array<string, float>> One item's prices in one store, keyed "purchasableId:storeId".
     */
    private array $_prices = [];

    /**
     * The currencies a purchasable in this store can be priced in: the store's payment currencies other
     * than the store currency (BR-1).
     *
     * @return string[] ISO codes
     */
    public function getEligibleCurrencies(int $storeId): array
    {
        $store = Commerce::getInstance()->getStores()->getStoreById($storeId);
        if ($store === null) {
            return [];
        }

        $storeCurrency = $store->getCurrency()?->getCode();

        return Commerce::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies($storeId)
            ->map(fn($paymentCurrency) => (string)$paymentCurrency->iso)
            ->filter(fn(string $iso) => $iso !== $storeCurrency)
            ->values()
            ->all();
    }

    /**
     * One item's prices in one store, read once per request.
     *
     * @return array<string, float> Price by ISO code. A currency with no price is absent.
     */
    public function getPrices(int $purchasableId, int $storeId): array
    {
        $key = $purchasableId . ':' . $storeId;

        if (!isset($this->_prices[$key])) {
            $this->_prices[$key] = array_map('floatval', $this->_createQuery()
                ->select(['price', 'currency'])
                ->where(['purchasableId' => $purchasableId, 'storeId' => $storeId])
                ->indexBy('currency')
                ->column());
        }

        return $this->_prices[$key];
    }

    public function getPrice(int $purchasableId, int $storeId, string $currency): ?float
    {
        return $this->getPrices($purchasableId, $storeId)[strtoupper($currency)] ?? null;
    }

    /**
     * Many purchasables' prices in one store and currency, in one query (BR-14).
     *
     * @param int[] $purchasableIds
     * @return array<int, float> Price by purchasable id. A purchasable with no price is absent.
     */
    public function getPricesForPurchasables(array $purchasableIds, int $storeId, string $currency): array
    {
        $purchasableIds = array_values(array_unique(array_filter(array_map('intval', $purchasableIds))));
        if (empty($purchasableIds)) {
            return [];
        }

        $prices = $this->_createQuery()
            ->select(['price', 'purchasableId'])
            ->where([
                'purchasableId' => $purchasableIds,
                'storeId' => $storeId,
                'currency' => strtoupper($currency),
            ])
            ->indexBy('purchasableId')
            ->column();

        $result = [];
        foreach ($prices as $purchasableId => $price) {
            $result[(int)$purchasableId] = (float)$price;
        }

        return $result;
    }

    /**
     * The prices of an order's purchasables in its store and payment currency, in one query (BR-14).
     *
     * @return array<int, float> Price by purchasable id. A purchasable with no price is absent.
     */
    public function getPricesForOrder(Order $order): array
    {
        $currency = $order->getPaymentCurrency();
        if (!$order->storeId || !$currency) {
            return [];
        }

        $purchasableIds = array_map(fn($lineItem) => $lineItem->purchasableId, $order->getLineItems());

        return $this->getPricesForPurchasables($purchasableIds, $order->storeId, $currency);
    }

    /**
     * Writes an item's prices in one store. Each given currency is set, or deleted when its value is null
     * or blank (BR-3). Currencies not given are left untouched. The unique key makes each write a single
     * upsert, so simultaneous saves leave one row and the last one wins (TN-14).
     *
     * @param array<string, float|int|string|null> $prices Price by ISO code
     * @throws InvalidArgumentException if a currency is not one of the store's eligible currencies (BR-1),
     * or a price is negative or not a number
     */
    public function savePrices(int $purchasableId, int $storeId, array $prices): void
    {
        $eligible = $this->getEligibleCurrencies($storeId);
        $set = [];
        $delete = [];

        foreach ($prices as $currency => $price) {
            $currency = strtoupper((string)$currency);

            if (!in_array($currency, $eligible, true)) {
                throw new InvalidArgumentException("$currency is not a payment currency of store $storeId other than its own currency.");
            }

            if ($price === null || $price === '') {
                $delete[] = $currency;
                continue;
            }

            if (!is_numeric($price) || $price < 0) {
                throw new InvalidArgumentException("Invalid price for $currency: $price");
            }

            $set[$currency] = $price;
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            foreach ($set as $currency => $price) {
                Db::upsert(PriceRecord::tableName(), [
                    'purchasableId' => $purchasableId,
                    'storeId' => $storeId,
                    'currency' => $currency,
                    'price' => $price,
                ], ['price' => $price]);
            }

            if (!empty($delete)) {
                Db::delete(PriceRecord::tableName(), [
                    'purchasableId' => $purchasableId,
                    'storeId' => $storeId,
                    'currency' => $delete,
                ]);
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->_clearCache($purchasableId);
    }

    /**
     * Replaces one element's rows, in every store, with a copy of another's: for a duplicate, a new draft,
     * an applied draft and a revision (BR-5).
     */
    public function copyPrices(int $fromPurchasableId, int $toPurchasableId): void
    {
        if ($fromPurchasableId === $toPurchasableId) {
            return;
        }

        $rows = $this->_createQuery()
            ->select(['storeId', 'currency', 'price'])
            ->where(['purchasableId' => $fromPurchasableId])
            ->all();

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            Db::delete(PriceRecord::tableName(), ['purchasableId' => $toPurchasableId]);

            if (!empty($rows)) {
                Db::batchInsert(
                    PriceRecord::tableName(),
                    ['purchasableId', 'storeId', 'currency', 'price'],
                    array_map(fn(array $row) => [$toPurchasableId, $row['storeId'], $row['currency'], $row['price']], $rows),
                );
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->_clearCache($toPurchasableId);
    }

    private function _createQuery(): Query
    {
        return (new Query())->from(PriceRecord::tableName());
    }

    private function _clearCache(int $purchasableId): void
    {
        foreach (array_keys($this->_prices) as $key) {
            if (str_starts_with($key, $purchasableId . ':')) {
                unset($this->_prices[$key]);
            }
        }
    }
}
