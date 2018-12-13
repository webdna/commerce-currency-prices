<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\currencyprices\twigextensions;

use kuriousagency\commerce\currencyprices\CurrencyPrices;
use craft\commerce\errors\CurrencyException;
use craft\commerce\Plugin as Commerce;

use Craft;

/**
 * @author    Kurious Agency
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class CurrencyPricesTwigExtension extends \Twig_Extension
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'CurrencyPrices';
    }

    /**
     * @inheritdoc
     */
    public function getFilters()
    {
        return [
			new \Twig_SimpleFilter('currencyPrice', [$this, 'currencyPrice']),
			new \Twig_SimpleFilter('currencySalePrice', [$this, 'currencySalePrice']),
        ];
    }

    /**
     * Formats and optionally converts a currency amount into the supplied valid payment currency as per the rate setup in payment currencies.
     *
     * @param      $amount
     * @param      $currency
     * @param bool $format
     * @param bool $stripZeros
     * @return string
     */
    public function currencyPrice($variant, $currency, $format = true, $stripZeros = false): string
    {
		$this->_validatePaymentCurrency($currency);
		
		$prices = CurrencyPrices::$plugin->service->getPricesByPurchasableId($variant->id);
		$amount = '';

		if ($prices) {
			$amount = $prices[$currency];
		}

        // return input if no currency passed, and both convert and format are false.
        if (!$format) {
            return $amount;
        }

        if ($format) {
            $amount = Craft::$app->getFormatter()->asCurrency($amount, $currency, [], [], $stripZeros);
        }

        return $amount;
	}

	public function currencySalePrice($variant, $currency, $format = true, $stripZeros = false): string
    {
		$this->_validatePaymentCurrency($currency);

		$salePrice = CurrencyPrices::$plugin->service->getSalePrice($variant, $currency);
		
		if (!$format) {
            return $salePrice;
        }

        if ($format) {
            $salePrice = Craft::$app->getFormatter()->asCurrency($salePrice, $currency, [], [], $stripZeros);
        }

        return $salePrice;
		
	}


	// Private methods
    // =========================================================================

    /**
     * @param $currency
     * @throws \Twig_Error
     */
    private function _validatePaymentCurrency($currency)
    {
        try {
            $currency = Commerce::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($currency);
        } catch (CurrencyException $exception) {
            throw new \Twig_Error($exception->getMessage());
        }
    }
}
