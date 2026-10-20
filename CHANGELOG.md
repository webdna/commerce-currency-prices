# Currency Prices Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 4.0.0 - 2026-10-20

4.0.0, for Craft CMS 5 and Craft Commerce 5. Craft 4 sites stay on 3.x. Upgrade from 3.0.0 (or 3.0.0-beta.6) or later. The README's "Upgrading from 3.x" has the steps.

### Added
- Craft CMS 5 and Craft Commerce 5 compatibility, including multiple stores. Prices are kept per item, per store and per payment currency.
- A **Currency prices** field layout element, with one price per payment currency of the store beside the item's own price. Install adds it below the price on every existing variant layout. Add it yourself to other product types' variant layouts, Digital Products product types and Events ticket-type layouts.
- Currency prices for Digital Products 4.1 products and Events 3.1 tickets. An Events ticket is priced from its ticket type.
- A **Payment Currency** order condition rule (*is one of*, *is not one of*) for discounts, shipping methods, shipping rules and the order index. Use one discount or shipping rule per currency for fixed amounts.
- Promotions reach the currency price by ratio: a catalog pricing rule, sale or customer-specific price that takes 10% off the store-currency price takes 10% off the currency price.
- `CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE` (`DefineCurrencyPriceEvent`), raised for basket lines and the Twig filters, so a site can adjust a currency price, for example to round it. A negative price is set to 0 and logged.
- `currencyPromotionalPrice` Twig filter.
- Each line's snapshot records `currencyPrices`: the currency, the currency price and the ratio. It replaces 3.x's `priceIn`.
- `php craft commerce-currency-prices/import --field=<handle> --currency=<ISO> [--store=<handle>] [--apply] [--include-zero]` copies prices from a site's own number fields. Without `--apply` it is a dry run, prints the same counts and says nothing was written. Empty fields and zeros are skipped (zeros unless `--include-zero`). An item whose layout lacks the field, and whose owner's layout lacks it too, counts as skipped as empty. Any failed item makes the exit code 1.
- `php craft commerce-currency-prices/legacy/report` lists the 3.x per-currency discount, shipping rule, shipping-category rate and Commerce Addons discount amounts that the upgrade cannot carry across. Each record is listed once, with its name and control panel URL (or "deleted"), followed by one line per currency. Zero amounts are left out, since 3.x saved 0 for a blank input. It changes nothing and always exits 0.
- An upgrade migration that copies 3.x's purchasable prices into the primary store. Each item's copy of its store-currency price, prices in currencies the store no longer accepts, and deleted items are skipped. It prints counts of prices copied, skipped and invalid.

### Changed
- Now requires Craft CMS 5.0+, Craft Commerce 5.3+ and PHP 8.2+.
- An item with no price in the basket's currency is no longer for sale in that currency. It cannot be added, and a basket that switches to that currency loses it with Commerce's "… is no longer available." notice. 3.x left the store-currency price on the line, which Commerce then converted at the exchange rate.
- While an order is a basket, its currency is kept equal to its payment currency, so Commerce never converts by exchange rate and the exchange rate is never read. 3.x set it at completion and at payment. You no longer need to set every rate to 1.
- Formatted amounts (`…AsCurrency`) on a basket, its lines and its shipping method options are in the basket's currency. Commerce formats them in the store's currency.
- The Twig filters `currencyPrice` and `currencySalePrice` now return a number by default. Pass `true` as the second argument for a formatted price such as "$120.00", which used to be the default. They return `null` when the item has no price in that currency, or when the store does not accept the currency (which also logs a warning). For the store's own currency they return Commerce's price for the current customer.
- The **Currencies** field offers the payment currencies of the current site's store. Its class name and saved values are unchanged.
- Prices are stored in a new `currencyprices_prices` table, one row per item, store and currency, instead of a column per currency.

### Removed
- Per-currency amounts on discounts, shipping rules and shipping-category rates, and the Discount and Shipping adjusters and ShippingRule and ShippingMethod models that 3.x forked from Commerce 4 to apply them. Use one discount or shipping rule per currency with the Payment Currency condition instead. **A fixed amount on a discount or shipping rule without that condition applies as entered, whatever currency the order is in.** The upgrade keeps the 3.x amounts in the database and `legacy/report` lists them.
- Commerce Addons support, including the `currencyAddonDiscountPrice` and `currencyAddonDiscountPrices` Twig filters. Commerce Addons has no Craft 5 release.
- The `localizationNormalizeNumber` Twig filter.
- Templates that still use `currencyAddonDiscountPrice`, `currencyAddonDiscountPrices` or `localizationNormalizeNumber` fail with Twig's unknown-filter error.
- The plugin's controllers and their actions: `prices`, `discounts`, `shipping`, `addons`, `payment-currencies` and `tickets`.
- The plugin's hooks on the payment currencies screen, which added a database column for each currency. Commerce's own payment currency screens are used unchanged.
- The control panel asset bundles and templates that moved hidden inputs next to Commerce 4's price fields.
- The `service`, `shipping`, `discounts` and `addons` plugin components, their records, and the `CurrencyPrices::$plugin` static. Use `CurrencyPrices::getInstance()->getPrices()`.
- `CurrencyPricesService::getSalePrice()`, which re-implemented Commerce 4's sales. Promotions now follow by ratio.

## 3.0.0 - 2026-10-20
### Changed
- The stable Craft 4 / Commerce 4 release. The same code as 3.0.0-beta.6. Craft 5 / Commerce 5 support is coming in Currency Prices 4.

## 3.0.0-beta.6 - 2026-10-02
### Fixed
- Commerce 5's upgrade no longer fails with “Cannot drop index … needed in a foreign key constraint”. A migration drops the foreign keys from the currency discount and shipping tables to `commerce_paymentcurrencies.iso`, and new installs no longer add them. Update to this version and run `php craft up` before upgrading to Craft 5.

### Changed
- Deleting a payment currency still deletes its discount and shipping amounts, which the plugin now does itself. Changing a payment currency's ISO code now moves them to the new code.

## 3.0.0-beta.5 - 2025-03-26
### Changed
- paymentCurrencyIso column type
- TypeError fix - thanks @garrill

## 3.0.0-beta.4 - 2022-10-13

### Changed
- Icon

## 3.0.0-beta.3 - 2022-10-13

### Fixed
- price suffix

## 3.0.0-beta.2 - 2022-10-13

### Fixed
- template fix


## 3.0.0-beta.1 - 2022-07-07

### Added

- Craft 4 compatibility.
- Commerce 4 compatibility.

### Changed

- Now requires Craft 4+.
- Now requires Craft Commerce 4+.

## 2.7.5 - 2022-07-07
### Changed
- Changed namespaces
- New Icon

## 2.7.4 - 2022-06-10
### Changed
- Change of ownership

## 2.7.3 - 2021-08-16

### Fixed

- error when applying a Commerce discount ([#21](https://github.com/webdna/commerce-currency-prices/issues/21))

## 2.7.2 - 2021-07-20

### Fixed

- error when setting min/max order value on a shipping rule

## 2.7.1 - 2020-12-18

### Fixed

- error when duplicating a product from a product page

## 2.7.0 - 2020-12-18

### Added

- Craft 3.5 compatibility.
- Commerce 3 compatibility.

### Added

- Now requires Craft 3.5+.
- Now requires Craft Commerce 3.2+.

### Fixed
- Fix a bug when trying to save a price with a comma

## 2.6.7 - 2020-10-20

### Fixed

- default value of 0 when adding a new currency

## 2.6.6 - 2020-09-22

### Fixed

- an issue when saving products.

## 2.6.5 - 2020-09-21

### Fixed

- Adds primary key to commerce_currencyprices table

## 2.6.4 - 2020-02-20

### Fixed

- Addons foreign key bug - thanks @boboldehampsink

## 2.6.3 - 2020-02-20

### Fixed

- Fixed a bug where purchasables wouldn't save from a console command

## 2.6.2 - 2020-02-20

### Fixed

- Fixed a bug where the prices weren't being saved on a ticket for an event

## 2.6.1 - 2019-12-10

### Fixed

- Fixed a bug where the shipping rules was using an incorrect item total

## 2.6.0 - 2019-12-02

### Added

- Support for Verbb Events: Tickets

## 2.5.6 - 2019-11-07

### Fixed

- Fixed a bug where shipping method rules were not using the correct currency

## 2.5.5 - 2019-10-31

### Fixed

- Fixed a bug where currencies were not displaying for shipping rules

## 2.5.4 - 2019-10-30

### Fixed

- Fixed a bug where the base discount was not using the correct currency value.

## 2.5.3 - 2019-10-22

### Fixed

- Shipping adjuster fix

## 2.5.2 - 2019-10-21

### Fixed

- Fixed a bug where shipping rules were not using the correct currency

## 2.5.1 - 2019-09-23

### Fixed

- Addons migration fix

## 2.5.0 - 2019-09-23

### Added

- commerce-addons support

### Changed

- plugin icon.
- refactor code

## 2.4.2 - 2019-07-10

### Fixed

- Fix for cloning product

## 2.4.1 - 2019-07-03

### Fixed

- Non-numeric value error when plugin installed and enabled but only one currency present.

## 2.4.0 - 2019-06-18

### Added

- Bundles support
- Digital Products support

## 2.3.9 - 2019-06-14

### Fixed

- Undefined offset error when saving new product #2

## 2.3.8 - 2019-04-26

### Fixed

- Save prices including localised formatting

## 2.3.7 - 2019-04-08

### Fixed

- Updated install script to include migration schema changes

## 2.3.5 - 2019-03-15

### Fixed

- shipping rules inputs

## 2.3.0 - 2019-02-38

### Added

- Currency fieldtype
