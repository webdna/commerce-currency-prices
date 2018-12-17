<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\currencyprices\adjusters;

use kuriousagency\commerce\currencyprices\CurrencyPrices;

use Craft;
use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin as Commerce;

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

        $shippingMethods = Commerce::getInstance()->getShippingMethods()->getAvailableShippingMethods($this->_order);

        $shippingMethod = null;

        /** @var ShippingMethod $method */
        foreach ($shippingMethods as $method) {
            if ($method['method']->getIsEnabled() == true && ($method['method']->getHandle() == $this->_order->shippingMethodHandle)) {
                /** @var ShippingMethod $shippingMethod */
                $shippingMethod = $method['method'];
            }
        }

        if ($shippingMethod === null) {
            return [];
		}
		
		$paymentCurrency = Commerce::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($order->paymentCurrency);

        $adjustments = [];

        /** @var ShippingRule $rule */
		//$rule = Commerce::getInstance()->getShippingMethods()->getMatchingShippingRule($this->_order, $shippingMethod);
		$rule = $this->_getMatchingRule($this->_order, $shippingMethod, $paymentCurrency);
		
		
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
					$adjustment->amount = $this->_getShippingAmount($adjustment->amount, $paymentCurrency);
					
                    $adjustment->lineItemId = $item->id;
                    if ($adjustment->amount) {
                        $adjustments[] = $adjustment;
                    }
                    $itemTotalAmount += $adjustment->amount;
                }
            }

            $baseAmount = Currency::round($rule->getBaseRate());
            if ($baseAmount && $baseAmount != 0) {
                $adjustment = $this->_createAdjustment($shippingMethod, $rule);
				//$adjustment->amount = $baseAmount;
				$adjustment->amount = $this->_getShippingAmount($baseAmount, $paymentCurrency);
				$adjustment->description = str_replace('{price}', Craft::$app->getFormatter()->asCurrency($rule->maxTotal, $paymentCurrency->iso, [],[],true), $adjustment->description);
                $adjustments[] = $adjustment;
            }

            $adjustmentToMinimumAmount = 0;
            // Is there a minimum rate and is the total shipping cost currently below it?
            if ($rule->getMinRate() != 0 && (($itemTotalAmount + $baseAmount) < Currency::round($rule->getMinRate()))) {
                $adjustmentToMinimumAmount = Currency::round($rule->getMinRate()) - ($itemTotalAmount + $baseAmount);
                $adjustment = $this->_createAdjustment($shippingMethod, $rule);
				//$adjustment->amount = $adjustmentToMinimumAmount;
				$adjustment->amount = $this->_getShippingAmount($adjustmentToMinimumAmount, $paymentCurrency);
                $adjustment->description .= ' Adjusted to minimum rate';
                $adjustments[] = $adjustment;
            }

            if ($rule->getMaxRate() != 0 && (($itemTotalAmount + $baseAmount + $adjustmentToMinimumAmount) > Currency::round($rule->getMaxRate()))) {
                $adjustmentToMaxAmount = Currency::round($rule->getMaxRate()) - ($itemTotalAmount + $baseAmount + $adjustmentToMinimumAmount);
                $adjustment = $this->_createAdjustment($shippingMethod, $rule);
				//$adjustment->amount = $adjustmentToMaxAmount;
				$adjustment->amount = $this->_getShippingAmount($adjustmentToMaxAmount, $paymentCurrency);
                $adjustment->description .= ' Adjusted to maximum rate';
                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    // Private Methods
	// =========================================================================
	
	private function _getShippingAmount($amount, $currency)
	{
		return Currency::round((ceil(($amount * $currency->rate) * 2) / 2) - 0.01);
	}

	private function _getRuleAmount($amount, $currency)
	{
		return ceil($amount * $currency->rate);
	}

	private function _getMatchingRule($order, $shippingMethod, $currency)
	{
		foreach ($shippingMethod->getShippingRules() as $rule) {
			$rule->minTotal = $this->_getRuleAmount($rule->minTotal, $currency);
			$rule->maxTotal = $this->_getRuleAmount($rule->maxTotal, $currency);
            if ($rule->matchOrder($order)) {
				return $rule;
            }
		}
		return false;
	}

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
        $adjustment->lineItemId = null;
        $adjustment->name = $shippingMethod->getName();
        $adjustment->sourceSnapshot = $rule->getOptions();
        $adjustment->description = $rule->getDescription();

        return $adjustment;
    }
}
