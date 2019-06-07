<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\currencyprices\controllers;

use kuriousagency\commerce\currencyprices\CurrencyPrices;

use Craft;
use craft\web\Controller;
use craft\commerce\Plugin as Commerce;

/**
 * @author    Kurious Agency
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class PricesController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = [];

    // Public Methods
    // =========================================================================

	public function actionGetPurchasablePrices()
	{
		$this->requireAcceptsJson();
		$id = Craft::$app->getRequest()->getRequiredParam('id');

		$purchasable = Commerce::getInstance()->getPurchasables()->getPurchasableById($id);

		$variables = [
			"purchasable" => $purchasable,
		];
		//Craft::dd($variables['values']);

		return $this->asJson([
			'html' => $this->getView()->renderTemplate('commerce-currency-prices/prices-purchasable', $variables)
		]);
	}
}
