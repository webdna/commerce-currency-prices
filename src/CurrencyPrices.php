<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * add multiple currency prices for products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\currencyprices;

use kuriousagency\currencyprices\services\CurrencyPricesService;
use kuriousagency\currencyprices\fields\CurrencyPricesField;
use kuriousagency\currencyprices\twigextensions\CurrencyPricesTwigExtension;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\services\Fields;
use craft\events\RegisterComponentTypesEvent;
use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;

use craft\commerce\events\LineItemEvent;
use craft\commerce\services\LineItems;
use craft\commerce\events\ProcessPaymentEvent;
use craft\commerce\services\Payments;
use yii\base\Event;


/**
 * Class CurrencyPrices
 *
 * @author    Kurious Agency
 * @package   CurrencyPrices
 * @since     1.0.0
 *
 */
class CurrencyPrices extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var CurrencyPrices
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
		self::$plugin = $this;

		$this->setComponents([
            'service' => CurrencyPricesService::class,
		]);

		$this->service->setCurrency('EUR');
		
		Craft::$app->view->registerTwigExtension(new CurrencyPricesTwigExtension());

        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = CurrencyPricesField::class;
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
		);
		
		Event::on(
			LineItems::class, 
			LineItems::EVENT_POPULATE_LINE_ITEM, 
			function(LineItemEvent $e) {

				$order = $e->lineItem->getOrder();
				$paymentCurrency = $order->getPaymentCurrency();
				$primaryCurrency = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

				//if ($paymentCurrency !== $e->lineItem->order->currency) {
					//Craft::dd($paymentCurrency);
					if ($paymentCurrency == $primaryCurrency) {
						$price = $e->lineItem->purchasable->price;
					} else {
						$price = isset($e->lineItem->purchasable->prices) ? $e->lineItem->purchasable->prices->{$paymentCurrency} : $e->lineItem->purchasable->product->prices->{$paymentCurrency};
					}

					//$price = isset($e->lineItem->purchasable->prices) ? $e->lineItem->purchasable->prices->{$paymentCurrency} : $e->lineItem->purchasable->product->prices->{$paymentCurrency};
					$e->lineItem->snapshot['priceIn'] = $paymentCurrency;
					$e->lineItem->price = $price;
					//$e->lineItem->note = $paymentCurrency;
				//}
				  
				//$cart = Commerce::getInstance()->getCarts()->getCart();
				//$cart->setPaymentCurrency($paymentCurrency);
			}
		);

		Event::on(Order::class, Order::EVENT_BEFORE_COMPLETE_ORDER, function(Event $e) {
			$e->sender->currency = $e->sender->paymentCurrency;
		});

		Event::on(Payments::class, Payments::EVENT_BEFORE_PROCESS_PAYMENT_EVENT, function(ProcessPaymentEvent $e) {
			$e->order->currency = $e->order->paymentCurrency;
		});

        Craft::info(
            Craft::t(
                'currency-prices',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

}
