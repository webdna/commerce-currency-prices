<?php
/**
 * Currency Prices plugin for Craft CMS 3.x
 *
 * Adds payment currency prices to products
 *
 * @link      https://webdna.co.uk/
 * @copyright Copyright (c) 2018 webdna
 */

namespace webdna\commerce\currencyprices;

use webdna\commerce\currencyprices\services\CurrencyPricesService;
use webdna\commerce\currencyprices\services\ShippingService;
use webdna\commerce\currencyprices\services\DiscountsService;
use webdna\commerce\currencyprices\services\AddonsService;
use webdna\commerce\currencyprices\controllers\PaymentCurrenciesController;
use webdna\commerce\currencyprices\adjusters\Shipping;
use webdna\commerce\currencyprices\adjusters\Discount;
use webdna\commerce\currencyprices\twigextensions\CurrencyPricesTwigExtension;
use webdna\commerce\currencyprices\assetbundles\currencyprices\CurrencyPricesAsset;
use webdna\commerce\currencyprices\fields\CurrencyField;
use webdna\commerce\currencyprices\models\ShippingMethod as CurrencyPriceShippingMethod;

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
use craft\services\Fields;

use craft\commerce\events\LineItemEvent;
use craft\commerce\services\LineItems;
use craft\commerce\events\ProcessPaymentEvent;
use craft\commerce\services\Payments;
use craft\commerce\services\OrderAdjustments;
use craft\commerce\services\ShippingMethods;
use craft\commerce\models\ShippingMethod;
use craft\commerce\events\RegisterAvailableShippingMethodsEvent;

use yii\base\Event;
use craft\db\ActiveRecord;
use yii\db\AfterSaveEvent;

/**
 * Class CurrencyPrices
 *
 * @author    webdna
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
	public $schemaVersion = '1.2.0';


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
			'shipping' => ShippingService::class,
			'discounts' => DiscountsService::class,
			'addons' => AddonsService::class,
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
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = CurrencyField::class;
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

			if (Craft::$app->getRequest()->getIsCpRequest()) {

				if ($event->sender instanceof \craft\commerce\elements\Product) {
					$prices = Craft::$app->getRequest()->getBodyParam('prices');
					$newCount = 1;
					if ($prices) {
						foreach ($event->sender->variants as $key => $variant)
						{
							if ($variant->id && isset($prices[$variant->id])) {
								$price = $prices[$variant->id];
							} elseif (isset($prices['new'.$newCount])) {
								$price = $prices['new'.$newCount];
								$newCount++;
							}
							if (isset($price)) {
								foreach ($price as $iso => $value)
								{
									if ($value == '') {
										$event->sender->variants[$key]->addError('prices-'.$iso, 'Price cannot be blank.');
										$event->isValid = false;
									}
								}
							}
						}
					}
				}

				if ($event->sender instanceof \verbb\events\elements\Event) {
					$prices = Craft::$app->getRequest()->getBodyParam('prices');
					$newCount = 1;
					if ($prices) {
						foreach ($event->sender->tickets as $key => $ticket)
						{
							if ($ticket->id && isset($prices[$ticket->id])) {
								$price = $prices[$ticket->id];
							} elseif (isset($prices['new'.$newCount])) {
								$price = $prices['new'.$newCount];
								$newCount++;
							}
							if (isset($price)) {
								foreach ($price as $iso => $value)
								{
									if ($value == '') {
										$event->sender->tickets[$key]->addError('prices-'.$iso, 'Price cannot be blank.');
										$event->isValid = false;
									}
								}
							}
						}
					}
				}

				if ($event->sender instanceof \webdna\commerce\bundles\elements\Bundle) {
					$prices = Craft::$app->getRequest()->getBodyParam('prices');

					if ($prices) {
						foreach ($prices as $iso => $value)
						{
							if ($value == '') {
								$event->sender->addError('prices-'.$iso, 'Price cannot be blank.');
								$event->isValid = false;
							}
						}
					}
				}

				if ($event->sender instanceof \craft\digitalproducts\elements\Product) {
					$prices = Craft::$app->getRequest()->getBodyParam('prices');

					if ($prices) {
						foreach ($prices as $iso => $value)
						{
							if ($value == '') {
								$event->sender->addError('prices-'.$iso, 'Price cannot be blank.');
								$event->isValid = false;
							}
						}
					}
				}

			}
		});

		Event::on(Element::class, Element::EVENT_AFTER_SAVE, function(Event $event) {

			if (Craft::$app->getRequest()->getIsCpRequest()) {

				if ($event->sender instanceof \craft\commerce\elements\Product) {
					$prices = Craft::$app->getRequest()->getBodyParam('prices');
					$count = 0;

					if ($prices && $event->sender->variants) {
						foreach ($prices as $key => $price)
						{
							if ($key != 'new') {
								$this->service->savePrices($event->sender->variants[$count], $price);
								$count++;
							}
						}
					}
				}

				if ($event->sender instanceof \verbb\events\elements\Event) {
					$prices = Craft::$app->getRequest()->getBodyParam('prices');
					$count = 0;
					if ($prices) {
						foreach ($prices as $key => $price)
						{
							if ($key !== 'new') {
								$this->service->savePrices($event->sender->tickets[$count], $price);
								$count++;
							}
						}
					}
				}

				if ($event->sender instanceof \webdna\commerce\bundles\elements\Bundle) {
					$prices = Craft::$app->getRequest()->getBodyParam('prices');
					if ($prices) {
						$this->service->savePrices($event->sender, $prices);
					}
				}

				if ($event->sender instanceof \craft\digitalproducts\elements\Product) {
					$prices = Craft::$app->getRequest()->getBodyParam('prices');
					if ($prices) {
						$this->service->savePrices($event->sender, $prices);
					}
				}

			}

		});

		Event::on(Element::class, Element::EVENT_AFTER_DELETE, function(Event $event) {
			//Craft::dd($event);
			if ($event->sender instanceof \craft\commerce\elements\Variant) {

				$this->service->deletePrices($event->sender->id);
			}
			if ($event->sender instanceof \verbb\events\elements\Ticket) {

				$this->service->deletePrices($event->sender->id);
			}
			if ($event->sender instanceof \webdna\commerce\bundles\elements\Bundle) {

				$this->service->deletePrices($event->sender->id);
			}
			if ($event->sender instanceof \craft\digitalproducts\elements\Product) {

				$this->service->deletePrices($event->sender->id);
			}
		});

		Event::on(ActiveRecord::class, ActiveRecord::EVENT_AFTER_INSERT, function(AfterSaveEvent $event) {
			if ($event->sender instanceof \webdna\commerce\addons\records\Discount) {
				$this->addons->saveAddon($event->sender->id, $this->addons->getPrices());
			}
			if ($event->sender instanceof \craft\commerce\records\Discount) {
				$this->discounts->saveDiscount($event->sender->id, $this->discounts->getPrices());
			}
			if ($event->sender instanceof \craft\commerce\records\ShippingRule) {
				$this->shipping->saveShipping($event->sender->id, $this->shipping->getPrices(), Craft::$app->getRequest()->getBodyParam('ruleCategoriesCP'));
			}
		});
		Event::on(ActiveRecord::class, ActiveRecord::EVENT_AFTER_UPDATE, function(AfterSaveEvent $event) {
			if ($event->sender instanceof \webdna\commerce\addons\records\Discount) {
				$this->addons->saveAddon($event->sender->id, $this->addons->getPrices());
			}
			if ($event->sender instanceof \craft\commerce\records\Discount) {
				$this->discounts->saveDiscount($event->sender->id, $this->discounts->getPrices());
			}
			if ($event->sender instanceof \craft\commerce\records\ShippingRule) {
				$this->shipping->saveShipping($event->sender->id, $this->shipping->getPrices(), Craft::$app->getRequest()->getBodyParam('ruleCategoriesCP'));
			}
		});

		Event::on(LineItems::class, LineItems::EVENT_POPULATE_LINE_ITEM, function(LineItemEvent $event) {

				$order = $event->lineItem->getOrder();
				$paymentCurrency = $order->getPaymentCurrency();
				$primaryCurrency = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

				$prices = $this->service->getPricesByPurchasableId($event->lineItem->purchasable->id);
				if ($prices) {
					$price = $prices[$paymentCurrency];
					$salePrice = $this->service->getSalePrice($event->lineItem->purchasable, $paymentCurrency);

					$event->lineItem->snapshot['priceIn'] = $paymentCurrency;
					$event->lineItem->price = $price;
					$event->lineItem->salePrice = $salePrice;
				}
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
				// if ($type == 'craft\\commerce\\adjusters\\Shipping') {
				// 	$e->types[$key] = Shipping::class;
				// }
				if ($type == 'craft\\commerce\\adjusters\\Discount') {
					$e->types[$key] = Discount::class;
				}
			}
			//Craft::dd($e->types);
		});

		Event::on(ShippingMethods::class, ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, function(RegisterAvailableShippingMethodsEvent $e) {

			$order = $e->order;

			foreach($e->shippingMethods as $key=>$method) {

				if (get_class($method) === ShippingMethod::class) {
					unset($e->shippingMethods[$key]);
				}

				$newMethod = new CurrencyPriceShippingMethod($method);
				$newMethod->order = $order;
				$e->shippingMethods[] = $newMethod;

			}

		});

		/*Event::on(Discount::class, Discount::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED, function(DiscountAdjustmentsEvent $e) {
			Craft::dd($e->adjustments);
			foreach ($e->adjustments as $key => $adjustment)
			{
				$price = (Object) CurrencyPrices::$plugin->service->getPricesByDiscountIdAndCurrency($e->discount->id, $e->order->paymentCurrency);
				if ($price) {
					foreach ($e->order->getLineItems() as $item) {
						if (in_array($item->id, $matchingLineIds, false)) {
							$adjustment = $this->_createOrderAdjustment($this->_discount);
							$adjustment->setLineItem($item);

							$amountPerItem = Currency::round($this->_discount->perItemDiscount * $item->qty);

							//Default is percentage off already discounted price
							$existingLineItemDiscount = $item->getAdjustmentsTotalByType('discount');
							$existingLineItemPrice = ($item->getSubtotal() + $existingLineItemDiscount);
							$amountPercentage = Currency::round($this->_discount->percentDiscount * $existingLineItemPrice);

							if ($this->_discount->percentageOffSubject == DiscountRecord::TYPE_ORIGINAL_SALEPRICE) {
								$amountPercentage = Currency::round($this->_discount->percentDiscount * $item->getSubtotal());
							}

							$adjustment->amount = $amountPerItem + $amountPercentage;

							if ($adjustment->amount != 0) {
								$adjustments[] = $adjustment;
							}
						}
					}
					if ($discount->baseDiscount !== null && $discount->baseDiscount != 0) {
						$baseDiscountAdjustment = $this->_createOrderAdjustment($discount);
						$baseDiscountAdjustment->amount = $discount->baseDiscount;
						$adjustments[] = $baseDiscountAdjustment;
					}
					$e->adjustments[$key]['amount'] =
				}
			}
		});*/

		Craft::$app->view->hook('cp.commerce.product.edit.content', function(array &$context) {
			$view = Craft::$app->getView();
			//Craft::dd($context);
        	return $view->renderTemplate('commerce-currency-prices/prices', ['variants'=>$context['product']->variants]);
		});

		Craft::$app->view->hook('cp.commerce.bundle.edit.price', function(array &$context) {
			$view = Craft::$app->getView();
			//Craft::dd($context);
        	return $view->renderTemplate('commerce-currency-prices/prices-purchasable', ['purchasable'=>$context['bundle']]);
		});

		Craft::$app->view->hook('cp.digital-products.product.edit.details', function(array &$context) {
			$view = Craft::$app->getView();
			//Craft::dd($context);
        	return $view->renderTemplate('commerce-currency-prices/prices-purchasable', ['purchasable'=>$context['product']]);
		});

		Craft::$app->view->hook('cp.events.event.edit.details', function(array &$context) {
			$view = Craft::$app->getView();
			//Craft::dd($context);
        	return $view->renderTemplate('commerce-currency-prices/prices', ['variants'=>$context['event']->tickets]);
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
