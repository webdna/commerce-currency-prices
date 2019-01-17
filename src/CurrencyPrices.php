<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\currencyprices;

use kuriousagency\commerce\currencyprices\services\CurrencyPricesService;
use kuriousagency\commerce\currencyprices\controllers\PaymentCurrenciesController;
use kuriousagency\commerce\currencyprices\adjusters\Shipping;
use kuriousagency\commerce\currencyprices\twigextensions\CurrencyPricesTwigExtension;
use kuriousagency\commerce\currencyprices\assetbundles\currencyprices\CurrencyPricesAsset;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;
use craft\web\View;
use craft\events\TemplateEvent;
use craft\services\Elements;
use craft\base\Element;
use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;
use craft\events\RegisterComponentTypesEvent;

use craft\commerce\events\LineItemEvent;
use craft\commerce\services\LineItems;
use craft\commerce\events\ProcessPaymentEvent;
use craft\commerce\services\Payments;
use craft\commerce\services\OrderAdjustments;

use yii\base\Event;

/**
 * Class CurrencyPrices
 *
 * @author    Kurious Agency
 * @package   CurrencyPrices
 * @since     1.0.0
 *
 * @property  CurrencyPricesService $currencyPricesService
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

		Craft::$app->view->registerTwigExtension(new CurrencyPricesTwigExtension());
		
		if (Craft::$app->getRequest()->getIsCpRequest()) {
            Event::on(
                View::class,
                View::EVENT_BEFORE_RENDER_TEMPLATE,
                function (TemplateEvent $event) {
                    try {
                        Craft::$app->getView()->registerAssetBundle(CurrencyPricesAsset::class);
                    } catch (InvalidConfigException $e) {
                        Craft::error(
                            'Error registering AssetBundle - '.$e->getMessage(),
                            __METHOD__
                        );
                    }
                }
            );
		}

        // Event::on(
        //     UrlManager::class,
        //     UrlManager::EVENT_REGISTER_SITE_URL_RULES,
        //     function (RegisterUrlRulesEvent $event) {
        //         $event->rules['siteActionTrigger1'] = 'currency-prices/default';
        //     }
        // );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
				$event->rules['commerce-currency-prices/payment-currencies/delete'] = 'commerce-currency-prices/payment-currencies/delete';
				$event->rules['commerce-currency-prices/payment-currencies/all'] = 'commerce-currency-prices/payment-currencies/all';
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

		Event::on(Element::class, Element::EVENT_BEFORE_SAVE, function(Event $event) {
			if ($event->sender instanceof \craft\commerce\elements\Product) {
				$prices = Craft::$app->getRequest()->getBodyParam('prices');
				$newCount = 1;
				foreach ($event->sender->variants as $key => $variant)
				{
					if ($variant->id) {
						$price = $prices[$variant->id];
					} else {
						$price = $prices['new'.$newCount];
						$newCount++;
					}
					foreach ($price as $iso => $value)
					{
						if ($value == '') {
							$event->sender->variants[$key]->addError('prices-'.$iso, 'Price cannot be blank.');
							$event->isValid = false;
						}
					}
				}
			}
		});

		Event::on(Element::class, Element::EVENT_AFTER_SAVE, function(Event $event) {
			if ($event->sender instanceof \craft\commerce\elements\Product) {
				$prices = Craft::$app->getRequest()->getBodyParam('prices');
				$count = 0;
				foreach ($prices as $key => $price)
				{
					if ($key != 'new') {
						$this->service->savePrices($event->sender->variants[$count], $price);
						$count++;
					}
				}
			}
		});

		Event::on(Element::class, Element::EVENT_AFTER_DELETE, function(Event $event) {
			//Craft::dd($event);
			if ($event->sender instanceof \craft\commerce\elements\Variant) {
				
				$this->service->deletePrices($event->sender->id);
			}
		});

		Event::on(LineItems::class, LineItems::EVENT_POPULATE_LINE_ITEM, function(LineItemEvent $event) {

				$order = $event->lineItem->getOrder();
				$paymentCurrency = $order->getPaymentCurrency();
				$primaryCurrency = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

				$prices = $this->service->getPricesByPurchasableId($event->lineItem->purchasable->id);
				$price = $prices[$paymentCurrency];
				
				$salePrice = $this->service->getSalePrice($event->lineItem->purchasable, $paymentCurrency);
				$saleAmount = 0- ($price - $salePrice);

				$event->lineItem->snapshot['priceIn'] = $paymentCurrency;
				$event->lineItem->price = $price;
				$event->lineItem->saleAmount = $saleAmount;
				$event->lineItem->salePrice = $salePrice;
				//Craft::dd($event->lineItem);
			}
		);

		Event::on(Order::class, Order::EVENT_BEFORE_COMPLETE_ORDER, function(Event $event) {
			$event->sender->currency = $event->sender->paymentCurrency;
		});

		Event::on(Payments::class, Payments::EVENT_BEFORE_PROCESS_PAYMENT, function(ProcessPaymentEvent $event) {
			$event->order->currency = $event->order->paymentCurrency;
		});

		Event::on(OrderAdjustments::class, OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS, function(RegisterComponentTypesEvent $e) {
			foreach ($e->types as $key => $type)
			{
				if ($type == 'craft\\commerce\\adjusters\\Shipping') {
					$e->types[$key] = Shipping::class;
				}
			}
		});

		
		Craft::$app->view->hook('cp.commerce.product.edit.details', function(array &$context) {
			$view = Craft::$app->getView();
			//Craft::dd($context);
        	return $view->renderTemplate('commerce-currency-prices/prices', ['variants'=>$context['product']->variants]);
		});

        Craft::info(
            Craft::t(
                'commerce-currency-prices',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

}
