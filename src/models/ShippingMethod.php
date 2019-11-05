<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace kuriousagency\commerce\currencyprices\models;

use kuriousagency\commerce\currencyprices\CurrencyPrices;
use kuriousagency\commerce\currencyprices\models\ShippingRule;

use Craft;
use craft\commerce\base\ShippingMethod as BaseShippingMethod;
use craft\commerce\Plugin;
use craft\commerce\elements\Order;
use craft\commerce\records\ShippingMethod as ShippingMethodRecord;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;

/**
 * Shipping method model.
 *
 * @property string $cpEditUrl the control panel URL to manage this method and its rules
 * @property bool $isEnabled whether the shipping method is enabled for listing and selection by customers
 * @property array|ShippingRule[] $shippingRules rules that meet the `ShippingRules` interface
 * @property string $type the type of Shipping Method
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */


class ShippingMethod extends BaseShippingMethod
{
	
	private $_order;
	
	
	// Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getType(): string
    {
        return Craft::t('commerce', 'Custom');
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
	}
	
	public function setOrder($value)
    {
        $this->_order = $value;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return (string)$this->name;
    }

    /**
     * @inheritdoc
     */
    public function getHandle(): string
    {		
		return (string)$this->handle;
	}

    /**
     * @inheritdoc
     */
    public function getShippingRules(): array
    {
		
		$shippingRules = [];
		// $order = Plugin::getInstance()->getCarts()->getCart();
		$rules = Plugin::getInstance()->getShippingRules()->getAllShippingRulesByShippingMethodId($this->id);
		
		foreach($rules as $ru) {

			$price = CurrencyPrices::$plugin->shipping->getPricesByShippingRuleIdAndCurrency($ru->id, $this->_order->paymentCurrency);

			$ru = new ShippingRule($ru);
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

				preg_match('/{(\w+)}/', $ru->getDescription(), $matches);
				if (count($matches) > 1) {
					$prop = $matches[1];
					if(property_exists($ru,$prop)) {
						$currency = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($this->_order->paymentCurrency);
						$price = Craft::$app->getFormatter()->asCurrency($ru->$prop, $currency, [], [], false);
						$ru->description = str_replace("{".$prop."}", $price, $ru->getDescription());
					}
				}

				$cats = $ru->getShippingRuleCategories();
				
				foreach ($cats as $key => $cat)
				{
					$price = (Object) CurrencyPrices::$plugin->shipping->getPricesByShippingRuleCategoryIdAndCurrency($cat->shippingRuleId, $cat->shippingCategoryId, $this->_order->paymentCurrency);
					$cats[$key]->perItemRate = $price->perItemRate;
					$cats[$key]->weightRate = $price->weightRate;
					$cats[$key]->percentageRate = $price->percentageRate;
				}
				$ru->setShippingRuleCategories($cats);
			}

			$shippingRules[] = $ru;
		}
		
		return $shippingRules;
	}
	
	// /**
    //  * @inheritdoc
    //  */
    // public function matchOrder(Order $order): bool
    // {
	// 	$this->_order = $order;
		
	// 	/** @var ShippingRuleInterface $rule */
    //     foreach ($this->getShippingRules() as $rule) {
    //         if ($rule->matchOrder($order)) {
    //             return true;
    //         }
    //     }

    //     return false;
    // }

    // /**
    //  * @inheritdoc
    //  */
    // public function getMatchingShippingRule(Order $order)
    // {
	// 	$this->_order = $order;
		
	// 	foreach ($this->getShippingRules() as $rule) {
    //         /** @var ShippingRuleInterface $rule */
    //         if ($rule->matchOrder($order)) {
    //             return $rule;
    //         }
    //     }

    //     return null;
    // }

    /**
     * @inheritdoc
     */
    public function getIsEnabled(): bool
    {
        return (bool)$this->enabled;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/shipping/shippingmethods/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'handle'], 'required'],
            [['name'], UniqueValidator::class, 'targetClass' => ShippingMethodRecord::class],
            [['handle'], UniqueValidator::class, 'targetClass' => ShippingMethodRecord::class]
        ];
    }
}
