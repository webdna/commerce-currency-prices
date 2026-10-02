<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\elements\conditions;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\commerce\base\HasStoreInterface;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\helpers\Db;
use craft\helpers\Html;
use yii\db\QueryInterface;

/**
 * Matches an order's payment currency (BR-12). Registered on Commerce's order condition, so a discount,
 * shipping method or shipping rule can hold one fixed amount per currency without a forked adjuster.
 * Commerce applies a fixed amount as entered, whatever the order's currency, so the rule's input says
 * so (BR-13).
 */
class PaymentCurrencyConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('commerce-currency-prices', 'Payment Currency');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * The payment currencies of the condition's store, the store currency among them. A condition with no
     * store of its own (the order index) offers the current store's.
     *
     * @return array<string, string>
     */
    protected function options(): array
    {
        $condition = $this->getCondition();
        $store = $condition instanceof HasStoreInterface
            ? $condition->getStore()
            : Commerce::getInstance()->getStores()->getCurrentStore();

        $options = [];
        $storeCurrency = $store->getCurrency()?->getCode();
        if ($storeCurrency !== null) {
            $options[$storeCurrency] = $storeCurrency;
        }

        foreach (Commerce::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies($store->id) as $paymentCurrency) {
            $options[(string)$paymentCurrency->iso] = (string)$paymentCurrency->iso;
        }

        return $options;
    }

    protected function inputHtml(): string
    {
        return parent::inputHtml() . Html::tag('p', Craft::t('commerce-currency-prices', 'Fixed amounts apply as entered, whatever currency the order is in. Give each currency its own discount or shipping rule with this condition. Percentages need no condition.'), [
            'class' => ['smalltext', 'light'],
        ]);
    }

    public function modifyQuery(QueryInterface $query): void
    {
        /** @var OrderQuery $query */
        $query->andWhere(Db::parseParam('commerce_orders.paymentCurrency', $this->paramValue()));
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        return $this->matchValue($element->getPaymentCurrency());
    }
}
