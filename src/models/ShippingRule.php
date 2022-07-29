<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://webdna.co.uk/
 * @copyright Copyright (c) 2018 webdna
 */

namespace webdna\commerce\currencyprices\models;

use webdna\commerce\currencyprices\CurrencyPrices;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\base\ShippingRuleInterface;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingRuleCategory as ShippingRuleCategoryRecord;

/**
 * Shipping rule model
 *
 * @property bool $isEnabled whether this shipping rule enabled for listing and selection
 * @property array $options
 * @property array|ShippingRuleCategory[] $shippingRuleCategories
 * @property mixed $shippingZone
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingRule extends Model implements ShippingRuleInterface
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public int $id;

    /**
     * @var string Name
     */
    public string $name;

    /**
     * @var string Description
     */
    public string $description;

    /**
     * @var int Shipping zone ID
     */
    public int $shippingZoneId;

    /**
     * @var int Shipping method ID
     */
    public int $methodId;

    /**
     * @var int Priority
     */
    public int $priority = 0;

    /**
     * @var bool Enabled
     */
    public bool $enabled = true;

    /**
     * @var int Minimum Quantity
     */
    public int $minQty = 0;

    /**
     * @var int Maximum Quantity
     */
    public int $maxQty = 0;

    /**
     * @var float Minimum total
     */
    public float $minTotal = 0;

    /**
     * @var float Maximum total
     */
    public float $maxTotal = 0;

    /**
     * @var float Minimum Weight
     */
    public float $minWeight = 0;

    /**
     * @var float Maximum Weight
     */
    public float $maxWeight = 0;

    /**
     * @var float Base rate
     */
    public float $baseRate = 0;

    /**
     * @var float Per item rate
     */
    public float $perItemRate = 0;

    /**
     * @var float Percentage rate
     */
    public float $percentageRate = 0;

    /**
     * @var float Weight rate
     */
    public float $weightRate = 0;

    /**
     * @var float Minimum Rate
     */
    public float $minRate = 0;

    /**
     * @var float Maximum rate
     */
    public float $maxRate = 0;

    /**
     * @var bool Is lite shipping rule
     */
    public bool $isLite = 0;

    /**
     * @param Order $order
     * @return array
     */
    private function _getUniqueCategoryIdsInOrder(Order $order): array
    {
        $orderShippingCategories = [];
        foreach ($order->lineItems as $lineItem) {
            $orderShippingCategories[] = $lineItem->shippingCategoryId;
        }
        $orderShippingCategories = array_unique($orderShippingCategories);
        return $orderShippingCategories;
    }

    /**
     * @param $shippingRuleCategories
     * @return array
     */
    private function _getRequiredAndDisallowedCategoriesFromRule($shippingRuleCategories): array
    {
        $disallowedCategories = [];
        $requiredCategories = [];
        foreach ($shippingRuleCategories as $ruleCategory) {
            if ($ruleCategory->condition === ShippingRuleCategoryRecord::CONDITION_DISALLOW) {
                $disallowedCategories[] = $ruleCategory->shippingCategoryId;
            }

            if ($ruleCategory->condition === ShippingRuleCategoryRecord::CONDITION_REQUIRE) {
                $requiredCategories[] = $ruleCategory->shippingCategoryId;
            }
        }
        return [$disallowedCategories, $requiredCategories];
    }

    /**
     * @var ShippingCategory[]
     */
    private array $_shippingRuleCategories;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [
                [
                    'name',
                    'methodId',
                    'priority',
                    'enabled',
                    'minQty',
                    'maxQty',
                    'minTotal',
                    'maxTotal',
                    'minWeight',
                    'maxWeight',
                    'baseRate',
                    'perItemRate',
                    'weightRate',
                    'percentageRate',
                    'minRate',
                    'maxRate',
                ], 'required'
            ]
        ];
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
    public function matchOrder(Order $order): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $shippingRuleCategories = $this->getShippingRuleCategories();

        $orderShippingCategories = $this->_getUniqueCategoryIdsInOrder($order);
        list($disallowedCategories, $requiredCategories) = $this->_getRequiredAndDisallowedCategoriesFromRule($shippingRuleCategories);

        // Does the order have any disallowed categories in the cart?
        $result = array_intersect($orderShippingCategories, $disallowedCategories);
        if (!empty($result)) {
            return false;
        }

        // Does the order have all required categories in the cart?
        $result = !array_diff($requiredCategories, $orderShippingCategories);
        if (!$result) {
            return false;
        }

        $this->getShippingRuleCategories();
        $floatFields = ['minTotal', 'maxTotal', 'minWeight', 'maxWeight'];
        foreach ($floatFields as $field) {
            $this->$field *= 1;
        }

        $shippingZone = $this->getShippingZone();
        $shippingAddress = $order->getShippingAddress();

        if ($shippingZone && !$shippingAddress) {
            return false;
        }

        /** @var ShippingAddressZone $shippingZone */
        if ($shippingZone) {
            if (!Plugin::getInstance()->getAddresses()->addressWithinZone($shippingAddress, $shippingZone)) {
                return false;
            }
        }

        // order qty rules are inclusive (min <= x <= max)
        if ($this->minQty && $this->minQty > $order->totalQty) {
            return false;
        }
        if ($this->maxQty && $this->maxQty < $order->totalQty) {
            return false;
        }

        // order total rules exclude maximum limit (min <= x < max)
        if ($this->minTotal && $this->minTotal > $order->getItemTotal()) {
            return false;
        }
        if ($this->maxTotal && $this->maxTotal <= $order->getItemTotal()) {
            return false;
        }

        // order weight rules exclude maximum limit (min <= x < max)
        if ($this->minWeight && $this->minWeight > $order->totalWeight) {
            return false;
        }
        if ($this->maxWeight && $this->maxWeight <= $order->totalWeight) {
            return false;
        }

        // all rules match
        return true;
    }

    /**
     * @return ShippingRuleCategory[]
     */
    public function getShippingRuleCategories(): array
    {
        if (null === $this->_shippingRuleCategories) {
            $this->_shippingRuleCategories = Plugin::getInstance()->getShippingRuleCategories()->getShippingRuleCategoriesByRuleId((int)$this->id);
        }

        return $this->_shippingRuleCategories;
    }

    /**
     * @param ShippingRuleCategory[] $models
     */
    public function setShippingRuleCategories(array $models): void
    {
        $this->_shippingRuleCategories = $models;
    }

    /**
     * @return mixed
     */
    public function getShippingZone(): ?ShippingAddressZone
    {
        return Plugin::getInstance()->getShippingZones()->getShippingZoneById($this->shippingZoneId);
    }

    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return $this->getAttributes();
    }

    /**
     * @inheritdoc
     */
    public function getPercentageRate($shippingCategoryId = null): float
    {
        return $this->_getRate('percentageRate', $shippingCategoryId);
    }

    /**
     * @inheritdoc
     */
    public function getPerItemRate($shippingCategoryId = null): float
    {
        return $this->_getRate('perItemRate', $shippingCategoryId);
    }

    /**
     * @inheritdoc
     */
    public function getWeightRate($shippingCategoryId = null): float
    {
        return $this->_getRate('weightRate', $shippingCategoryId);
    }

    /**
     * @inheritdoc
     */
    public function getBaseRate(): float
    {
        return (float)$this->baseRate;
    }

    /**@inheritdoc
     */
    public function getMaxRate(): float
    {
        return (float)$this->maxRate;
    }

    /**
     * @inheritdoc
     */
    public function getMinRate(): float
    {
        return (float)$this->minRate;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    // Private Methods
    // =========================================================================

    /**
     * @param $attribute
     * @param $shippingCategoryId
     * @return mixed
     */
    private function _getRate($attribute, $shippingCategoryId = null): mixed
    {
        if (!$shippingCategoryId) {
            return $this->$attribute;
        }

        foreach ($this->getShippingRuleCategories() as $ruleCategory) {
            if ((int)$shippingCategoryId === (int)$ruleCategory->shippingCategoryId && $ruleCategory->$attribute !== null) {
                return $ruleCategory->$attribute;
            }
        }

        return $this->$attribute;
    }
}
