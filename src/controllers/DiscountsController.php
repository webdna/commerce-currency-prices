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
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Product;
use craft\commerce\models\Discount;
use craft\elements\Category;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\i18n\Locale;
use yii\web\HttpException;
use yii\web\Response;

/**
 * @author    Kurious Agency
 * @package   CurrencyPrices
 * @since     1.0.0
 */
class DiscountsController extends Controller
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

	public function actionGetInputs()
	{
		$this->requireAcceptsJson();
		$id = Craft::$app->getRequest()->getParam('id');
		$name = Craft::$app->getRequest()->getRequiredParam('name');

		$variables = [
			"name" => $name,
			"id" => $name,
			"values" => $this->_getValues($id, $name),
			"errors" => [],
			"label" => Craft::$app->getRequest()->getParam('label'),
			"instructions" => Craft::$app->getRequest()->getParam('instructions'),
		];
		//Craft::dd($variables['values']);

		return $this->asJson([
			'html' => $this->getView()->renderTemplate('commerce-currency-prices/field', $variables)
		]);
	}

	private function _getValues($id, $prop)
	{
		$values = [];
		$prices = [];
		if ($id) {
			$prices = CurrencyPrices::$plugin->service->getPricesByDiscountId($id);
		}
		//Craft::dump($id);
		//Craft::dd($prices);
		foreach (Commerce::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies() as $currency)
		{
			$val = null;
			foreach ($prices as $price)
			{
				if ($currency->iso == $price['paymentCurrencyIso']) {
					$val = $price;
				}
			}
			//Craft::dd($val);
			if ($val) {
				$values[$currency->iso] = ['iso'=>$currency->iso, 'price'=>$val[$prop]];
			} else {
				$values[$currency->iso] = ['iso'=>$currency->iso, 'price'=>0];
			}
		}

		return $values;
	}

	/**
     * @throws HttpException
     */
    public function actionSave()
    {
		$this->requirePostRequest();
		$request = Craft::$app->getRequest();
		$discount = new Discount();

		// Craft::dd($request);

		$iso = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

		$currencyPrices = [];
		$currencyFields = ['baseDiscount', 'perItemDiscount'];
		foreach ($currencyFields as $field)
		{
			//Craft::dd($request->getBodyParam($field));
			$values = $request->getBodyParam($field);

			// Craft::dd($values);
			
			// replace empty values with 0
			if(is_array($values)) {
				$values = array_map(function($value) {
					return $value === "" ? 0 : $value;
				}, $values);
			}

			$discount->$field = (float)$values[$iso] * -1;
			foreach ($values as $key => $price)
			{
				if (!array_key_exists($key, $currencyPrices)) {
					$currencyPrices[$key] = [];
				}
				$currencyPrices[$key][$field] = isset($price) ? $price : 0;
			}
		}
		//CurrencyPrices::$plugin->service->saveShipping($shippingRule->id, $currencyPrices);
		//Craft::dd($currencyPrices);

        $discount->id = $request->getBodyParam('id');
        $discount->name = $request->getBodyParam('name');
        $discount->description = $request->getBodyParam('description');
        $discount->enabled = (bool)$request->getBodyParam('enabled');
        $discount->stopProcessing = (bool)$request->getBodyParam('stopProcessing');
        $discount->purchaseTotal = $request->getBodyParam('purchaseTotal');
        $discount->purchaseQty = $request->getBodyParam('purchaseQty');
        $discount->maxPurchaseQty = $request->getBodyParam('maxPurchaseQty');
        //$discount->baseDiscount = $request->getBodyParam('baseDiscount');
        //$discount->perItemDiscount = $request->getBodyParam('perItemDiscount');
        $discount->percentDiscount = $request->getBodyParam('percentDiscount');
        $discount->percentageOffSubject = $request->getBodyParam('percentageOffSubject');
        $discount->freeShipping = (bool)$request->getBodyParam('freeShipping');
        $discount->excludeOnSale = (bool)$request->getBodyParam('excludeOnSale');
        $discount->code = $request->getBodyParam('code') ?: null;
        $discount->perUserLimit = $request->getBodyParam('perUserLimit');
        $discount->perEmailLimit = $request->getBodyParam('perEmailLimit');
        $discount->totalUseLimit = $request->getBodyParam('totalUseLimit');

        //$discount->baseDiscount = (float)$request->getBodyParam('baseDiscount') * -1;
        //$discount->perItemDiscount = (float)$request->getBodyParam('perItemDiscount') * -1;

        $date = $request->getBodyParam('dateFrom');
        if ($date) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $discount->dateFrom = $dateTime;
        }

        $date = $request->getBodyParam('dateTo');
        if ($date) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $discount->dateTo = $dateTime;
        }

        // Format into a %
        $percentDiscountAmount = $request->getBodyParam('percentDiscount');
        $localeData = Craft::$app->getLocale();
        $percentSign = $localeData->getNumberSymbol(Locale::SYMBOL_PERCENT);
        if (strpos($percentDiscountAmount, $percentSign) || (float)$percentDiscountAmount >= 1) {
            $discount->percentDiscount = (float)$percentDiscountAmount / -100;
        } else {
            $discount->percentDiscount = (float)$percentDiscountAmount * -1;
        }

        $purchasables = [];
        $purchasableGroups = $request->getBodyParam('purchasables') ?: [];
        foreach ($purchasableGroups as $group) {
            if (is_array($group)) {
                array_push($purchasables, ...$group);
            }
        }
        $purchasables = array_unique($purchasables);
        $discount->setPurchasableIds($purchasables);

        $categories = $request->getBodyParam('categories', []);
        if (!$categories) {
            $categories = [];
        }
        $discount->setCategoryIds($categories);

        $groups = $request->getBodyParam('groups', []);
        if (!$groups) {
            $groups = [];
        }
        $discount->setUserGroupIds($groups);

        // Save it
        if (Commerce::getInstance()->getDiscounts()->saveDiscount($discount)
        ) {
			CurrencyPrices::$plugin->service->saveDiscount($discount->id, $currencyPrices);
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Discount saved.'));
            $this->redirectToPostedUrl($discount);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save discount.'));
        }

        // Send the model back to the template
        $variables = [
            'discount' => $discount
        ];
        $this->_populateVariables($variables);

        Craft::$app->getUrlManager()->setRouteParams($variables);
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
	
	private function _populateVariables(&$variables)
    {
        if ($variables['discount']->id) {
            $variables['title'] = $variables['discount']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a Discount');
        }

        //getting user groups map
        if (Craft::$app->getEdition() == Craft::Pro) {
            $groups = Craft::$app->getUserGroups()->getAllGroups();
            $variables['groups'] = ArrayHelper::map($groups, 'id', 'name');
        } else {
            $variables['groups'] = [];
        }

        $variables['categoryElementType'] = Category::class;
        $variables['categories'] = null;
        $categories = $categoryIds = [];

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('categoryIds')) {
            $categoryIds = \explode('|', Craft::$app->getRequest()->getParam('categoryIds'));
        } else {
            $categoryIds = $variables['discount']->getCategoryIds();
        }

        foreach ($categoryIds as $categoryId) {
            $id = (int)$categoryId;
            $categories[] = Craft::$app->getElements()->getElementById($id);
        }

        $variables['categories'] = $categories;

        $variables['purchasables'] = null;


        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('purchasableIds')) {
            $purchasableIdsFromUrl = \explode('|', Craft::$app->getRequest()->getParam('purchasableIds'));
            $purchasableIds = [];
            foreach ($purchasableIdsFromUrl as $purchasableId) {
                $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
                if ($purchasable && $purchasable instanceof Product) {
                    $purchasableIds[] = $purchasable->defaultVariantId;
                } else {
                    $purchasableIds[] = $purchasableId;
                }
            }
        } else {
            $purchasableIds = $variables['discount']->getPurchasableIds();
        }

        $purchasables = [];
        foreach ($variables['discount']->getPurchasableIds() as $purchasableId) {
            $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
            if ($purchasable && $purchasable instanceof PurchasableInterface) {
                $class = \get_class($purchasable);
                $purchasables[$class] = $purchasables[$class] ?? [];
                $purchasables[$class][] = $purchasable;
            }
        }
        $variables['purchasables'] = $purchasables;

        $variables['purchasableTypes'] = [];
        $purchasableTypes = Commerce::getInstance()->getPurchasables()->getAllPurchasableElementTypes();

        /** @var Purchasable $purchasableType */
        foreach ($purchasableTypes as $purchasableType) {
            $variables['purchasableTypes'][] = [
                'name' => $purchasableType::displayName(),
                'elementType' => $purchasableType
            ];
        }
    }
}
