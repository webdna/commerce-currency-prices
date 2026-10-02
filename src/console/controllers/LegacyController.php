<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\console\controllers;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\Plugin as Commerce;
use craft\console\Controller;
use craft\db\Query;
use yii\console\ExitCode;

/**
 * Lists the Craft 4 per-currency discount and shipping amounts that 4.0 no longer applies (BR-20).
 *
 * `php craft commerce-currency-prices/legacy/report`
 *
 * 3.x kept these amounts in its own tables, which the upgrade leaves in place. Each one is listed under the
 * record it belonged to, with its name and CP URL, or "deleted" when the record is gone, so a person can
 * rebuild those still wanted with a Payment currency condition. It reads only; nothing is changed.
 *
 * @author webdna
 * @since 4.0.0
 */
class LegacyController extends Controller
{
    public const NO_DATA = 'No Craft 4 data found.';

    /**
     * The 3.x tables, what each row belongs to, and its amount columns with their labels. 3.x saved discount
     * amounts negated (its controllers multiplied by -1), so those are flipped back for the report.
     */
    private const SOURCES = [
        [
            'heading' => 'Discounts',
            'table' => '{{%commerce_discounts_currencyprices}}',
            'record' => 'discount',
            'negated' => true,
            'amounts' => [
                'purchaseTotal' => 'purchase total',
                'baseDiscount' => 'base discount',
                'perItemDiscount' => 'per item discount',
            ],
        ],
        [
            'heading' => 'Shipping rules',
            'table' => '{{%commerce_shippingrules_currencyprices}}',
            'record' => 'shippingRule',
            'negated' => false,
            'amounts' => [
                'minTotal' => 'minimum total',
                'maxTotal' => 'maximum total',
                'minWeight' => 'minimum weight',
                'maxWeight' => 'maximum weight',
                'baseRate' => 'base rate',
                'perItemRate' => 'per item rate',
                'weightRate' => 'weight rate',
                'percentageRate' => 'percentage rate',
                'minRate' => 'minimum rate',
                'maxRate' => 'maximum rate',
            ],
        ],
        [
            'heading' => 'Shipping category rates',
            'table' => '{{%commerce_shippingrule_categories_currencyprices}}',
            'record' => 'shippingCategoryRate',
            'negated' => false,
            'amounts' => [
                'perItemRate' => 'per item rate',
                'weightRate' => 'weight rate',
                'percentageRate' => 'percentage rate',
            ],
        ],
        [
            'heading' => 'Commerce Addons discounts',
            'table' => '{{%addons_discounts_currencyprices}}',
            'record' => 'addonsDiscount',
            'negated' => true,
            'amounts' => [
                'perItemDiscount' => 'per item discount',
            ],
        ],
    ];

    private const ADDONS_DISCOUNTS_TABLE = '{{%addons_discounts}}';

    public $defaultAction = 'report';

    /**
     * Lists every Craft 4 per-currency discount and shipping amount. Changes nothing.
     */
    public function actionReport(): int
    {
        $sections = [];

        foreach (self::SOURCES as $source) {
            $groups = $this->readSource($source);
            if ($groups !== []) {
                $sections[$source['heading']] = $groups;
            }
        }

        if ($sections === []) {
            $this->stdout(self::NO_DATA . "\n");
            return ExitCode::OK;
        }

        $this->stdout("Craft 4 per-currency amounts, from Currency Prices 3.x. Commerce no longer applies any of them.\n");

        foreach ($sections as $heading => $groups) {
            $this->stdout("\n$heading\n");

            foreach ($groups as $group) {
                $this->stdout("  {$group['label']}: {$group['where']}\n");
                foreach ($group['lines'] as $line) {
                    $this->stdout("    $line\n");
                }
            }
        }

        $this->stdout("\nNothing was changed. To keep an amount, recreate it on a discount or shipping rule with a Payment currency condition.\n");

        return ExitCode::OK;
    }

    /**
     * One source table's rows, grouped by the record they belonged to, in id order. A row with no amount
     * other than 0 is left out.
     *
     * @param array{table: string, record: string, negated: bool, amounts: array<string, string>} $source
     * @return array<string, array{label: string, where: string, lines: string[]}>
     */
    private function readSource(array $source): array
    {
        $db = Craft::$app->getDb();
        $schema = $db->getTableSchema($source['table']);
        if ($schema === null) {
            return [];
        }

        $isCategory = $source['record'] === 'shippingCategoryRate';
        $idColumn = match ($source['record']) {
            'discount', 'addonsDiscount' => 'discountId',
            default => 'shippingRuleId',
        };
        $amountColumns = array_values(array_intersect(array_keys($source['amounts']), $schema->getColumnNames()));

        $rows = (new Query())
            ->select([$idColumn, 'paymentCurrencyIso', ...$amountColumns, ...($isCategory ? ['shippingCategoryId'] : [])])
            ->from($source['table'])
            ->orderBy([$idColumn => SORT_ASC, ...($isCategory ? ['shippingCategoryId' => SORT_ASC] : []), 'paymentCurrencyIso' => SORT_ASC])
            ->all($db);

        $groups = [];

        foreach ($rows as $row) {
            $amounts = [];
            foreach ($amountColumns as $column) {
                if ($row[$column] === null || (float)$row[$column] == 0) {
                    continue;
                }
                $value = $source['negated'] ? -(float)$row[$column] : (float)$row[$column];
                $amounts[] = $source['amounts'][$column] . ' ' . $this->formatAmount($value);
            }

            if ($amounts === []) {
                continue;
            }

            $recordId = (int)$row[$idColumn];
            $categoryId = $isCategory ? (int)$row['shippingCategoryId'] : null;
            $key = $recordId . '-' . $categoryId;

            $groups[$key] ??= $this->describe($source['record'], $recordId, $categoryId);
            $groups[$key]['lines'][] = $row['paymentCurrencyIso'] . ': ' . implode(', ', $amounts);
        }

        return $groups;
    }

    /**
     * The record's label, and its name and CP URL, or "deleted".
     *
     * @return array{label: string, where: string, lines: string[]}
     */
    private function describe(string $record, int $id, ?int $categoryId): array
    {
        $group = ['label' => '', 'where' => 'deleted', 'lines' => []];

        switch ($record) {
            case 'discount':
                $group['label'] = "Discount $id";
                $discount = (new Query())->select(['name', 'storeId'])->from(Table::DISCOUNTS)->where(['id' => $id])->one();
                if ($discount) {
                    $group['where'] = $this->named($discount['name'], (int)$discount['storeId'], "discounts/$id");
                }
                break;

            case 'shippingRule':
            case 'shippingCategoryRate':
                $rule = (new Query())
                    ->select(['rules.name', 'rules.methodId', 'methods.storeId'])
                    ->from(['rules' => Table::SHIPPINGRULES])
                    ->innerJoin(['methods' => Table::SHIPPINGMETHODS], '[[methods.id]] = [[rules.methodId]]')
                    ->where(['rules.id' => $id])
                    ->one();
                $path = $rule ? "shippingmethods/{$rule['methodId']}/shippingrules/$id" : '';

                if ($categoryId === null) {
                    $group['label'] = "Shipping rule $id";
                    if ($rule) {
                        $group['where'] = $this->named($rule['name'], (int)$rule['storeId'], $path);
                    }
                    break;
                }

                $category = (new Query())->select(['name'])->from(Table::SHIPPINGCATEGORIES)->where(['id' => $categoryId])->scalar();
                $group['label'] = sprintf(
                    'Shipping category %d%s on shipping rule %d',
                    $categoryId,
                    $category !== false ? " \"$category\"" : ' (deleted)',
                    $id,
                );
                if ($rule) {
                    $group['where'] = $this->named($rule['name'], (int)$rule['storeId'], $path);
                }
                break;

            case 'addonsDiscount':
                $group['label'] = "Commerce Addons discount $id";
                // Commerce Addons has no Craft 5 version, so there is no CP page, and its own table may be gone.
                if (Craft::$app->getDb()->getTableSchema(self::ADDONS_DISCOUNTS_TABLE) === null) {
                    $group['where'] = 'its name is unknown (the Commerce Addons tables are gone)';
                    break;
                }
                $name = (new Query())->select(['name'])->from(self::ADDONS_DISCOUNTS_TABLE)->where(['id' => $id])->scalar();
                if ($name !== false) {
                    $group['where'] = "\"$name\" (Commerce Addons has no Craft 5 version, so no CP URL)";
                }
                break;
        }

        return $group;
    }

    private function named(string $name, int $storeId, string $path): string
    {
        $store = Commerce::getInstance()->getStores()->getStoreById($storeId);

        return $store ? "\"$name\" " . $store->getStoreSettingsUrl($path) : "\"$name\"";
    }

    /**
     * Up to 4 places, the columns' scale, without trailing zeros.
     */
    private function formatAmount(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
