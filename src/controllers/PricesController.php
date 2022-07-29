<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://webdna.co.uk/
 * @copyright Copyright (c) 2018 webdna
 */

namespace webdna\commerce\currencyprices\controllers;

use webdna\commerce\currencyprices\CurrencyPrices;

use Craft;
use craft\web\Controller;
use craft\commerce\Plugin as Commerce;
use yii\web\Response;

/**
 * @author    webdna
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
    protected array|bool|int $allowAnonymous = [];

    // Public Methods
    // =========================================================================

	public function actionGetPurchasablePrices(): Response
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
