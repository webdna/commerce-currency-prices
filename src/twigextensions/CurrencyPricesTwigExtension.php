<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * add multiple currency prices for products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\currencyprices\twigextensions;

use kuriousagency\currencyprices\CurrencyPrices;
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
		
		$primary = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
		
		if ($currency == $primary) {
			return $variant->price;
		}

		$amount = $variant->prices->{$currency};

        // return input if no currency passed, and both convert and format are false.
        if (!$format) {
            return $amount;
        }

        if ($format) {
            $amount = Craft::$app->getFormatter()->asCurrency($amount, $currency, [], [], $stripZeros);
        }

        return $amount;
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
