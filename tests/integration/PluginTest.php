<?php

namespace webdna\commerce\currencyprices\tests\integration;

use Codeception\Test\Unit;
use Craft;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\fields\CurrencyField;

/**
 * The Craft 5 skeleton (task 0.2): the plugin installs after Commerce under its 3.x handle, and the
 * Currencies field keeps its class name, which sites' project config stores.
 */
class PluginTest extends Unit
{
    public function testPluginInstallsUnderItsHandle(): void
    {
        $this->assertTrue(Craft::$app->getPlugins()->isPluginInstalled('commerce-currency-prices'));
        $this->assertInstanceOf(CurrencyPrices::class, Craft::$app->getPlugins()->getPlugin('commerce-currency-prices'));
    }

    public function testCurrencyFieldIsRegistered(): void
    {
        $this->assertContains(CurrencyField::class, Craft::$app->getFields()->getAllFieldTypes());
    }
}
