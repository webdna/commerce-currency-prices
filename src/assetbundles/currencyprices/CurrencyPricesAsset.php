<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\currencyprices\assetbundles\currencyprices;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Kurious Agency
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class CurrencyPricesAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
		//$this->sourcePath = "@kuriousagency/currency-prices/assetbundles/paymentcurrencies/dist";
		$this->sourcePath = __DIR__.'/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
			'js/addons.js',
			'js/discounts.js',
			'js/products.js',
			'js/shipping.js',
			'js/purchasable.js',
        ];

        $this->css = [
            'css/CurrencyPrices.css',
        ];

        parent::init();
    }
}
