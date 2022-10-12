<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace webdna\commerce\currencyprices\models;

use webdna\commerce\currencyprices\CurrencyPrices;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\ShippingRule;
use craft\commerce\base\ShippingMethod as BaseShippingMethod;
use craft\commerce\base\ShippingRuleInterface;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingMethod as ShippingMethodRecord;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;
use yii\behaviors\AttributeTypecastBehavior;

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

    private Order $_order;
    
    
    
    
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
    
        $behaviors['typecast'] = [
            'class' => AttributeTypecastBehavior::class,
            'attributeTypes' => [
                'id' => AttributeTypecastBehavior::TYPE_INTEGER,
                'name' => AttributeTypecastBehavior::TYPE_STRING,
                'handle' => AttributeTypecastBehavior::TYPE_STRING,
                'enabled' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                'isLite' => AttributeTypecastBehavior::TYPE_BOOLEAN,
            ],
        ];
    
        return $behaviors;
    }


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
    public function getId(): ?int
    {
        return $this->id;
    }


    public function setOrder($value): void
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

        foreach($rules as $rule) {
            $rule = $this->updateRule($rule);
            $shippingRules[] = $rule;
        }

        return $shippingRules;
    }
    
    public function getMatchingShippingRule(Order $order): ?ShippingRuleInterface
    {
        $this->setOrder($order);
        
        foreach ($this->getShippingRules() as $rule) {
            /** @var ShippingRuleInterface $rule */
            if ($rule->matchOrder($order)) {
                return $rule;
            }
        }
    
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getIsEnabled(): bool
    {
        return $this->enabled;
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
    public function rules(): array
    {
        return [
            [['name', 'handle'], 'required'],
            [['name'], UniqueValidator::class, 'targetClass' => ShippingMethodRecord::class],
            [['handle'], UniqueValidator::class, 'targetClass' => ShippingMethodRecord::class]
        ];
    }
    
    
    private function updateRule(ShippingRule $rule): ShippingRuleInterface
    {
        $price = CurrencyPrices::$plugin->shipping->getPricesByShippingRuleIdAndCurrency($rule->id, $this->_order->paymentCurrency);
        
        if ($price) {
            $rule = new ShippingRule($rule->getAttributes());
            $price = (Object) $price;
            $rule->minTotal = $price->minTotal;
            $rule->maxTotal = $price->maxTotal;
            $rule->baseRate = $price->baseRate;
            $rule->perItemRate = $price->perItemRate;
            $rule->weightRate = $price->weightRate;
            $rule->percentageRate = $price->percentageRate;
            $rule->minRate = $price->minRate;
            $rule->maxRate = $price->maxRate;
        
            preg_match('/{(\w+)}/', $rule->getDescription(), $matches);
            if (count($matches) > 1) {
                $prop = $matches[1];
                if(property_exists($rule,$prop)) {
                    $currency = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($this->_order->paymentCurrency);
                    $price = Craft::$app->getFormatter()->asCurrency($rule->$prop, $currency, [], [], false);
                    $rule->description = str_replace("{".$prop."}", $price, $rule->getDescription());
                }
            }
        
            $cats = $rule->getShippingRuleCategories();
        
            foreach ($cats as $key => $cat)
            {
                $price = (Object) CurrencyPrices::$plugin->shipping->getPricesByShippingRuleCategoryIdAndCurrency($cat->shippingRuleId, $cat->shippingCategoryId, $this->_order->paymentCurrency);
                $cats[$key]->perItemRate = $price->perItemRate;
                $cats[$key]->weightRate = $price->weightRate;
                $cats[$key]->percentageRate = $price->percentageRate;
            }
            $rule->setShippingRuleCategories($cats);
        }
        
        //Craft::dd($rule);
        
        return $rule;
    }
}
