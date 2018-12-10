<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * add multiple currency prices for products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\currencyprices\services;

use kuriousagencyx\currencyprices\CurrencyPrices;

use Craft;
use craft\base\Component;
use craft\commerce\Plugin as Commerce;

/**
 * @author    Kurious Agency
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class CurrencyPricesService extends Component
{
    // Public Methods
    // =========================================================================

    /*
     * @return mixed
     */
    public function setCurrency($currency)
    {
		$session = Craft::$app->getSession();
		$session->set('commerce_paymentCurrency', $currency);

		$cart = Commerce::getInstance()->getCarts()->getCart();
		$cart->setPaymentCurrency($currency);
		$cart->currency = $currency;
	}
	
	public function getCurrency()
	{
		$session = Craft::$app->getSession();
		$paymentCurrency = $session['commerce_paymentCurrency'];

		return $paymentCurrency;
	}
}
