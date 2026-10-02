<?php

namespace webdna\commerce\currencyprices\tests\integration;

use Codeception\Test\Unit;
use Craft;
use craft\base\FieldInterface;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin as Commerce;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use webdna\commerce\currencyprices\console\controllers\ImportController;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\services\Prices;
use webdna\commerce\currencyprices\tests\Support\CommerceFixtures;
use yii\console\ExitCode;

/**
 * Import (task 4.1): a shop's own number field becomes currency prices, previewed first (TS-9, BR-21, TN-11).
 */
class ImportTest extends Unit
{
    use CommerceFixtures;

    protected function _before(): void
    {
        $this->setUpGbpStore();
        CurrencyPrices::getInstance()->set('prices', ['class' => Prices::class]);
    }

    public function testADryRunThenAnApplyThenARerun(): void
    {
        // TS-9 #1: the product says 50; one variant 55, one empty, one 0.
        $field = $this->createFieldOnClothing(new Number(['decimals' => 2]));
        [$own, $inherited, $zero] = $this->createProductWithVariants($field, 50, [55, null, 0]);

        // TS-9 #2
        $run = $this->import(['field' => $field->handle, 'currency' => 'USD']);
        $this->assertSame(ExitCode::OK, $run['exit']);
        $this->assertCounts(['written' => 2, 'skippedZero' => 1], $run['counts']);
        $this->assertStringContainsString("Written: 2\n", $run['out']);
        $this->assertStringContainsString("Skipped as zero: 1\n", $run['out']);
        $this->assertStringContainsString('Dry run: nothing was written', $run['out']);
        $this->assertNull($this->price($own));
        $this->assertNull($this->price($inherited));

        // TS-9 #3
        $run = $this->import(['field' => $field->handle, 'currency' => 'usd', 'apply' => true]);
        $this->assertSame(ExitCode::OK, $run['exit']);
        $this->assertCounts(['written' => 2, 'skippedZero' => 1], $run['counts']);
        $this->assertStringNotContainsString('Dry run', $run['out']);
        $this->assertSame(55.0, $this->price($own));
        $this->assertSame(50.0, $this->price($inherited), 'An empty variant takes the product\'s value');
        $this->assertNull($this->price($zero), 'A 0 is not a price');

        // TS-9 #4
        $run = $this->import(['field' => $field->handle, 'currency' => 'USD', 'apply' => true]);
        $this->assertCounts(['unchanged' => 2, 'skippedZero' => 1], $run['counts']);
    }

    public function testAChangedValueIsCountedAsOverwritten(): void
    {
        $field = $this->createFieldOnClothing(new Number(['decimals' => 2]));
        [$variant] = $this->createProductWithVariants($field, null, [55]);
        CurrencyPrices::getInstance()->getPrices()->savePrices($variant->id, $this->store->id, ['USD' => 40, 'EUR' => 45]);

        $run = $this->import(['field' => $field->handle, 'currency' => 'USD']);
        $this->assertCounts(['overwritten' => 1], $run['counts']);
        $this->assertSame(40.0, $this->price($variant), 'A dry run leaves the old price');

        $this->import(['field' => $field->handle, 'currency' => 'USD', 'apply' => true]);
        $this->assertSame(55.0, $this->price($variant));
        $this->assertSame(45.0, CurrencyPrices::getInstance()->getPrices()->getPrice($variant->id, $this->store->id, 'EUR'), 'Other currencies are untouched');
    }

    public function testIncludeZeroImportsAZero(): void
    {
        $field = $this->createFieldOnClothing(new Number(['decimals' => 2]));
        [$zero] = $this->createProductWithVariants($field, 50, [0]);

        $run = $this->import(['field' => $field->handle, 'currency' => 'USD', 'apply' => true, 'includeZero' => true]);
        $this->assertCounts(['written' => 1], $run['counts']);
        $this->assertSame(0.0, $this->price($zero));
    }

    public function testAnItemWithNoValueAnywhereIsSkippedAsEmpty(): void
    {
        $field = $this->createFieldOnClothing(new Number(['decimals' => 2]));
        $this->createProductWithVariants($field, null, [null]);

        $run = $this->import(['field' => $field->handle, 'currency' => 'USD', 'apply' => true]);
        $this->assertCounts(['skippedEmpty' => 1], $run['counts']);
    }

    public function testTextThatIsNotAPriceFailsWithItsIdAndTheRestContinue(): void
    {
        // TN-11
        $field = $this->createFieldOnClothing(new PlainText());
        [$text, $priced, $negative] = $this->createProductWithVariants($field, null, ['call us', ' 12.50 ', '-5']);

        $run = $this->import(['field' => $field->handle, 'currency' => 'USD', 'apply' => true]);
        $this->assertSame(ExitCode::UNSPECIFIED_ERROR, $run['exit']);
        $this->assertCounts(['written' => 1, 'failed' => 2], $run['counts']);
        $this->assertStringContainsString("Item $text->id failed: \"call us\" is not a price.", $run['err']);
        $this->assertStringContainsString("Item $negative->id failed", $run['err']);
        $this->assertSame(12.5, $this->price($priced));
        $this->assertNull($this->price($text));
    }

    public function testACurrencyTheStoreDoesNotAcceptStopsBeforeAnythingIsRead(): void
    {
        $field = $this->createFieldOnClothing(new Number(['decimals' => 2]));
        $this->createProductWithVariants($field, 50, [55]);

        foreach (['JPY' => 'does not accept JPY', 'GBP' => 'GBP is the store currency'] as $iso => $message) {
            $run = $this->import(['field' => $field->handle, 'currency' => $iso, 'apply' => true]);
            $this->assertSame(ExitCode::USAGE, $run['exit'], $iso);
            $this->assertStringContainsString($message, $run['err']);
            $this->assertSame('', $run['out'], 'Nothing was read');
            $this->assertCounts([], $run['counts']);
        }
    }

    public function testAnUnknownStoreOrFieldStopsIt(): void
    {
        $field = $this->createFieldOnClothing(new Number(['decimals' => 2]));

        $run = $this->import(['field' => $field->handle, 'currency' => 'USD', 'store' => 'nope']);
        $this->assertSame(ExitCode::USAGE, $run['exit']);
        $this->assertStringContainsString('No store with the handle "nope"', $run['err']);

        $run = $this->import(['field' => 'nope', 'currency' => 'USD']);
        $this->assertSame(ExitCode::USAGE, $run['exit']);
        $this->assertStringContainsString('No field with the handle "nope"', $run['err']);

        $run = $this->import(['currency' => 'USD']);
        $this->assertSame(ExitCode::USAGE, $run['exit']);
    }

    public function testTheStoreOptionNamesTheStore(): void
    {
        $field = $this->createFieldOnClothing(new Number(['decimals' => 2]));
        [$variant] = $this->createProductWithVariants($field, null, [55]);

        $this->import(['field' => $field->handle, 'currency' => 'USD', 'store' => $this->store->handle, 'apply' => true]);
        $this->assertSame(55.0, $this->price($variant));
    }

    public function testTheOptionsAreTheCommandLineOnes(): void
    {
        $options = (new ImportController('import', CurrencyPrices::getInstance()))->options('index');

        foreach (['field', 'currency', 'store', 'apply', 'includeZero'] as $option) {
            $this->assertContains($option, $options);
        }
    }

    /**
     * Runs the command with these options, capturing what it prints.
     *
     * @param array<string, mixed> $options
     * @return array{exit: int, out: string, err: string, counts: array<string, int>}
     */
    private function import(array $options): array
    {
        $controller = new class('import', CurrencyPrices::getInstance()) extends ImportController {
            public string $out = '';
            public string $err = '';

            /**
             * @return int
             */
            public function stdout($string)
            {
                $this->out .= $string;
                return strlen($string);
            }

            /**
             * @return int
             */
            public function stderr($string)
            {
                $this->err .= $string;
                return strlen($string);
            }
        };

        Craft::configure($controller, $options);
        $exit = $controller->actionIndex();

        return ['exit' => $exit, 'out' => $controller->out, 'err' => $controller->err, 'counts' => $controller->counts];
    }

    /**
     * @param array<string, int> $expected The counts that are not 0
     * @param array<string, int> $counts
     */
    private function assertCounts(array $expected, array $counts): void
    {
        $all = array_fill_keys(['written', 'unchanged', 'overwritten', 'skippedEmpty', 'skippedZero', 'failed'], 0);
        $this->assertSame([...$all, ...$expected], [...$all, ...array_filter($counts)]);
    }

    private function price(Variant $variant): ?float
    {
        return CurrencyPrices::getInstance()->getPrices()->getPrice($variant->id, $this->store->id, 'USD');
    }

    /**
     * The field, saved with a unique handle (fields live in project config and outlast a test), on both
     * the product and the variant layout of the clothing type.
     */
    private function createFieldOnClothing(FieldInterface $field): FieldInterface
    {
        $field->name = 'Price USD';
        $field->handle = 'priceUSD' . uniqid();
        $this->assertTrue(Craft::$app->getFields()->saveField($field), implode(', ', $field->getFirstErrors()));

        $productType = $this->getProductType();
        foreach ([$productType->getProductFieldLayout(), $productType->getVariantFieldLayout()] as $layout) {
            /** @var FieldLayout $layout */
            $tab = new FieldLayoutTab(['layout' => $layout, 'name' => 'Prices']);
            $tab->setElements([new CustomField($field)]);
            $layout->setTabs([...$layout->getTabs(), $tab]);
        }
        $this->assertTrue(Commerce::getInstance()->getProductTypes()->saveProductType($productType), implode(', ', $productType->getFirstErrors()));

        return $field;
    }

    /**
     * One product holding the field's value, and a variant per value.
     *
     * @param array<int|float|string|null> $variantValues
     * @return Variant[]
     */
    private function createProductWithVariants(FieldInterface $field, int|float|string|null $productValue, array $variantValues): array
    {
        $elements = Craft::$app->getElements();

        $product = new Product(['typeId' => $this->getProductType()->id, 'title' => 'Imported']);
        $product->setFieldValue($field->handle, $productValue);
        $this->assertTrue($elements->saveElement($product), implode(', ', $product->getFirstErrors()));

        $variants = [];
        foreach ($variantValues as $i => $value) {
            $variant = new Variant(['title' => "Imported $i", 'sku' => 'IMP-' . $i . '-' . uniqid(), 'basePrice' => 10]);
            $variant->setPrimaryOwner($product);
            $variant->setOwner($product);
            $variant->setFieldValue($field->handle, $value);
            $this->assertTrue($elements->saveElement($variant), implode(', ', $variant->getFirstErrors()));
            $variants[] = $variant;
        }

        return $variants;
    }
}
