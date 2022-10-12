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
use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin as Commerce;
use craft\commerce\models\ShippingRule;
use craft\commerce\records\ShippingRuleCategory;
use yii\web\HttpException;
use yii\web\Response;

/**
 * @author    webdna
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class ShippingController extends Controller
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

    public function actionGetInputs(): Response
    {
        $this->requireAcceptsJson();
        $id = Craft::$app->getRequest()->getParam('id');
        $name = Craft::$app->getRequest()->getRequiredParam('name');

        $variables = [
            "name" => $name,
            "values" => $this->_getValues($id, str_replace('CP','',$name)),
            "errors" => [],
            "label" => Craft::$app->getRequest()->getParam('label'),
            "instructions" => Craft::$app->getRequest()->getParam('instructions'),
        ];

        return $this->asJson([
            'html' => $this->getView()->renderTemplate('commerce-currency-prices/field', $variables)
        ]);
    }

    public function actionGetCategoryInputs(): Response
    {
        $this->requireAcceptsJson();
        $id = Craft::$app->getRequest()->getParam('id');
        $name = Craft::$app->getRequest()->getRequiredParam('name');
        $categories = Craft::$app->getRequest()->getRequiredParam('ruleCategories');

        $values = [];


        foreach ($categories as $key => $category)
        {
            $html = "<tr data-id='$key' data-name='$name' class=''><th scope='row' data-title='Name'>$name</th>";

            foreach ($category as $prop => $value)
            {
                //$values[] = $this->_getValues($id, $prop);
                $html .= "<td data-title='$prop'>";
                $html .= $this->getView()->renderTemplate('commerce-currency-prices/field', [
                    'name' => "ruleCategoriesCP[$key][$prop]",
                    'values' => $this->_getCategoryValues($id, $key, $prop),
                    'errors' => [],
                    'label' => '',
                    'instructions' => '',
                ]);
                $html .= "</td>";
            }
        }
        $html .= "</tr>";

        return $this->asJson([
            'html' => $html
        ]);
    }

    /**
     * @throws HttpException
     */
    public function actionSave(): void
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();

        $fields = CurrencyPrices::$plugin->shipping->getPrices(false);
        $request->setBodyParams(array_merge($request->getBodyParams(), $fields));
        //Craft::dd($request->getBodyParams());

        Craft::$app->runAction('commerce/shipping-rules/save');

    }

    private function _getValues($id, $prop): array
    {
        $values = [];
        $prices = [];
        if ($id) {
            $prices = CurrencyPrices::$plugin->shipping->getPricesByShippingRuleId($id);
        }

        foreach (Commerce::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies() as $currency)
        {
            $val = null;
            foreach ($prices as $price)
            {
                if ($currency->iso == $price['paymentCurrencyIso']) {
                    $val = $price;
                }
            }
            if ($val) {
                $values[$currency->iso] = ['iso'=>$currency->iso, 'price'=>$val[$prop]];
            } else {
                $values[$currency->iso] = ['iso'=>$currency->iso, 'price'=>0];
            }
        }

        return $values;
    }

    private function _getCategoryValues($id, $catId, $prop): array
    {
        $values = [];
        $prices = [];
        if ($id) {
            $prices = CurrencyPrices::$plugin->shipping->getPricesByShippingRuleCategoryId($id, $catId);
        }

        foreach (Commerce::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies() as $currency)
        {
            $val = null;
            foreach ($prices as $price)
            {
                if ($currency->iso == $price['paymentCurrencyIso']) {
                    $val = $price;
                }
            }
            if ($val) {
                $values[$currency->iso] = ['iso'=>$currency->iso, 'price'=>$val[$prop]];
            } else {
                $values[$currency->iso] = ['iso'=>$currency->iso, 'price'=>null];
            }
        }
        //Craft::dd($values);
        return $values;
    }

    /**
     * @throws HttpException
     */
    /*public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        $currency = Commerce::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($id);

        if ($currency && !$currency->primary) {
            Commerce::getInstance()->getPaymentCurrencies()->deletePaymentCurrencyById($currency->id);
            CurrencyPrices::$plugin->service->removeCurrency($currency->iso);
            return $this->asJson(['success' => true]);
        }

        $message = Craft::t('commerce', 'You can not delete that currency.');
        return $this->asErrorJson($message);
    }*/
}
