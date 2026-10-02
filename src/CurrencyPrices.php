<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\services\LineItems;
use craft\commerce\services\Purchasables as CommercePurchasables;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\models\FieldLayout;
use craft\services\Fields;
use craft\services\Plugins;
use webdna\commerce\currencyprices\fields\CurrencyField;
use webdna\commerce\currencyprices\services\Prices;
use webdna\commerce\currencyprices\services\Pricing;
use webdna\commerce\currencyprices\services\Purchasables;
use yii\base\Event;

/**
 * Commerce Currency Prices: a price per purchasable, store and payment currency.
 *
 * The 3.x hooks are gone (BR-23): the asset bundle and template hooks that moved inputs into Commerce 4's
 * DOM, the payment-currency screen that added a column per currency, the request-param price saves, and
 * the forked Shipping and Discount adjusters. Phase 1 of the 4.0 spec adds the Commerce 5 replacements.
 *
 * @property-read Prices $prices
 * @property-read Pricing $pricing
 * @property-read Purchasables $purchasables
 * @author webdna
 * @since 1.0.0
 */
class CurrencyPrices extends Plugin
{
    /**
     * @event DefineCurrencyPriceEvent Raised once an item's price in a payment currency is worked out, for a
     * basket line and for the template helpers alike. A handler may change the price and promotional
     * price (BR-8).
     */
    public const EVENT_DEFINE_CURRENCY_PRICE = 'defineCurrencyPrice';

    /**
     * 3.x's last schema version. The 4.0 upgrade migration (task 3.2) raises it.
     */
    public string $schemaVersion = '1.2.0';

    public bool $hasCpSettings = false;

    public bool $hasCpSection = false;

    public static function config(): array
    {
        return [
            'components' => [
                'prices' => ['class' => Prices::class],
                'pricing' => ['class' => Pricing::class],
                'purchasables' => ['class' => Purchasables::class],
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->registerFieldTypes();
        $this->registerPurchasableEditing();
        $this->registerPricing();
    }

    /**
     * Adds the Currency prices element below the price on every variant layout (BR-2). Craft enables the
     * plugin only after this hook, and a layout tab silently drops an element whose plugin is not enabled
     * (`FieldLayoutTab::setElements()`), so it waits for Craft's after-install event. When the install
     * arrives in project config, the layouts it changed arrive with it.
     */
    protected function afterInstall(): void
    {
        parent::afterInstall();

        if (Craft::$app->getProjectConfig()->getIsApplyingExternalChanges()) {
            return;
        }

        $plugins = Craft::$app->getPlugins();
        $handler = function(PluginEvent $event) use ($plugins, &$handler): void {
            if ($event->plugin->handle !== $this->handle) {
                return;
            }
            $plugins->off(Plugins::EVENT_AFTER_INSTALL_PLUGIN, $handler);
            $this->getPurchasables()->addToVariantLayouts();
        };
        $plugins->on(Plugins::EVENT_AFTER_INSTALL_PLUGIN, $handler);
    }

    public function getPrices(): Prices
    {
        /** @var Prices */
        return $this->get('prices');
    }

    public function getPricing(): Pricing
    {
        /** @var Pricing */
        return $this->get('pricing');
    }

    public function getPurchasables(): Purchasables
    {
        /** @var Purchasables */
        return $this->get('purchasables');
    }

    /**
     * The layout element, the posted prices' validation, and their write in the element's own save
     * (BR-2–BR-5). Registered on Commerce's base purchasable, so every purchasable type is covered.
     */
    private function registerPurchasableEditing(): void
    {
        $purchasables = $this->getPurchasables();

        Event::on(FieldLayout::class, FieldLayout::EVENT_DEFINE_NATIVE_FIELDS, [$purchasables, 'defineNativeFields']);
        Event::on(Purchasable::class, Model::EVENT_DEFINE_BEHAVIORS, [$purchasables, 'defineBehaviors']);
        Event::on(Purchasable::class, Model::EVENT_DEFINE_RULES, [$purchasables, 'defineRules']);
        Event::on(Purchasable::class, Element::EVENT_AFTER_SAVE, [$purchasables, 'afterSave']);
    }

    /**
     * Line pricing, order currency and availability for a basket in another currency (BR-7–BR-11).
     */
    private function registerPricing(): void
    {
        $pricing = $this->getPricing();

        Event::on(LineItems::class, LineItems::EVENT_POPULATE_LINE_ITEM, [$pricing, 'populateLineItem']);
        Event::on(CommercePurchasables::class, CommercePurchasables::EVENT_PURCHASABLE_AVAILABLE, [$pricing, 'purchasableAvailable']);
        Event::on(Order::class, Element::EVENT_BEFORE_SAVE, [$pricing, 'beforeSaveOrder']);
        Event::on(Order::class, Order::EVENT_BEFORE_LINE_ITEMS_REFRESHED, [$pricing, 'beforeLineItemsRefreshed']);
        Event::on(Order::class, Order::EVENT_AFTER_LINE_ITEMS_REFRESHED, [$pricing, 'afterLineItemsRefreshed']);

        foreach ([Order::class, LineItem::class] as $class) {
            Event::on($class, Model::EVENT_DEFINE_BEHAVIORS, [$pricing, 'defineOrderBehaviors']);
            Event::on($class, Model::EVENT_DEFINE_FIELDS, [$pricing, 'defineCurrencyFields']);
        }
    }

    /**
     * The Currencies field's class name is stored in sites' project config, so it stays registered.
     */
    private function registerFieldTypes(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = CurrencyField::class;
            }
        );
    }
}
