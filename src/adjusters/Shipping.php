<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://webdna.co.uk/
 * @copyright Copyright (c) 2018 webdna
 */

namespace webdna\commerce\currencyprices\adjusters;

use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\models\ShippingRule;

use Craft;
use craft\base\Component;
use craft\commerce\Plugin;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\ShippingMethod;

/**
 * Tax Adjustments
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Shipping extends Component implements AdjusterInterface
{
    // Constants
    // =========================================================================

    const ADJUSTMENT_TYPE = 'shipping';

    // Properties
    // =========================================================================

    /**
     * @var
     */
    private $_order;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function adjust(Order $order): array
    {
        $this->_order = $order;

		// $shippingMethod = $order->getShippingMethod();
		$shippingMethods = Plugin::getInstance()->getShippingMethods()->getAllShippingMethods();
		//raft::dd($shippingMethod);

        if ($shippingMethods === null) {
            return [];
		}

		if (count($order->lineItems) == 0) {
			return [];
		}

		$adjustments = [];

        /** @var ShippingRule $rule */
		//$rule = $shippingMethod->getMatchingShippingRule($this->_order);
		$rule = null;

		foreach($shippingMethods as $method) {
			foreach ($method->getShippingRules() as $ru) {
				$ru = new ShippingRule($ru);
				$price = CurrencyPrices::$plugin->shipping->getPricesByShippingRuleIdAndCurrency($ru->id, $order->paymentCurrency);
				if ($price) {
					$price = (Object) $price;
					$ru->minTotal = $price->minTotal;
					$ru->maxTotal = $price->maxTotal;
					$ru->baseRate = $price->baseRate;
					$ru->perItemRate = $price->perItemRate;
					$ru->weightRate = $price->weightRate;
					$ru->percentageRate = $price->percentageRate;
					$ru->minRate = $price->minRate;
					$ru->maxRate = $price->maxRate;

					/* TODO: rule categories prices */
					$cats = $ru->getShippingRuleCategories();

					foreach ($cats as $key => $cat)
					{
						$price = (Object) CurrencyPrices::$plugin->shipping->getPricesByShippingRuleCategoryIdAndCurrency($cat->shippingRuleId, $cat->shippingCategoryId, $order->paymentCurrency);
						$cats[$key]->perItemRate = $price->perItemRate;
						$cats[$key]->weightRate = $price->weightRate;
						$cats[$key]->percentageRate = $price->percentageRate;
					}
					$ru->setShippingRuleCategories($cats);
				}
	//Craft::dump($ru->enabled ? 'yes' : 'no');
				if ($ru->matchOrder($order)) {
					$rule = $ru;
					$shippingMethod = $method;
					continue;
				}
			}
		}


		// Craft::dd($rule);

        if ($rule) {
            $itemTotalAmount = 0;
            //checking items shipping categories
            foreach ($order->getLineItems() as $item) {
                if (!$item->purchasable->hasFreeShipping()) {
                    $adjustment = $this->_createAdjustment($shippingMethod, $rule);

                    $percentageRate = $rule->getPercentageRate($item->shippingCategoryId);
                    $perItemRate = $rule->getPerItemRate($item->shippingCategoryId);
                    $weightRate = $rule->getWeightRate($item->shippingCategoryId);

                    $percentageAmount = $item->getSubtotal() * $percentageRate;
                    $perItemAmount = $item->qty * $perItemRate;
                    $weightAmount = ($item->weight * $item->qty) * $weightRate;

                    $adjustment->amount = Currency::round($percentageAmount + $perItemAmount + $weightAmount);
                    $adjustment->setLineItem($item);
                    if ($adjustment->amount) {
                        $adjustments[] = $adjustment;
                    }
                    $itemTotalAmount += $adjustment->amount;
                }
            }

            $baseAmount = Currency::round($rule->getBaseRate());
            //if ($baseAmount && $baseAmount != 0) {
                $adjustment = $this->_createAdjustment($shippingMethod, $rule);
                $adjustment->amount = $baseAmount;
                $adjustments[] = $adjustment;
            //}

            $adjustmentToMinimumAmount = 0;
            // Is there a minimum rate and is the total shipping cost currently below it?
            if ($rule->getMinRate() != 0 && (($itemTotalAmount + $baseAmount) < Currency::round($rule->getMinRate()))) {
                $adjustmentToMinimumAmount = Currency::round($rule->getMinRate()) - ($itemTotalAmount + $baseAmount);
                $adjustment = $this->_createAdjustment($shippingMethod, $rule);
                $adjustment->amount = $adjustmentToMinimumAmount;
                $adjustment->description .= ' Adjusted to minimum rate';
                $adjustments[] = $adjustment;
            }

            if ($rule->getMaxRate() != 0 && (($itemTotalAmount + $baseAmount + $adjustmentToMinimumAmount) > Currency::round($rule->getMaxRate()))) {
                $adjustmentToMaxAmount = Currency::round($rule->getMaxRate()) - ($itemTotalAmount + $baseAmount + $adjustmentToMinimumAmount);
                $adjustment = $this->_createAdjustment($shippingMethod, $rule);
                $adjustment->amount = $adjustmentToMaxAmount;
                $adjustment->description .= ' Adjusted to maximum rate';
                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    // Private Methods
    // =========================================================================

    /**
     * @param ShippingMethod $shippingMethod
     * @param ShippingRule $rule
     * @return OrderAdjustment
     */
    private function _createAdjustment($shippingMethod, $rule): OrderAdjustment
    {
        //preparing model
        $adjustment = new OrderAdjustment;
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->orderId = $this->_order->id;
        $adjustment->name = $shippingMethod->getName();
		$adjustment->sourceSnapshot = $rule->getOptions();
		$adjustment->description = $rule->getDescription();

		preg_match('/{(\w+)}/', $rule->getDescription(), $matches);
		if (count($matches) > 1) {
			$prop = $matches[1];

			if(property_exists($rule,$prop)) {
				$currency = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($this->_order->paymentCurrency);
				$price = Craft::$app->getFormatter()->asCurrency($rule->$prop, $currency, [], [], false);
				$adjustment->description = str_replace("{".$prop."}", $price, $rule->getDescription());
			}

		}

        return $adjustment;
	}

}
