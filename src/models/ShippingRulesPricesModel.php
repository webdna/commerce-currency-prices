<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\currencyprices\models;

use kuriousagency\commerce\currencyprices\CurrencyPrices;

use Craft;
use craft\base\Model;

/**
 * @author    Kurious Agency
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
    public function rules()
    {
        return [
            ['someAttribute', 'string'],
            ['someAttribute', 'default', 'value' => 'Some Default'],
        ];
    }
}
