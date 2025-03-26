# Currency Prices Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

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
