<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\console\controllers;

use Craft;
use craft\base\ElementInterface;
use craft\base\NestedElementInterface;
use craft\commerce\base\Purchasable;
use craft\commerce\models\Store;
use craft\commerce\Plugin as Commerce;
use craft\console\Controller;
use craft\helpers\Db;
use craft\models\Site;
use webdna\commerce\currencyprices\CurrencyPrices;
use yii\console\ExitCode;

/**
 * Copies a shop's own number field into currency prices (BR-21).
 *
 * `php craft commerce-currency-prices/import --field=priceUSD --currency=USD [--store=<handle>] [--apply] [--include-zero]`
 *
 * Each purchasable's value is read from the field on the purchasable, or else on its owner (a variant's
 * product). Without `--apply` nothing is written. An empty value is skipped, and so is a 0 unless
 * `--include-zero` is given, because a hand-rolled field that defaults to 0 usually means "unpriced".
 *
 * @author webdna
 * @since 4.0.0
 */
class ImportController extends Controller
{
    public const COUNT_WRITTEN = 'written';
    public const COUNT_UNCHANGED = 'unchanged';
    public const COUNT_OVERWRITTEN = 'overwritten';
    public const COUNT_EMPTY = 'skippedEmpty';
    public const COUNT_ZERO = 'skippedZero';
    public const COUNT_FAILED = 'failed';

    private const LABELS = [
        self::COUNT_WRITTEN => 'Written',
        self::COUNT_UNCHANGED => 'Unchanged',
        self::COUNT_OVERWRITTEN => 'Overwritten',
        self::COUNT_EMPTY => 'Skipped as empty',
        self::COUNT_ZERO => 'Skipped as zero',
        self::COUNT_FAILED => 'Failed',
    ];

    /**
     * @var string|null The handle of the number field holding the prices
     */
    public ?string $field = null;

    /**
     * @var string|null The ISO code of the currency the field's prices are in
     */
    public ?string $currency = null;

    /**
     * @var string|null The handle of the store to price. Defaults to the primary store.
     */
    public ?string $store = null;

    /**
     * @var bool Whether to write the prices. Without it, the counts are printed and nothing changes.
     */
    public bool $apply = false;

    /**
     * @var bool Whether a 0 is imported as a price, rather than skipped
     */
    public bool $includeZero = false;

    /**
     * @var array<string, int> The last run's counts, keyed by the COUNT_* constants
     */
    public array $counts = [];

    public function options($actionID): array
    {
        return [...parent::options($actionID), 'field', 'currency', 'store', 'apply', 'includeZero'];
    }

    /**
     * Imports a number field into currency prices for one store.
     */
    public function actionIndex(): int
    {
        $this->counts = array_fill_keys(array_keys(self::LABELS), 0);

        // Everything is checked before an item is read (BR-21).
        if (!$this->field || !$this->currency) {
            $this->stderr("Both --field and --currency are required.\n");
            return ExitCode::USAGE;
        }

        $store = $this->store
            ? Commerce::getInstance()->getStores()->getStoreByHandle($this->store)
            : Commerce::getInstance()->getStores()->getPrimaryStore();
        if ($store === null) {
            $this->stderr("No store with the handle \"$this->store\".\n");
            return ExitCode::USAGE;
        }

        $currency = strtoupper($this->currency);
        if (!in_array($currency, CurrencyPrices::getInstance()->getPrices()->getEligibleCurrencies($store->id), true)) {
            $this->stderr($currency === $store->getCurrency()?->getCode()
                ? "$currency is the store currency of \"$store->handle\", whose prices are Commerce's own.\n"
                : "The store \"$store->handle\" does not accept $currency as a payment currency.\n");
            return ExitCode::USAGE;
        }

        if (Craft::$app->getFields()->getFieldByHandle($this->field) === null) {
            $this->stderr("No field with the handle \"$this->field\".\n");
            return ExitCode::USAGE;
        }

        $this->stdout(sprintf(
            "Importing \"%s\" into %s prices for the store \"%s\"%s.\n",
            $this->field,
            $currency,
            $store->handle,
            $this->apply ? '' : ' (dry run: nothing will be written)',
        ));

        $this->importStore($store, $currency);

        foreach (self::LABELS as $key => $label) {
            $this->stdout("$label: {$this->counts[$key]}\n");
        }

        if (!$this->apply) {
            $this->stdout("Dry run: nothing was written. Run again with --apply to write these prices.\n");
        }

        return $this->counts[self::COUNT_FAILED] > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Every purchasable type, in every site of the store. An item in several sites, or tickets sharing a
     * ticket type, is priced once.
     */
    private function importStore(Store $store, string $currency): void
    {
        $seen = [];

        /** @var Site $site */
        foreach (Commerce::getInstance()->getStores()->getAllSitesForStore($store) as $site) {
            foreach (Commerce::getInstance()->getPurchasables()->getAllPurchasableElementTypes() as $type) {
                /** @var class-string<Purchasable> $type */
                $query = $type::find()->siteId($site->id)->status(null);

                foreach (Db::each($query) as $purchasable) {
                    /** @var Purchasable $purchasable */
                    $ownerId = CurrencyPrices::getInstance()->getPurchasables()->getPriceOwnerId($purchasable);
                    if (isset($seen[$ownerId])) {
                        continue;
                    }
                    $seen[$ownerId] = true;

                    try {
                        $this->importItem($purchasable, $ownerId, $store->id, $currency);
                    } catch (\Throwable $e) {
                        $this->fail($ownerId, $e->getMessage());
                    }
                }
            }
        }
    }

    private function importItem(Purchasable $purchasable, int $ownerId, int $storeId, string $currency): void
    {
        // An Events ticket is priced by its ticket type, which holds the field too.
        $element = $ownerId === $purchasable->id
            ? $purchasable
            : Craft::$app->getElements()->getElementById($ownerId, null, $purchasable->siteId);
        if ($element === null) {
            $this->fail($ownerId, 'The element that holds its prices does not exist.');
            return;
        }

        $value = $this->readValue($element);

        if ($value === null) {
            $this->counts[self::COUNT_EMPTY]++;
            return;
        }

        if (!is_numeric($value) || $value < 0) {
            $this->fail($ownerId, sprintf('"%s" is not a price.', is_scalar($value) ? $value : get_debug_type($value)));
            return;
        }

        $price = round((float)$value, 4);

        if ($price == 0 && !$this->includeZero) {
            $this->counts[self::COUNT_ZERO]++;
            return;
        }

        $prices = CurrencyPrices::getInstance()->getPrices();
        $existing = $prices->getPrice($ownerId, $storeId, $currency);

        if ($existing !== null && round($existing, 4) == $price) {
            $this->counts[self::COUNT_UNCHANGED]++;
            return;
        }

        if ($this->apply) {
            $prices->savePrices($ownerId, $storeId, [$currency => $price]);
        }

        $this->counts[$existing === null ? self::COUNT_WRITTEN : self::COUNT_OVERWRITTEN]++;
    }

    /**
     * The field's value on the element, or else on its owner. Null when neither has one.
     */
    private function readValue(ElementInterface $element): mixed
    {
        $owner = $element instanceof NestedElementInterface ? $element->getOwner() : null;

        foreach ([$element, $owner] as $source) {
            if ($source === null || $source->getFieldLayout()?->getFieldByHandle((string)$this->field) === null) {
                continue;
            }

            $value = $source->getFieldValue((string)$this->field);
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function fail(int $id, string $message): void
    {
        $this->counts[self::COUNT_FAILED]++;
        $this->stderr("Item $id failed: $message\n");
    }
}
