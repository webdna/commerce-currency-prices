<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://webdna.co.uk/
 * @copyright Copyright (c) 2018 webdna
 */

namespace webdna\commerce\currencyprices\assetbundles\paymentcurrencies;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    webdna
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class PaymentCurrenciesAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
		//$this->sourcePath = "@webdna/currency-prices/assetbundles/paymentcurrencies/dist";
		$this->sourcePath = __DIR__.'/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/PaymentCurrencies.js',
        ];

        $this->css = [
            'css/PaymentCurrencies.css',
        ];

        parent::init();
    }
}
