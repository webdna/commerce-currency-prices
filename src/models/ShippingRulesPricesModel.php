<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://webdna.co.uk/
 * @copyright Copyright (c) 2018 webdna
 */

namespace webdna\commerce\currencyprices\models;

use webdna\commerce\currencyprices\CurrencyPrices;

use Craft;
use craft\base\Model;

/**
 * @author    webdna
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class ShippingRulesPricesModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $shippingRuleId;
    public $paymentCurrencyId;
    public $minTotal;
    public $maxTotal;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            ['someAttribute', 'string'],
            ['someAttribute', 'default', 'value' => 'Some Default'],
        ];
    }
}
