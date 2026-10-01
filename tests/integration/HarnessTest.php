<?php

namespace webdna\commerce\currencyprices\tests\integration;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\Plugin as Commerce;

/**
 * The harness itself: Craft 5 boots against the MySQL container with Commerce 5 installed and a
 * primary store. An empty suite never boots the Craft module, so without this a broken harness
 * would still pass `composer check`.
 */
class HarnessTest extends Unit
{
    public function testCraftAndCommerceAreInstalled(): void
    {
        $this->assertTrue(version_compare(Craft::$app->getVersion(), '5.0', '>='));
        $this->assertTrue(Craft::$app->getPlugins()->isPluginInstalled('commerce'));
        $this->assertTrue(version_compare(Commerce::getInstance()->getVersion(), '5.3', '>='));
        $this->assertNotNull(Commerce::getInstance()->getStores()->getPrimaryStore());
    }
}
