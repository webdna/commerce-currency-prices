<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\twigextensions;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\Plugin as Commerce;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use webdna\commerce\currencyprices\CurrencyPrices;

/**
 * Template filters giving a purchasable's price in a currency for the current customer (BR-15).
 *
 * They price the item as a basket line would (BR-7), through the same event (BR-8), so a page shows what
 * the basket charges. The store currency gives Commerce's own value. An item with no price in the
 * currency gives null, and so does a currency the store does not accept, with a warning.
 *
 * `currencyPrice` and `currencySalePrice` keep their 3.x names and arguments, but now give the number
 * unless `format` is true.
 *
 * @author webdna
 * @since 1.0.0
 */
class CurrencyPricesTwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('currencyPrice', [$this, 'currencyPrice']),
            new TwigFilter('currencyPromotionalPrice', [$this, 'currencyPromotionalPrice']),
            new TwigFilter('currencySalePrice', [$this, 'currencySalePrice']),
        ];
    }

    /**
     * The item's price in a currency (BR-7).
     */
    public function currencyPrice(mixed $purchasable, string $currency, bool $format = false, bool $stripZeros = false): float|string|null
    {
        return $this->output($this->prices($purchasable, $currency)['price'] ?? null, $currency, $format, $stripZeros);
    }

    /**
     * The item's promotional price in a currency, or null when it has none.
     */
    public function currencyPromotionalPrice(mixed $purchasable, string $currency, bool $format = false, bool $stripZeros = false): float|string|null
    {
        return $this->output($this->prices($purchasable, $currency)['promotionalPrice'] ?? null, $currency, $format, $stripZeros);
    }

    /**
     * The item's promotional price in a currency, falling back to its price.
     */
    public function currencySalePrice(mixed $purchasable, string $currency, bool $format = false, bool $stripZeros = false): float|string|null
    {
        $prices = $this->prices($purchasable, $currency);

        return $this->output($prices === null ? null : $prices['promotionalPrice'] ?? $prices['price'], $currency, $format, $stripZeros);
    }

    /**
     * @return array{price: float|null, promotionalPrice: float|null}|null Null when the item has no price
     * in the currency, or the store does not accept it
     */
    private function prices(mixed $purchasable, string $currency): ?array
    {
        if (!$purchasable instanceof Purchasable) {
            return null;
        }

        $currency = strtoupper($currency);
        $storeId = $purchasable->getStoreId();

        if (Commerce::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($currency, $storeId) === null) {
            Craft::warning("A template asked for purchasable $purchasable->id's price in $currency, which store $storeId does not accept.", __METHOD__);

            return null;
        }

        if ($currency === $purchasable->getStore()->getCurrency()?->getCode()) {
            return ['price' => $purchasable->getPrice(), 'promotionalPrice' => $purchasable->getPromotionalPrice()];
        }

        $plugin = CurrencyPrices::getInstance();
        $currencyPrice = $plugin->getPrices()->getPrice((int)$purchasable->id, $storeId, $currency);
        if ($currencyPrice === null) {
            return null;
        }

        $pricing = $plugin->getPricing();
        $basePrice = (float)$purchasable->getBasePrice();
        $promotionalPrice = $purchasable->getPromotionalPrice();

        return $pricing->definePrices(
            $purchasable,
            $currency,
            $currencyPrice,
            $pricing->ratio((float)$purchasable->getPrice(), $basePrice),
            $promotionalPrice === null ? null : $pricing->ratio($promotionalPrice, $basePrice),
        );
    }

    private function output(?float $amount, string $currency, bool $format, bool $stripZeros): float|string|null
    {
        if ($amount === null || !$format) {
            return $amount;
        }

        return Craft::$app->getFormatter()->asCurrency($amount, strtoupper($currency), [], [], $stripZeros);
    }
}
