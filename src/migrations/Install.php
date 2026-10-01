<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\migrations;

use craft\db\Migration;

/**
 * Fresh install. Task 1.1 adds the price table (one row per purchasable × store × payment currency).
 *
 * The 3.x install (a column per currency, foreign keys on `commerce_paymentcurrencies.iso`) is at
 * `734fa63:src/migrations/Install.php`, for the upgrade tasks 3.1 and 3.2.
 *
 * @author webdna
 * @since 1.0.0
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
