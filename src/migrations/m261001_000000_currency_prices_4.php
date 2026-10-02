<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\migrations;

use Craft;
use craft\commerce\db\Table as CommerceTable;
use craft\commerce\Plugin as Commerce;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\records\PriceRecord;
use yii\base\Application;

/**
 * The 3.x → 4.0 upgrade (BR-18, BR-19).
 *
 * 3.x keeps one row per purchasable with a column per payment currency in `commerce_currencyprices`.
 * Each non-null price whose currency the primary store accepts, other than its own currency, becomes a
 * row of `currencyprices_prices` in the primary store, where Commerce's upgrade put every purchasable.
 * The Craft 4 tables are left as they are: the legacy report reads them (BR-20, B3).
 *
 * On a real upgrade `craft up` runs this before Commerce's migrations (Craft holds the plugins as
 * blitz, commerce-currency-prices, commerce on the Commerce 4 fixture), so it drops any 3.x foreign keys
 * on `commerce_paymentcurrencies.iso` in time for Commerce's m230920_051125, which stops on them (MySQL
 * error 1553), and it copies the prices once Commerce has finished. Run after Commerce, it copies at once.
 *
 * @author webdna
 * @since 4.0.0
 */
class m261001_000000_currency_prices_4 extends Migration
{
    /**
     * The 3.x price table.
     */
    public const LEGACY_PRICE_TABLE = '{{%commerce_currencyprices}}';

    /**
     * The 3.x tables whose `paymentCurrencyIso` had a foreign key to `commerce_paymentcurrencies.iso`.
     */
    public const LEGACY_ISO_TABLES = [
        '{{%commerce_discounts_currencyprices}}',
        '{{%commerce_shippingrules_currencyprices}}',
        '{{%commerce_shippingrule_categories_currencyprices}}',
    ];

    /**
     * The 3.x price table's columns that are not a currency.
     */
    private const LEGACY_PRICE_COLUMNS = ['id', 'purchasableId', 'siteId', 'dateCreated', 'dateUpdated', 'uid'];

    /**
     * @var array<string, int> Prices copied, skipped (by reason) and invalid, for the summary and tests
     */
    public array $counts = [];

    /**
     * The plugin's schema version in the project config YAML when the migration started. Below 4.0's, this
     * is the environment where the upgrade is being made, so the layouts are changed here (BR-2).
     */
    private string $yamlSchemaVersion = '';

    public function safeUp(): bool
    {
        // Read before Craft records 4.0's schema version, which it does before Commerce's migrations run.
        $this->yamlSchemaVersion = (string)Craft::$app->getProjectConfig()->get('plugins.' . CurrencyPrices::getInstance()->id . '.schemaVersion', true);

        $this->dropLegacyIsoForeignKeys();

        if ($this->isCommerceUpdatePending()) {
            $this->upgradeAfterCommerce();
            return true;
        }

        if (!$this->db->tableExists(CommerceTable::STORES)) {
            echo "    > Currency Prices 4 needs Commerce 5's store tables, which do not exist yet. Nothing was copied. Run `php craft up`.\n";
            return false;
        }

        $this->upgrade();

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261001_000000_currency_prices_4 cannot be reverted.\n";
        return false;
    }

    /**
     * BR-19: 3.0.0-beta.6 drops these keys on Craft 4. Any left behind are dropped here.
     */
    private function dropLegacyIsoForeignKeys(): void
    {
        foreach (self::LEGACY_ISO_TABLES as $table) {
            if ($this->db->tableExists($table)) {
                $this->dropForeignKeyIfExists($table, ['paymentCurrencyIso']);
            }
        }
    }

    private function upgrade(): void
    {
        (new Install())->createPriceTable();
        $this->copyPrices();
        $this->addLayoutElement();
    }

    /**
     * Commerce has not upgraded yet, so there are no stores to copy into. Commerce's migrations follow in
     * the same `craft up` (or CP update), and Craft then records Commerce's new schema version in project
     * config: the copy runs then. If Commerce does not finish in this request (this migration was run on
     * its own, say), it is taken out of the migration history at the end, so the next `craft up` runs it again.
     */
    private function upgradeAfterCommerce(): void
    {
        echo "    > Commerce's upgrade has not run yet. The prices are copied once it has.\n";

        $done = false;

        Craft::$app->getProjectConfig()->onUpdate('plugins.commerce.schemaVersion', function() use (&$done): void {
            if ($done || $this->isCommerceUpdatePending()) {
                return;
            }
            $done = true;
            echo "\n*** Currency Prices 4: copying the 3.x prices now that Commerce has upgraded\n";
            $this->upgrade();
        });

        Craft::$app->on(Application::EVENT_AFTER_REQUEST, function() use (&$done): void {
            if ($done) {
                return;
            }
            CurrencyPrices::getInstance()->getMigrator()->removeMigrationHistory('m261001_000000_currency_prices_4');
            echo "    > Commerce did not finish upgrading, so no prices were copied. `php craft up` runs this migration again.\n";
        });
    }

    private function isCommerceUpdatePending(): bool
    {
        $commerce = Commerce::getInstance();

        return $commerce === null || Craft::$app->getPlugins()->isPluginUpdatePending($commerce);
    }

    /**
     * BR-18. A price already in the new table is left alone, so a second run writes nothing.
     */
    private function copyPrices(): void
    {
        $this->counts = [
            'copied' => 0,
            'storeCurrency' => 0,
            'notAccepted' => 0,
            'missingElement' => 0,
            'alreadyCopied' => 0,
            'invalid' => 0,
        ];

        $tableSchema = $this->db->getTableSchema(self::LEGACY_PRICE_TABLE);
        if ($tableSchema === null) {
            echo "    > No 3.x prices to copy.\n";
            return;
        }

        $store = Commerce::getInstance()->getStores()->getPrimaryStore();
        $storeCurrency = $store->getCurrency()?->getCode();
        $eligible = CurrencyPrices::getInstance()->getPrices()->getEligibleCurrencies($store->id);

        // Column name → ISO code
        $currencyColumns = [];
        foreach ($tableSchema->getColumnNames() as $column) {
            if (!in_array($column, self::LEGACY_PRICE_COLUMNS, true)) {
                $currencyColumns[$column] = strtoupper($column);
            }
        }

        $legacyRows = (new Query())
            ->select(['purchasableId', ...array_keys($currencyColumns)])
            ->from(self::LEGACY_PRICE_TABLE)
            ->orderBy(['purchasableId' => SORT_ASC])
            ->all($this->db);

        $existingElements = array_flip((new Query())
            ->select(['id'])
            ->from(Table::ELEMENTS)
            ->where(['id' => array_column($legacyRows, 'purchasableId')])
            ->column($this->db));

        $alreadyCopied = [];
        foreach ((new Query())->select(['purchasableId', 'currency'])->from(PriceRecord::tableName())->where(['storeId' => $store->id])->all($this->db) as $row) {
            $alreadyCopied[$row['purchasableId'] . ':' . $row['currency']] = true;
        }

        $insert = [];
        foreach ($legacyRows as $row) {
            foreach ($currencyColumns as $column => $currency) {
                $price = $row[$column];
                if ($price === null) {
                    continue;
                }

                if ($currency === $storeCurrency) {
                    // 3.x kept a copy of the base price in the store currency's column (BR-1).
                    $this->counts['storeCurrency']++;
                } elseif (!in_array($currency, $eligible, true)) {
                    $this->counts['notAccepted']++;
                } elseif (!isset($existingElements[$row['purchasableId']])) {
                    $this->counts['missingElement']++;
                } elseif (isset($alreadyCopied[$row['purchasableId'] . ':' . $currency])) {
                    $this->counts['alreadyCopied']++;
                } elseif (!is_numeric($price) || $price < 0) {
                    $this->counts['invalid']++;
                    echo "    > Invalid $currency price for purchasable {$row['purchasableId']}: $price\n";
                } else {
                    $insert[] = [$row['purchasableId'], $store->id, $currency, $price];
                    $this->counts['copied']++;
                }
            }
        }

        if (!empty($insert)) {
            $this->batchInsert(PriceRecord::tableName(), ['purchasableId', 'storeId', 'currency', 'price'], $insert);
        }

        $skipped = $this->counts['storeCurrency'] + $this->counts['notAccepted'] + $this->counts['missingElement'] + $this->counts['alreadyCopied'];
        echo "    > Currency prices copied: {$this->counts['copied']}, skipped: $skipped, invalid: {$this->counts['invalid']}\n";
        echo "    > Skipped: {$this->counts['storeCurrency']} in the store currency ($storeCurrency), {$this->counts['notAccepted']} in a currency the primary store does not accept, {$this->counts['missingElement']} for elements that no longer exist, {$this->counts['alreadyCopied']} already copied\n";
    }

    /**
     * 3.x showed prices on the variant edit page without a layout element. 4.0 needs one on each variant
     * layout (BR-2). Layouts are project config, so this happens once, where the change is made, and
     * other environments receive it with the YAML.
     */
    private function addLayoutElement(): void
    {
        $plugin = CurrencyPrices::getInstance();
        $projectConfig = Craft::$app->getProjectConfig();

        if ($projectConfig->getIsApplyingExternalChanges() || version_compare($this->yamlSchemaVersion, $plugin->schemaVersion, '>=')) {
            return;
        }

        $changed = $plugin->getPurchasables()->addToVariantLayouts();
        echo "    > Currency prices element added to $changed variant layout(s)\n";
    }
}
