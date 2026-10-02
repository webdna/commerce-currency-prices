# Currency Prices for Craft Commerce

Sell from one Craft Commerce store in several currencies, at prices you set for each currency rather than prices converted by an exchange rate.

A shopper who pays in dollars sees and pays the dollar price you typed, not the pound price converted at today's rate. It works for variants, Commerce Bundles bundles, Digital Products and Events tickets.

## Requirements

- Craft CMS 5.0 or later
- Craft Commerce 5.3 or later
- PHP 8.2 or later

Craft 4 and Commerce 4 sites stay on the 3.x line. To move from 3.x, see [Upgrading from 3.x](#upgrading-from-3x).

## Installation

```bash
composer require webdna/commerce-currency-prices
php craft plugin/install commerce-currency-prices
```

Or install it from the Plugin Store in the control panel.

## Setup

1. **Add your payment currencies.** In **Commerce → Store Settings → Payment Currencies**, add each currency the store should accept besides its own. The plugin never reads a currency's exchange rate, so its value does not matter to prices.
2. **Check the field layouts.** On install the plugin adds a **Currency prices** element below the price on every existing variant field layout. For anything else, add it to the layout yourself from the field layout designer:
   - product types created after install (variant layout)
   - Commerce Bundles 3 bundle types
   - Digital Products product types
   - Events ticket-type layouts. A ticket has no layout of its own, so its ticket type holds the prices for every session's tickets.
3. **Enter the prices.** The element shows one price per payment currency, next to the item's normal price. If the store accepts only its own currency, it says so instead.

How prices are kept:

- **Blank means not for sale in that currency.** The item cannot be added to a basket in that currency, and a basket that switches to that currency loses it, with Commerce's usual "… is no longer available." notice. It is never sold at 0 or at a converted price.
- **0 is a real price.** Use it for an item that is free in one currency.
- **Prices belong to a store.** With several stores, edit an item from a site of the store whose prices you want to change. Each store's prices are separate.
- Drafts, revisions and duplicates keep their own copy of the prices, as they do for the rest of the item.

Editing currency prices needs the same permission as editing the item. There are no new permissions.

## How baskets are priced

Your site decides which currency a shopper pays in, whether by country, by account or with a switcher. Set it the usual Commerce way, by posting `paymentCurrency` to `commerce/cart/update-cart`. The plugin prices whatever currency the basket is in.

- **In the store's own currency** the basket is exactly as Commerce makes it.
- **In another currency** each line costs the price you typed for that currency.
- **Promotions follow by ratio.** When a catalog pricing rule, a sale or customer-specific pricing (such as trade prices) takes 10% off the store-currency price, the currency price drops by the same 10%. Promotional prices follow the same way. You do not set promotions up twice.
- **The order is recorded in the currency the shopper pays in.** While an order is a basket the plugin keeps its currency equal to its payment currency, so the total, the receipt and the card charge always agree, and Commerce never converts by exchange rate. A completed order is never changed.

Each line's snapshot records how its price was reached, under `currencyPrices`: the currency, the currency price and the ratio.

## Discounts and shipping in each currency

Percentage discounts work in every currency without any extra set-up.

**Fixed amounts do not convert.** A discount or shipping rule takes off or charges its amount exactly as you entered it, whatever currency the order is in. A £10-off discount with no currency condition also takes 10 off a dollar order: $10, not the dollar value of £10. The plugin does not warn you about this.

So give each currency its own discount or shipping rule, using the **Payment Currency** condition the plugin adds:

1. Create the discount, shipping method or shipping rule as usual, with its amounts in one currency, for example £10 off orders over £50.
2. In its conditions, add **Payment Currency** → **is one of** → **GBP**.
3. Duplicate it for each other currency. Change the amounts and the condition: $12 off orders over $65, **is one of USD**.

The condition offers the store's payment currencies, including the store's own. **is not one of** works too, for example one rule for every currency except USD. The condition is also available on the order index, so you can filter orders by the currency they were paid in.

## Templates

```twig
{{ variant|currencyPrice('USD') }}
{{ variant|currencyPromotionalPrice('USD') }}
{{ variant|currencySalePrice('USD') }}

{# in the basket's currency #}
{{ variant|currencySalePrice(cart.paymentCurrency) }}

{# formatted, e.g. "$120.00"; a further true strips trailing zeros, e.g. "$120" #}
{{ variant|currencyPrice('USD', true) }}
{{ variant|currencyPrice('USD', true, true) }}
```

- `currencyPrice` gives the price, `currencyPromotionalPrice` gives the promotional price (or null when there is none), and `currencySalePrice` gives the promotional price, or else the price.
- They give the same figure the basket will charge for the current customer, promotions included.
- For the store's own currency they give Commerce's own price.
- For a currency the item has no price in they give `null`. Test for it to show "not available in this currency".
- For a currency the store does not accept they give `null` and log a warning.
- A number is returned unless you pass `true` for formatting.

## Changing a price in code

To adjust a currency price, for example to round it, listen for `CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE`. It is raised for basket lines and for the Twig filters, so a page and the basket show the same figure.

```php
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\events\DefineCurrencyPriceEvent;
use yii\base\Event;

Event::on(CurrencyPrices::class, CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE, function(DefineCurrencyPriceEvent $event) {
    // $event->purchasable, $event->currency, and $event->order / $event->lineItem in a basket (null on a page)
    $event->price = ceil($event->price);
    if ($event->promotionalPrice !== null) {
        $event->promotionalPrice = ceil($event->promotionalPrice);
    }
});
```

A negative price is set to 0 and logged. A promotional price that is not below the price is dropped, as Commerce does.

**If your site sets line prices itself** in its own `LineItems::EVENT_POPULATE_LINE_ITEM` handler, the order the handlers run in decides the price:

- A handler attached after plugins load, which is the usual way for a module (`Craft::$app->onInit()`), runs after the plugin. Its price is the one that stands.
- A handler that runs before the plugin is treated like a promotion. The price it sets moves the currency price by ratio.

## Currencies field

The **Currencies** field type gives a dropdown of the payment currencies of the current site's store, for example to store a customer's preferred currency. Values saved under 3.x keep working.

## Importing prices from your own fields

If your site already keeps currency prices in its own number fields, the `import` command copies them in:

```bash
# dry run: shows what would change and writes nothing
php craft commerce-currency-prices/import --field=priceUsd --currency=USD

# write the prices
php craft commerce-currency-prices/import --field=priceUsd --currency=USD --apply
```

- It reads the field from each purchasable, or from its product (its owner) when the purchasable's own layout lacks the field.
- `--store=<handle>` picks the store. The default is the primary store. The currency must be one of that store's payment currencies, and not its own currency.
- An empty field is skipped. **A 0 is skipped too**, because a field that defaults to 0 usually means "no price". Add `--include-zero` to import zeros.
- It prints how many items were written, unchanged, overwritten, skipped as empty, skipped as zero, and failed. A field holding text or a negative number fails, with the item's id, and the rest carry on. Any failure makes the exit code 1.

Run it once per field and currency.

## Upgrading from 3.x

3.x is the Craft 4 version. Upgrading keeps every product price. Commerce 5 rebuilt discounts and shipping, so 3.x's per-currency discount and shipping amounts cannot be carried across. The upgrade keeps them in the database and a report lists each one, so you can set it up again as a per-currency rule.

1. **On Craft 4, update to 3.0.0-beta.6 or later and run `php craft up`.** Earlier 3.x versions add database foreign keys that stop Commerce 5's upgrade with "Cannot drop index … needed in a foreign key constraint". 3.0.0-beta.6 removes them and changes nothing else.

   ```bash
   composer require webdna/commerce-currency-prices:3.0.0-beta.6
   php craft up
   ```

2. **Upgrade to Craft 5 and Commerce 5**, following Craft's and Commerce's upgrade guides, and require 4.x of this plugin in the same Composer update:

   ```bash
   composer require craftcms/cms:^5.0 craftcms/commerce:^5.3 webdna/commerce-currency-prices:^4.0 --no-update
   composer update
   php craft up
   ```

   If the first `php craft up` stops because the project config files were written for different versions, run `php craft project-config/write` and then `php craft up` again.

   The plugin's migration waits for Commerce's to finish, then copies every 3.x price into the primary store. It prints how many it copied, skipped and found invalid. It skips each item's copy of its store-currency price (that is the item's normal price), prices in currencies the store no longer accepts, and items that no longer exist. Running it again copies nothing twice. It also adds the **Currency prices** element to every variant layout that lacks it.

3. **List the old discount and shipping amounts:**

   ```bash
   php craft commerce-currency-prices/legacy/report
   ```

   It lists each 3.x discount, shipping rule and shipping-category rate that had per-currency amounts, with its name, its control panel URL (or "deleted"), and one line per currency. It changes nothing. Set each one up again as a discount or rule per currency, as described in [Discounts and shipping in each currency](#discounts-and-shipping-in-each-currency). Commerce Addons discounts are listed too, but Commerce Addons has no Craft 5 version.

4. **Check your templates** against the changes listed in the [changelog](CHANGELOG.md) for 4.0.0. In particular, the Twig filters now return a number unless you pass `true` to format it, and `currencyAddonDiscountPrice`, `currencyAddonDiscountPrices` and `localizationNormalizeNumber` are gone.

The 3.x tables (`commerce_currencyprices`, `commerce_discounts_currencyprices`, `commerce_shippingrules_currencyprices`, `commerce_shippingrule_categories_currencyprices` and, with Commerce Addons, `addons_discounts_currencyprices`) are left in the database for the report. Drop them yourself once you have rebuilt your rules.

---

Brought to you by [webdna](https://webdna.co.uk/)
