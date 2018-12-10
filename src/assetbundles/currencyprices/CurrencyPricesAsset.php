<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * add multiple currency prices for products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\currencyprices\assetbundles\currencyprices;

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
        $this->sourcePath = "@kuriousagency/currencyprices/assetbundles/currencyprices/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/CurrencyPricesField.js',
        ];

        $this->css = [
            'css/CurrencyPricesField.css',
        ];

        parent::init();
    }
}
