<?php

namespace webdna\commerce\currencyprices\tests\integration;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\models\Discount;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin as Commerce;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use webdna\commerce\currencyprices\console\controllers\LegacyController;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\tests\Support\CommerceFixtures;

/**
 * The legacy report (task 3.3, BR-20): every Craft 4 per-currency amount, under its record's name and CP URL
 * or "deleted", discounts flipped back to positive, and nothing changed. TS-8 step 5 is run on the
 * craft4-commerce fixture.
 *
 * The 3.x tables are TEMPORARY tables, because a CREATE TABLE would commit the test's transaction.
 */
class LegacyReportTest extends Unit
{
    use CommerceFixtures;

    private const TABLES = [
        '{{%commerce_discounts_currencyprices}}' => 'discountId INT NULL, purchaseTotal DECIMAL(14,4) NULL, baseDiscount DECIMAL(14,4) NULL, perItemDiscount DECIMAL(14,4) NULL',
        '{{%commerce_shippingrules_currencyprices}}' => 'shippingRuleId INT NULL, minTotal DECIMAL(14,4) NULL, baseRate DECIMAL(14,4) NULL, perItemRate DECIMAL(14,4) NULL',
        '{{%commerce_shippingrule_categories_currencyprices}}' => 'shippingRuleId INT NULL, shippingCategoryId INT NULL, perItemRate DECIMAL(14,4) NULL, weightRate DECIMAL(14,4) NULL',
        '{{%addons_discounts_currencyprices}}' => 'discountId INT NULL, perItemDiscount DECIMAL(14,4) NULL',
    ];

    protected function _before(): void
    {
        $this->setUpGbpStore();
    }

    protected function _after(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            $this->db("DROP TEMPORARY TABLE IF EXISTS %s", $table);
        }
    }

    public function testWithNoCraft4TablesItSaysSo(): void
    {
        $report = $this->report();

        $this->assertSame(0, $report['exit']);
        $this->assertSame(LegacyController::NO_DATA . "\n", $report['out']);
    }

    public function testWithEmptyCraft4TablesItSaysSo(): void
    {
        $this->createLegacyTables();
        $this->insert('{{%commerce_discounts_currencyprices}}', ['discountId' => 1, 'paymentCurrencyIso' => 'USD', 'purchaseTotal' => 0, 'baseDiscount' => 0, 'perItemDiscount' => null]);

        $this->assertSame(LegacyController::NO_DATA . "\n", $this->report()['out']);
    }

    public function testEveryAmountIsListedUnderItsRecordAndNothingChanges(): void
    {
        $this->createLegacyTables();

        $discount = new Discount(['storeId' => $this->store->id, 'name' => 'Spring sale', 'enabled' => true, 'allPurchasables' => true, 'allCategories' => true]);
        $this->assertTrue(Commerce::getInstance()->getDiscounts()->saveDiscount($discount), implode(', ', $discount->getFirstErrors()));

        $method = new ShippingMethod(['storeId' => $this->store->id, 'name' => 'Standard', 'handle' => 'standard' . StringHelper::randomString(6), 'enabled' => true]);
        $this->assertTrue(Commerce::getInstance()->getShippingMethods()->saveShippingMethod($method), implode(', ', $method->getFirstErrors()));
        $rule = new ShippingRule(['storeId' => $this->store->id, 'methodId' => $method->id, 'name' => 'UK mainland', 'enabled' => true]);
        $this->assertTrue(Commerce::getInstance()->getShippingRules()->saveShippingRule($rule), implode(', ', $rule->getFirstErrors()));
        $category = Commerce::getInstance()->getShippingCategories()->getAllShippingCategories($this->store->id)->first();

        $deletedId = 999999;

        // 3.x stored discount amounts negated.
        $this->insert('{{%commerce_discounts_currencyprices}}', ['discountId' => $discount->id, 'paymentCurrencyIso' => 'USD', 'purchaseTotal' => -40, 'baseDiscount' => -4, 'perItemDiscount' => -0.8]);
        $this->insert('{{%commerce_discounts_currencyprices}}', ['discountId' => $discount->id, 'paymentCurrencyIso' => 'EUR', 'purchaseTotal' => -45, 'baseDiscount' => -4.5, 'perItemDiscount' => 0]);
        $this->insert('{{%commerce_discounts_currencyprices}}', ['discountId' => $deletedId, 'paymentCurrencyIso' => 'USD', 'purchaseTotal' => 0, 'baseDiscount' => -10, 'perItemDiscount' => null]);
        $this->insert('{{%commerce_shippingrules_currencyprices}}', ['shippingRuleId' => $rule->id, 'paymentCurrencyIso' => 'USD', 'minTotal' => 0, 'baseRate' => 3, 'perItemRate' => 1]);
        $this->insert('{{%commerce_shippingrule_categories_currencyprices}}', ['shippingRuleId' => $rule->id, 'shippingCategoryId' => $category->id, 'paymentCurrencyIso' => 'USD', 'perItemRate' => 2, 'weightRate' => 0]);
        $this->insert('{{%addons_discounts_currencyprices}}', ['discountId' => 7, 'paymentCurrencyIso' => 'EUR', 'perItemDiscount' => -1.25]);

        $before = $this->rowCounts();
        $report = $this->report();

        $this->assertSame(0, $report['exit']);
        $this->assertSame($before, $this->rowCounts());

        $settings = $this->store->getStoreSettingsUrl();
        $ruleUrl = "$settings/shippingmethods/$method->id/shippingrules/$rule->id";

        $this->assertStringContainsString(
            "Discounts\n"
            . "  Discount $discount->id: \"Spring sale\" $settings/discounts/$discount->id\n"
            . "    EUR: purchase total 45, base discount 4.5\n"
            . "    USD: purchase total 40, base discount 4, per item discount 0.8\n"
            . "  Discount $deletedId: deleted\n"
            . "    USD: base discount 10\n",
            $report['out'],
        );
        $this->assertStringContainsString(
            "Shipping rules\n"
            . "  Shipping rule $rule->id: \"UK mainland\" $ruleUrl\n"
            . "    USD: base rate 3, per item rate 1\n",
            $report['out'],
        );
        $this->assertStringContainsString(
            "Shipping category rates\n"
            . "  Shipping category $category->id \"$category->name\" on shipping rule $rule->id: \"UK mainland\" $ruleUrl\n"
            . "    USD: per item rate 2\n",
            $report['out'],
        );
        $this->assertStringContainsString(
            "Commerce Addons discounts\n"
            . "  Commerce Addons discount 7: its name is unknown (the Commerce Addons tables are gone)\n"
            . "    EUR: per item discount 1.25\n",
            $report['out'],
        );
    }

    private function createLegacyTables(): void
    {
        foreach (self::TABLES as $table => $columns) {
            $this->db(
                "CREATE TEMPORARY TABLE %s (id INT AUTO_INCREMENT PRIMARY KEY, $columns, paymentCurrencyIso CHAR(3) NOT NULL, dateCreated DATETIME NOT NULL, dateUpdated DATETIME NOT NULL, uid CHAR(36) NOT NULL DEFAULT '0')",
                $table,
            );
        }
    }

    private function db(string $sql, string $table): void
    {
        $db = Craft::$app->getDb();
        $db->createCommand(sprintf($sql, $db->quoteTableName($db->getSchema()->getRawTableName($table))))->execute();
        $db->getSchema()->refreshTableSchema($table);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insert(string $table, array $row): void
    {
        Db::insert($table, $row);
    }

    /**
     * @return array<string, int|string>
     */
    private function rowCounts(): array
    {
        $counts = [];
        foreach (array_keys(self::TABLES) as $table) {
            $counts[$table] = (new Query())->from($table)->count();
        }

        return $counts;
    }

    /**
     * @return array{exit: int, out: string}
     */
    private function report(): array
    {
        $controller = new class('legacy', CurrencyPrices::getInstance()) extends LegacyController {
            public string $out = '';

            /**
             * @return int
             */
            public function stdout($string)
            {
                $this->out .= $string;
                return strlen($string);
            }
        };

        $exit = $controller->actionReport();

        return ['exit' => $exit, 'out' => $controller->out];
    }
}
