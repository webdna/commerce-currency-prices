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
use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin as Commerce;
use yii\web\HttpException;
use yii\web\Response;

/**
 * @author    Kurious Agency
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class PaymentCurrenciesController extends Controller
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

	/**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $currency = new PaymentCurrency();

        // Shared attributes
        $currency->id = Craft::$app->getRequest()->getBodyParam('currencyId');
        $currency->iso = Craft::$app->getRequest()->getBodyParam('iso');
        $currency->rate = Craft::$app->getRequest()->getBodyParam('rate');
		$currency->primary = (bool)Craft::$app->getRequest()->getBodyParam('primary');

		$id = $currency->id;
		
		if ($id) {	
			$oldCurrency = Commerce::getInstance()->getPaymentCurrencies()->getPaymentCurrencyById($currency->id);
			//Craft::dd($oldCurrency);
		}

        // Save it
        if (Commerce::getInstance()->getPaymentCurrencies()->savePaymentCurrency($currency)) {
			if ($id) {
				CurrencyPrices::$plugin->service->renameCurrency($oldCurrency->iso, $currency->iso);
			} else {
				CurrencyPrices::$plugin->service->addCurrency($currency->iso);
			}
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Currency saved.'));
            $this->redirectToPostedUrl($currency);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save currency.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['currency' => $currency]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        $currency = Commerce::getInstance()->getPaymentCurrencies()->getPaymentCurrencyById($id);

        if ($currency && !$currency->primary) {
			Commerce::getInstance()->getPaymentCurrencies()->deletePaymentCurrencyById($id);
			CurrencyPrices::$plugin->service->removeCurrency($currency->iso);
            return $this->asJson(['success' => true]);
        }

        $message = Craft::t('commerce', 'You can not delete that currency.');
        return $this->asErrorJson($message);
    }
}
