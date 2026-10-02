# Project profile — Commerce Currency Prices (Craft CMS 5 / Commerce 5 plugin)

**This file is referenced from the front matter of every spec in `docs/specs/`.** It carries the
project-specific half of a spec's build contract, so individual specs can stay about their feature.

> **Agents: read this whole file before writing code.** On 1 Oct 2026 the repo is the Craft 4 plugin
> (3.0.0-beta.5, master `734fa63`; the old `feature/bundles` branch is already merged) with **no tests, no
> static analysis, no CI**. formech's "Craft 5" copy (`~/Projects/formech-website/plugins/webdna/commercecurrencyprices`)
> is this code with every behaviour commented out — it is **not** a port and nothing should be copied from it.
> Every Commerce entry in Traps was read in Commerce 5.7.5's source
> (`~/projects/craft5/vendor/craftcms/commerce/src`) on 1 Oct 2026; every "old code" entry was found in
> this repo the same day.

---

## 1. Stack

| Layer | Choice |
|---|---|
| Package | `webdna/commerce-currency-prices`, type `craft-plugin`, handle **`commerce-currency-prices`** (keep — it is the key in every site's project config) |
| Namespace | `webdna\commerce\currencyprices\` → `src/` (keep — the field type's class name is stored in project config) |
| Plugin class | `src/CurrencyPrices.php`, schemaVersion `1.2.0` on 3.x |
| CMS | Craft CMS `^5.0` (Craft 4 stays on the 3.x line; no dual support) |
| Commerce | Craft Commerce `^5.3` (same floor as Bundles 3) |
| Language | PHP `^8.2` |
| Tests | Codeception 5 with Craft's `craft\test\Craft` module (added by the spec's first task) |
| Static analysis | PHPStan 2 via `craftcms/phpstan`, level 5, no baseline (added by the first task) |
| Coding standard | ECS via `craftcms/ecs` (added by the first task) |
| Licence | Craft License, paid Plugin Store plugin |
| Optional integrations | Commerce Bundles 3, Digital Products 4.1+, verbb Events 3.1+ — only when installed; never a hard dependency |

## 2. Commands

**The host has no PHP.** Until the harness exists, nothing runs on the host. After the first task,
everything runs in the containers its `docker-compose.yml` defines (PHP CLI + MySQL 8.0), as in
`~/Projects/plugins/auditor`:

```bash
docker compose up -d mysql
docker compose run --rm php composer install
docker compose run --rm php composer check        # ecs + phpstan + codeception
docker compose run --rm php composer analyse      # phpstan
docker compose run --rm php composer test         # codecept run
```

**Real-app testing uses the craft5 sandbox** (Sam, 30 Sep 2026): DDEV at `~/projects/craft5`, Craft 5.11.1
with Commerce 5.7.5. Mount this repo by adding
`${HOME}/Projects/plugins/commerce-currency-prices:/var/www/html/plugins/commerce-currency-prices:cached` to
`.ddev/docker-compose.store-plugins.yaml`, `ddev restart`, `ddev composer require
'webdna/commerce-currency-prices:*@dev'`, `ddev exec php craft plugin/install commerce-currency-prices`.
Drive it with a throwaway script under craft5's `storage/` that requires `/var/www/html/bootstrap.php` then
`CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php'`; delete it afterwards. CP pages over HTTP: mint a
`users/impersonate-with-token` token in such a script, open it with curl and a cookie jar, and pass
`-b jar -c jar` on every request. **The 3.x → 4.0 upgrade is tested from a Commerce 4 site** —
`~/Projects/craftcms/craft4-commerce` (Commerce 4.11.2) — never by editing craft5's database by hand.

## 3. Where things go

| What | Where |
|---|---|
| Plugin class | `src/CurrencyPrices.php` |
| Price storage (record, service) | `src/records/`, `src/services/` |
| CP field-layout element | `src/fieldlayoutelements/` |
| Order condition rule | `src/elements/conditions/` |
| Events | `src/events/` |
| Console commands | `src/console/controllers/` |
| Field type | `src/fields/CurrencyField.php` |
| Twig extension | `src/twigextensions/` |
| Migrations | `src/migrations/` — `Install.php` for fresh installs **and** a dated upgrade migration for 3.x data |
| CP templates | `src/templates/` |
| Translations | `src/translations/en/commerce-currency-prices.php` |
| Tests | `tests/unit/`, `tests/integration/`, `tests/_craft/` |
| Specs | `docs/specs/YYYY-MM-DD-<slug>.md` — **the spec is the plan; there is no `docs/plans/`** |

## 4. Verification

```bash
# 1. Everything (after the harness task)
docker compose run --rm php composer check
# expect: ECS "No errors found", PHPStan "[OK] No errors", Codeception "OK (N tests, M assertions)"

# 2. Composer metadata is Store-valid
docker compose run --rm php composer validate --strict --no-plugins
# expect: "./composer.json is valid"

# 3. Every class loads against Craft 5 + Commerce 5 (in craft5)
#    a storage/ script that class_exists() every file under plugins/commerce-currency-prices/src
# expect: "loaded N", no "ERR" / "NOT FOUND" lines, no fatal

# 4. No Commerce 4 API survives
grep -rnE 'getPrimaryPaymentCurrency|->salePrice\b|getSalesForPurchasable|records\\Sale\b|commerce\\models\\Currency|getContentColumnType|EVENT_REGISTER_ORDER_ADJUSTERS|registerAssetBundle' src/
# expect: no output
```

## 5. Traps

### Commerce 5 — currency and the order (5.7.5)

- **Payment currencies belong to a store.** `PaymentCurrencies::getAllPaymentCurrencies(?int $storeId)` and
  `getPaymentCurrencyByIso($iso, $storeId)`; there is no global list and no "primary payment currency" any
  more — the store's own `currency` is the primary. Always pass the store.
- **`Order::$currency` is a plain public column**, defaulted to the store's currency only when null
  (`elements/Order.php:1306`). `getPaymentAmount()` converts by exchange rate **only when `currency` differs
  from `paymentCurrency`** (`:2699`). Keep them equal and the exchange rate is never read.
- **An unknown payment currency is silently reset** to the store currency on an incomplete order
  (`Order.php:1311`). A test that sets an ISO the store doesn't have will see GBP, not an error.
- **`LineItem::populateFromPurchasable()` sets price and promotional price from the purchasable, calls
  `$purchasable->populateLineItem()`, then raises `LineItems::EVENT_POPULATE_LINE_ITEM`** (`models/LineItem.php:920-975`).
  Use `setPrice()` / `setPromotionalPrice()`; `salePrice` is derived (`getSalePrice()`), never assigned.
- **`Purchasable::getPrice()` is the catalog price for the current customer** when catalog pricing is in use,
  else `basePrice`; `getPromotionalPrice()` comes from catalog pricing **or the sales system**, and returns
  null unless it is below the price (`base/Purchasable.php:586-650`). Sales and catalog pricing are mutually
  exclusive per install — test both.
- **Availability with an order goes through `Purchasables::EVENT_PURCHASABLE_AVAILABLE`**
  (`services/Purchasables.php:169`). On recalculation `helpers/Order::normalizeLineItemPurchasableAvailability()`
  removes an unavailable line and adds the notice "{description} is no longer available." — reuse it, do not
  write a second removal path.
- **There is no currency order-condition rule in Commerce 5** (`elements/conditions/orders/`). Discount,
  shipping method and shipping rule conditions all extend `OrderCondition`, so one rule registered on
  `OrderCondition`'s `EVENT_REGISTER_CONDITION_RULES` reaches all three.
- **Shipping rule `minTotal`/`maxTotal` etc. are gone** — they are order conditions now. 3.x's per-currency
  shipping columns have nothing to map onto.

### Commerce 5 — purchasables

- **The store comes from the element's site** (`Purchasable::getStore()`, throws when unmapped). Two sites in
  one store share one set of per-store values — the last save wins. Prices for another store are saved from
  a site of that store.
- **Native purchasable layout elements live in `fieldlayoutelements/`** (`PurchasablePriceField` etc.). Add
  ours through `FieldLayout::EVENT_DEFINE_NATIVE_FIELDS` for layouts whose element type is a purchasable.
- **Drafts have their own element ids.** Anything keyed by element id must be copied when a draft is applied
  and when an element is duplicated, or the draft's values vanish on publish.
- **Commerce 5 variants default to `promotable = false`.** A test discount that "does nothing" is usually this.

### Commerce 4 → 5 upgrade

- **3.x puts foreign keys on `commerce_paymentcurrencies.iso`** from `commerce_discounts_currencyprices`,
  `commerce_shippingrules_currencyprices` and `commerce_shippingrule_categories_currencyprices`
  (`src/migrations/Install.php:274-334`). Commerce's `m230920_051125_move_primary_currency_to_store_settings`
  **drops the unique `iso` index** and recreates it non-unique. MySQL refuses to drop an index a foreign key
  needs, so a 3.x site's Commerce upgrade fails there. **Proven 2 Oct 2026** on the Commerce 4 fixture
  (MariaDB 10.11, error 1553). 3.0.0-beta.6 (`3.x` branch) drops the keys, and the upgrade then passes.
- **Commerce's upgrade puts every existing record in the first store**; ids of variants, discounts and
  shipping rules survive.
- **`craft up` runs this plugin's migrations BEFORE Commerce's** (verified 2 Oct 2026 on the fixture: Craft
  holds the plugins as blitz, commerce-currency-prices, commerce, despite the DB's handle order). Anything
  that needs Commerce 5's schema must wait for Commerce: 4.0's migration copies from a project-config handler
  on `plugins.commerce.schemaVersion`. A migration that returns false makes Craft restore its backup, so
  refusing cannot help an upgrade. **Commerce 4 already has a `commerce_stores` table**, so test whether
  Commerce has a pending update instead (`Plugins::isPluginUpdatePending()`).
- **Commerce defines variant native fields (price, SKU…) only on CP requests.** On the console a product
  type with no saved variant layout has an empty one, with no price element to anchor to.
- **The first `craft up` after an upgrade exits 1 on stale project-config YAML.** Run
  `craft project-config/write`, then `craft up` again.

### The 3.x code — defects to delete, not to port

- `TicketsController` calls `CurrencyPrices::$plugin->tickets`, a service that is never registered.
- `PaymentCurrenciesController` adds and renames a **database column per currency** on every save — the reason
  3.x's main table is shaped per-ISO. Commerce 5's own payment-currency screens never call it.
- The adjusters (`src/adjusters/`) and `models/ShippingRule.php` / `ShippingMethod.php` are forks of Commerce
  4 internals; `CurrencyPricesService::getSalePrice()` re-implements Commerce 4 sales. All are replaced by
  the ratio rule and the condition rule, not ported.
- The CP UI is hidden inputs moved next to the price field by jQuery (`assetbundles/*/dist/js`) against
  Commerce 4's DOM. Commerce 5's DOM is different; none of it survives.
- `fields/CurrencyField.php` uses Craft 4 field signatures (`getContentColumnType`, untyped nullable element).

### craft5 sandbox

- **Run `ddev` from `/Users/samuelbirch/projects/craft5` (lowercase p).** From `~/Projects` a restart stops
  the project and then refuses to start it.
- **A CLI script that creates fields, types, stores or payment currencies must call
  `$app->getProjectConfig()->saveModifiedConfigData()` before exiting**, or the rows exist with no config.
- **Never commit in craft5**; it carries other sessions' uncommitted work. Mention changes in the handover.
  Snapshot before changing its store: `ddev snapshot --name pre-currency-prices-<date>`.

## 6. Working conventions

- **Commit, never push.** Sam pushes and tags. Releases are tags; `composer.json` carries no `version`.
- One spec task per session: when a §8 task is committed, verified and ticked, update the build-progress
  memory and end with a copy-paste prompt for a fresh session.
- The Craft 5 line is **4.x** on `master`. Craft 4 fixes go on a `3.x` branch cut from `734fa63`.
- CHANGELOG entries go under `## Unreleased` until Sam tags.
