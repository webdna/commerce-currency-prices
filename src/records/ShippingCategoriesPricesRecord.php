<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://webdna.co.uk/
 * @copyright Copyright (c) 2018 webdna
 */

namespace webdna\commerce\currencyprices\records;

use webdna\commerce\currencyprices\CurrencyPrices;

use Craft;
use craft\db\ActiveRecord;

/**
 * @author    webdna
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class ShippingCategoriesPricesRecord extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%commerce_shippingrule_categories_currencyprices}}';
    }
}
