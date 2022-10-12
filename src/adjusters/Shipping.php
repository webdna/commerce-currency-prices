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
use webdna\commerce\currencyprices\models\ShippingMethod;

use Craft;
use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\OrderAdjustment;
//use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;

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

    public const ADJUSTMENT_TYPE = 'shipping';

    // Properties
    // =========================================================================

    /**
     * @var Order
     */
    private Order $_order;
    
    /**
     * @var bool
     */
    private bool $_isEstimated = false;
    
    /**
     * Temporary feature flag for testing
     *
     * @var bool
     */
    private bool $_consolidateShippingToSingleAdjustment = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function adjust(Order $order): array
    {
        $this->_order = $order;
        $this->_isEstimated = (!$order->shippingAddressId && $order->estimatedShippingAddressId);
        
        $shippingMethod = $order->getShippingMethod();
        $lineItems = $order->getLineItems();

        if ($shippingMethod === null) {
            return [];
        }

        $nonShippableItems = [];
        
        foreach ($lineItems as $item) {
            $purchasable = $item->getPurchasable();
            if ($purchasable && !Plugin::getInstance()->getPurchasables()->isPurchasableShippable($purchasable)) {
                $nonShippableItems[$item->id] = $item->id;
            }
        }
        
        // Are all line items non shippable items? No shipping cost.
        if (count($lineItems) == count($nonShippableItems)) {
            return [];
        }

        $adjustments = [];
        
        $discounts = Plugin::getInstance()->getDiscounts()->getAllActiveDiscounts($order);

        // Check to see if we have shipping related discounts
        $hasOrderLevelShippingRelatedDiscounts = (bool)ArrayHelper::firstWhere($discounts, 'hasFreeShippingForOrder', true, false);
        $hasLineItemLevelShippingRelatedDiscounts = (bool)ArrayHelper::firstWhere($discounts, 'hasFreeShippingForMatchingItems', true, false);
        
        $shippingMethod = new ShippingMethod($shippingMethod->getAttributes());
        //$shippingMethod->order($this->_order);
        $rule = $shippingMethod->getMatchingShippingRule($this->_order);

        //Craft::dd($rule);

        if ($rule) {
            $itemTotalAmount = 0;
            
            // Check for order level discounts for shipping
            $hasDiscountRemoveShippingCosts = false;
            if ($hasOrderLevelShippingRelatedDiscounts) {
                foreach ($discounts as $discount) {
                    $matchedOrder = Plugin::getInstance()->getDiscounts()->matchOrder($this->_order, $discount);
            
                    if ($discount->hasFreeShippingForOrder && $matchedOrder) {
                        $hasDiscountRemoveShippingCosts = true;
                        break;
                    }
            
                    if ($matchedOrder && $discount->stopProcessing) {
                        break;
                    }
                }
            }
            
            if (!$hasDiscountRemoveShippingCosts) {
                //checking items shipping categories
                foreach ($order->getLineItems() as $item) {
                    // Lets match the discount now for free shipped items and not even make a shipping cost for the line item.
                    $hasFreeShippingFromDiscount = false;
                    if ($hasLineItemLevelShippingRelatedDiscounts) {
                        foreach ($discounts as $discount) {
                            $matchedLineItem = Plugin::getInstance()->getDiscounts()->matchLineItem($item, $discount, true);
            
                            if ($discount->hasFreeShippingForMatchingItems && $matchedLineItem) {
                                $hasFreeShippingFromDiscount = true;
                                break;
                            }
            
                            if ($matchedLineItem && $discount->stopProcessing) {
                                break;
                            }
                        }
                    }
            
                    $freeShippingFlagOnProduct = $item->purchasable->hasFreeShipping();
                    $shippable = Plugin::getInstance()->getPurchasables()->isPurchasableShippable($item->getPurchasable());
                    if (!$freeShippingFlagOnProduct && !$hasFreeShippingFromDiscount && $shippable) {
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
                if ($baseAmount && $baseAmount != 0) {
                    $adjustment = $this->_createAdjustment($shippingMethod, $rule);
                    $adjustment->amount = $baseAmount;
                    $adjustments[] = $adjustment;
                }
            
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
        }
        
        if ($this->_consolidateShippingToSingleAdjustment) {
            $amount = 0;
            foreach ($adjustments as $adjustment) {
                $amount += $adjustment->amount;
            }
        
            //preparing model
            $adjustment = new OrderAdjustment();
            $adjustment->type = self::ADJUSTMENT_TYPE;
            $adjustment->setOrder($this->_order);
            $adjustment->name = $shippingMethod->getName();
            $adjustment->amount = $amount;
            $adjustment->description = $rule->getDescription();
            $adjustment->isEstimated = $this->_isEstimated;
            $adjustment->setSourceSnapshot([]);
        
            return [$adjustment];
        }

        return $adjustments;
    }

    // Private Methods
    // =========================================================================
    
    private function getMatchingShippingRule(Order $order): ?ShippingRule
    {
        foreach ($order->getShippingMethod()->getShippingRules() as $ru) {
            $ru = new ShippingRule($ru->getAttributes());
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
        
            if ($ru->matchOrder($order)) {
                return $ru;
            }
        }
        
        return null;
    }

    /**
     * @param ShippingMethod $shippingMethod
     * @param ShippingRule $rule
     * @return OrderAdjustment
     */
    private function _createAdjustment(ShippingMethod $shippingMethod, ShippingRule $rule): OrderAdjustment
    {
        //preparing model
        $adjustment = new OrderAdjustment;
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->setOrder($this->_order);
        $adjustment->name = $shippingMethod->getName();
        $adjustment->description = $rule->getDescription();
        $adjustment->isEstimated = $this->_isEstimated;
        $adjustment->sourceSnapshot = $rule->toArray();
        
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
