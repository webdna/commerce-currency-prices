<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices;

use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use webdna\commerce\currencyprices\fields\CurrencyField;
use webdna\commerce\currencyprices\services\Prices;
use yii\base\Event;

/**
 * Commerce Currency Prices: a price per purchasable, store and payment currency.
 *
 * The 3.x hooks are gone (BR-23): the asset bundle and template hooks that moved inputs into Commerce 4's
 * DOM, the payment-currency screen that added a column per currency, the request-param price saves, and
 * the forked Shipping and Discount adjusters. Phase 1 of the 4.0 spec adds the Commerce 5 replacements.
 *
 * @property-read Prices $prices
 * @author webdna
 * @since 1.0.0
 */
class CurrencyPrices extends Plugin
{
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
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->registerFieldTypes();
    }

    public function getPrices(): Prices
    {
        /** @var Prices */
        return $this->get('prices');
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
