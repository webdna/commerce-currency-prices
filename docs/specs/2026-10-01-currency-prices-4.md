---
spec: Currency Prices 4 — Craft 5 and Commerce 5
slug: currency-prices-4
status: draft
version: 0.1
date: 2026-10-01
author: Claude (with Sam Birch)
client: webdna (Plugin Store product)
approver: Sam Birch
profile: _PROFILE.commerce-currency-prices.md
related: [commerce-bundles-3 (~/Projects/plugins/commerce-bundles/docs/specs/2026-10-01-commerce-bundles-3.md)]
---

# Currency Prices 4 — Craft 5 and Commerce 5

> **Status:** draft - **Version:** 0.1 - **Profile:** `_PROFILE.commerce-currency-prices.md`
> A single Craft 5 shop can sell in several currencies at prices it sets for each currency, not prices
> converted by an exchange rate.

---

## 1. How it works

A shop owner sells from one store but lets shoppers pay in several currencies — pounds, dollars,
euros, dirhams. With Currency Prices 4 they type a price for each of those currencies beside the
product's normal price. A shopper who pays in dollars sees and pays the dollar price that was typed,
not the pound price converted at today's rate. It works for products and their variants, for
bundles, for digital products and for event tickets.

Promotions follow the shopper's currency without being set up twice. When a pricing rule takes 10%
off a product in the store's own currency, the dollar price drops by the same 10%. That includes
prices that depend on who the customer is, such as trade pricing. Discounts and shipping that are a
fixed amount of money are different, because $10 off is not £10 off. For those the owner makes one
discount or shipping rule per currency, using a new **payment currency** condition: "applies when the
shopper pays in USD". Percentage discounts need no copies.

An item with no price in the shopper's currency cannot be bought in that currency. If a shopper
switches currency with it already in the basket, it is removed with Commerce's usual "no longer
available" notice. It is never sold at zero or at a guessed conversion. The order is then recorded
and charged in the currency the shopper paid in, so the total, the receipt and the card charge always
agree.

Shops already running the Craft 4 version keep their product prices when they upgrade. The Craft 4
version also held per-currency discount and shipping amounts. Commerce 5 rebuilt discounts and
shipping, so those cannot be carried across automatically. The upgrade keeps them and a report lists
each one, so the owner can set it up again as a per-currency rule. A shop that has kept currency
prices in its own product fields can load them in with an import command.

Choosing which currency a shopper pays in stays the shop's own decision, made by its site code (by
country, by account, or by a switcher). The plugin prices whatever currency the shopper's basket is
in.

**Rejected:** forking Commerce's discount and shipping calculators to hold an amount per currency, as
the Craft 4 version did. Every Commerce release would have to be re-forked, and it would collide with
shops that already replace those calculators with their own. Also rejected: converting by exchange
rate when a price is missing. A rate left at 1.0 (the Craft 4 version's own advice) would sell a $100
item for £100.

**Vocabulary**

| Term | Meaning |
|---|---|
| Store | Commerce's unit of selling, with one main currency and its own prices. A site belongs to one store |
| Store currency | The store's main currency (GBP for a UK shop). Its price is Commerce's own price field |
| Payment currency | A currency a store also accepts. Each one gets its own currency price |
| Currency price | The price typed for one payment currency, for one item, in one store |
| Promotion ratio | How far a promotion has moved the store-currency price, applied to the currency price |
| Payment currency condition | A rule condition: "the shopper pays in one of these currencies" |

## 2. Scope

**In scope**

- Currency prices on variants, Commerce Bundles 3 bundles, Digital Products and Events tickets,
  entered in the control panel beside the normal price, per store.
- Baskets and orders priced in the shopper's payment currency, with promotions carried across by
  ratio, and an order whose currency always matches its payment currency.
- Items without a price in the shopper's currency are unavailable in that currency.
- A payment currency condition for discounts, shipping methods and shipping rules.
- A way for a shop's own code to adjust a currency price, such as rounding (heathfield rounds sale
  prices down after tax).
- Template helpers that show a currency price, giving the same figure the basket will charge.
- The Currencies field type (a dropdown of the store's payment currencies), carried over.
- Upgrade from the Craft 4 version: product prices kept, discount and shipping amounts reported.
- An import command that copies prices from a shop's own number fields.
- An automated test suite, which the plugin has never had.

**Out of scope**

| Item | Reason |
|---|---|
| Choosing the shopper's currency (by country, account or switcher) | The shop's decision. Commerce already lets a basket change payment currency |
| Exchange-rate conversion | Prices are typed per currency. A conversion fallback is the rejected alternative in §1 |
| An amount per currency on each discount or shipping rule | Replaced by the payment currency condition (§1) |
| Converting the Craft 4 version's discount and shipping amounts into new rules | Commerce 5 changed what those amounts mean. A person recreates them from the report |
| Commerce Addons | It has no Craft 5 release (last was 1.0.1 in 2019), so there is nothing to test against |
| Heathfield switching over to the plugin | Its own piece of work. Heathfield is the case this design is checked against |
| Per-currency promotional prices typed by hand | Promotions follow by ratio (§1). An exception per currency is not needed yet |
| A shop with one store per currency (as formech has) | Commerce 5 already does this without the plugin |

**Later**

- GraphQL fields for currency prices.
- A Feed Me field so imports can set currency prices directly.
- A currency-price column in the product index.

## 3. User journeys

**Who it is for**

| Who | What changes for them |
|---|---|
| Shop owners and editors | Type a price per currency. Set up per-currency shipping and fixed-amount discounts |
| Shoppers | Pay the price shown in their currency, and are charged in that currency |
| Shops on the Craft 4 version (26 installs) | Upgrade to Craft 5 with prices intact. They must recreate per-currency discounts and shipping from the report |
| Shop developers | One event to adjust a currency price. Template helpers that match the basket |

**Journeys**

1. **Pricing a product in another currency.**
   1. An editor opens a product. Under each variant's price is a **Currency prices** group with one
      box per payment currency the store accepts: USD, EUR, AED.
   2. They type 120 for USD and 110 for EUR, and leave AED empty.
   3. They save. USD and EUR are priced. AED shows as "No price — not for sale in AED".
2. **Shopping in another currency.**
   1. A shopper's basket is in USD (the shop's site chose it).
   2. They add the variant. The line is $120, the total is in dollars, and payment is taken in
      dollars.
   3. A pricing rule takes 10% off the product, so the line shows $108.
   4. They switch to AED. The variant is removed with "… is no longer available."
3. **A fixed-amount discount or shipping rate per currency.**
   1. An admin duplicates "£5 off orders over £50" as "$6 off orders over $65". To the copy they add the
      condition *Payment currency is USD*, and to the original *Payment currency is GBP*.
   2. A dollar basket over $65 gets $6 off. A pound basket gets £5 off. Neither gets both.
4. **Upgrading from the Craft 4 version.**
   1. The developer upgrades the site to Craft 5 and Currency Prices 4.
   2. Every product, bundle and digital product shows its old currency prices.
   3. They run the legacy report. It lists each old per-currency discount and shipping amount, with
      the rule it belonged to, and they recreate those that are still wanted.
5. **Importing prices from the shop's own fields.**
   1. A developer runs the import, naming the shop's own dollar price field and USD. A dry run lists
      what would change.
   2. They run it again to apply it. The prices appear on the products.

**First run.** Straight after installing, nothing changes for shoppers: no item has a currency price,
so a basket in the store currency behaves exactly as before. A basket in another currency finds
nothing to buy. On the editing screen, a store with no extra payment currencies shows: "This store
only accepts GBP. Add payment currencies in Commerce → Store Settings → Payment Currencies to price
items in other currencies."

## 4. Data model

**One new table, `currencyprices_prices`:** one row per purchasable × store × currency.

| Column | Type | Notes |
|---|---|---|
| `id` | pk | |
| `purchasableId` | int, FK `elements.id` ON DELETE CASCADE | Variant, bundle, digital product or Events ticket type (its tickets are priced from it). Drafts and revisions have their own rows |
| `storeId` | int, FK `commerce_stores.id` ON DELETE CASCADE | The store whose payment currency this is |
| `currency` | char(3) | ISO code. Never the store's own currency |
| `price` | decimal(14,4) not null | The typed price. An absent row means "no price" |
| `dateCreated`, `dateUpdated`, `uid` | | |

Unique `(purchasableId, storeId, currency)`. Index `(storeId, currency)`.

**Why this shape.** The Craft 4 table kept one column per currency and changed the table's columns
whenever a currency was saved, through the plugin's own currency screen. Commerce 5's currency screens
never call that code, and currencies now belong to a store, so the rows carry both. The store-currency
price is not stored here: it is Commerce's own base price, and two copies of it would drift. No
promotional price is stored either, because promotions are derived (BR-7). An empty box is an absent
row rather than a 0, so "free" and "not priced" stay distinct.

**On deletion.** Deleting an item or a store removes its rows. Removing a payment currency from a
store leaves its rows in place but unused, and they apply again if the currency is re-added.

**Craft 4 tables.** `commerce_currencyprices`, `commerce_discounts_currencyprices`,
`commerce_shippingrules_currencyprices`, `commerce_shippingrule_categories_currencyprices` and
`addons_discounts_currencyprices` are left as they are once read. Nothing writes to them again. The
report (BR-20) reads them, and uninstalling drops them.

## 5. Rules

| ID | Rule |
|---|---|
| BR-1 | A currency price exists per purchasable, store and payment currency. Only the payment currencies of the element's store, other than the store currency, can be priced. |
| BR-2 | A **Currency prices** field-layout element is available on the field layout of every purchasable type: variants, Bundles 3 bundle types, Digital Products product types, and Events ticket-type layouts. Events generates a ticket per session and ticket type, and a ticket has no layout of its own, so the ticket type holds the prices for all its tickets. It shows one money input per eligible currency (BR-1), labelled with the ISO code and formatted to that currency. It shows the store-only notice from §3 when there are none. It is never added to a layout automatically. Each layout gets it in its own way: on install, the plugin adds it below the price element of each existing **variant** layout. Other types' layouts are left for an admin. |
| BR-3 | A blank input means no price and deletes the row. 0 is a valid price. A negative or non-numeric value fails validation on the element, with "Enter a price of 0 or more for {currency}." |
| BR-4 | Currency prices are written in the element's own save, after Commerce writes its store values. Programmatic saves, resaves and the CP all go through the same path. A save whose request does not post the element's currency-price inputs leaves its rows untouched. |
| BR-5 | Duplicating an element copies its rows. A draft gets its own rows, copied from the canonical element when the draft is created. Applying a draft replaces the canonical element's rows with the draft's. A revision keeps a copy of the rows as they were. |
| BR-6 | Prices are saved for the store of the site being edited. A store's rows are changed only from a site of that store. |
| BR-7 | **Line pricing.** When an order's payment currency differs from its store currency and the line's purchasable has a currency price *C*, the line's price is *C × (price ÷ base price)*. Its promotional price is *C × (promotional price ÷ base price)*, or none when the purchasable has none. Price, promotional price and base price are Commerce's own values for that customer and store. A base price of 0 or none gives a ratio of 1. Results are rounded to the currency's minor unit. This runs after Commerce populates the line, so the catalog pricing path and the sales path both feed it. |
| BR-8 | After BR-7, the plugin raises `CurrencyPrices::EVENT_DEFINE_CURRENCY_PRICE` with the purchasable, the currency, the order and the line (both null outside a basket), the price and the promotional price. A handler may change the two prices. The same event is raised for the template helpers (BR-15), so a page and a basket show the same figure. |
| BR-9 | The line's snapshot records `currencyPrices: {currency, currencyPrice, ratio}`, so a completed order shows how its price was reached after the price changes. |
| BR-10 | **Order currency.** Before an incomplete order is saved or recalculated, its currency is set to its payment currency. A completed order's currency is never changed. Commerce therefore never converts by exchange rate, and the plugin never reads a rate. |
| BR-11 | **Availability.** Asked about a purchasable for an order whose payment currency differs from the store currency, the plugin answers unavailable when the purchasable has no currency price for that currency in that store. Adding it fails with Commerce's error. A line already in the basket is removed on recalculation with Commerce's "{description} is no longer available." notice. Without an order, availability is unchanged. |
| BR-12 | A **Payment currency** condition rule is registered on Commerce's order condition, so it appears on discounts, shipping methods and shipping rules. It has operators *is one of* and *is not one of*, offers the store's payment currencies including the store currency, and matches the order's payment currency. |
| BR-13 | Fixed amounts on a discount or shipping rule with no payment currency condition apply unchanged, whatever currency the order is in. The plugin does not detect this. The README and the condition's instructions say so. Percentages need no condition. |
| BR-14 | Prices for an order's purchasables are read in one query per recalculation, not one per line. Template helpers read one item's prices once per request. |
| BR-15 | Twig filters `purchasable\|currencyPrice(iso)`, `\|currencyPromotionalPrice(iso)` and `\|currencySalePrice(iso)` give BR-7's price, its promotional price, and the promotional price falling back to the price, after BR-8, for the current customer. For the store currency they give Commerce's own value. For a currency with no price they give null. For a currency the store does not accept they give null and log a warning. The 3.x names `currencyPrice` and `currencySalePrice` keep working. `currencyAddonDiscountPrice(s)` and `localizationNormalizeNumber` are removed. |
| BR-16 | The **Currencies** field type keeps its class name and stored values. It offers the payment currencies of the current site's store, and uses Craft 5's field API. |
| BR-17 | Integrations are soft. Bundles, Digital Products and Events are referenced by class name only after checking the plugin is installed. With none of them installed, the plugin installs and runs, and PHPStan passes. |
| BR-18 | **Upgrade, prices.** The 4.0 upgrade migration copies each `commerce_currencyprices` row into one row per non-null column whose ISO is a payment currency of the primary store and is not its store currency. All go to the primary store. Rows for elements that no longer exist are skipped. The migration prints counts of rows copied, skipped and invalid. It refuses, with a message, when Commerce's store tables do not exist yet. Running it twice writes nothing the second time. |
| BR-19 | **Upgrade, the path in.** A 3.x site upgrades to Craft 5 without a manual database step. 3.x's foreign keys on `commerce_paymentcurrencies.iso` must not stop Commerce's own upgrade. 3.0.x (Craft 4) ships a migration that drops them, and the README's upgrade notes say to update to it first. 4.0's migration drops any that remain. |
| BR-20 | **Upgrade, the report.** `php craft commerce-currency-prices/legacy/report` lists every Craft 4 per-currency discount, shipping rule, shipping-category rate and Addons discount amount. Each entry shows the currency, the amounts, the record's name and its CP URL when the record still exists, or "deleted" when it does not. It changes nothing. With no Craft 4 tables it prints "No Craft 4 data found." |
| BR-21 | **Import.** `php craft commerce-currency-prices/import --field=<handle> --currency=<ISO> [--store=<handle>] [--apply] [--include-zero]` reads a number field from each purchasable, or else from its owner product. It writes currency prices for that store, which defaults to the primary store. Without `--apply` it changes nothing. An empty field is skipped. A 0 is skipped unless `--include-zero` is given, because a hand-rolled field defaulting to 0 usually means unpriced. It prints counts of items written, unchanged, overwritten, skipped as empty, skipped as zero and failed. A currency the store does not accept stops it before reading anything. |
| BR-22 | Editing currency prices needs the same permission as editing the item. There is no new permission. The commands are console-only. |
| BR-23 | The 3.x controllers, adjusters, forked shipping models, asset bundles and payment-currency screen are removed, not ported (profile §5). The handle, namespace and Currencies field class are unchanged. |
| BR-24 | Release 4.0.0 needs `craftcms/cms ^5.0`, `craftcms/commerce ^5.3` and PHP `^8.2`. Its README covers setup, the per-currency rule pattern, BR-13's warning and the upgrade from 3.x. The CHANGELOG lists every removal under BR-15 and BR-23. |

**Non-functional.** No personal data is stored. Prices are not site-specific beyond their store, so
multi-site needs nothing more. Console output is plain text, one line per count. CP inputs use Craft's
own money field, so they meet the CP's accessibility.

## 6. Interfaces

| Surface | Kind | Auth | Notes |
|---|---|---|---|
| Currency prices layout element | CP, element editor | Element's edit permission | BR-2–BR-6 |
| Payment currency condition rule | CP, condition builders | Commerce's discount and shipping permissions | BR-12 |
| `EVENT_DEFINE_CURRENCY_PRICE` | PHP event on `CurrencyPrices` | — | BR-8. Event class `events/DefineCurrencyPriceEvent` |
| Twig filters | Templates | — | BR-15 |
| `commerce-currency-prices/legacy/report` | Console | Shell | BR-20 |
| `commerce-currency-prices/import` | Console | Shell | BR-21 |

There are no site or CP routes, and no front-end controller. The shopper changes currency through
Commerce's own `commerce/cart/update-cart` with `paymentCurrency`.

**Design source:** none. Everything reuses Commerce 5's own price field, field-layout and
condition-builder patterns. **Copy:** Claude drafts every string, quoted in §3 and §5. Sam approves it
when he reviews the spec.

## 7. Test plan

### Client-verified criteria

| ID | Criterion | Proved by |
|---|---|---|
| AC-1 | An editor types a price per payment currency on a variant, and a currency left empty shows as not for sale | TS-1 |
| AC-2 | A store with no extra currencies shows the explanatory notice instead of inputs | TS-1 |
| AC-3 | A basket in USD charges the USD price, shows dollar totals, and records a dollar payment | TS-2 |
| AC-4 | An item with no price in the basket's currency cannot be added, and is removed with a notice when the shopper switches to that currency | TS-3 |
| AC-5 | A percentage pricing rule or sale on the store price moves the currency price by the same proportion, including a trade-only rule | TS-4 |
| AC-6 | A discount and a shipping rule limited to USD apply to dollar baskets only | TS-5 |
| AC-7 | A bundle, a digital product and an event ticket can each be priced and bought in another currency | TS-6 |
| AC-8 | A change to a draft's currency prices does not reach shoppers until the draft is published | TS-7 |
| AC-9 | After upgrading from the Craft 4 version, every currency price is still there, and the report lists the old discount and shipping amounts | TS-8 |
| AC-10 | Prices in a shop's own number field can be previewed, then imported | TS-9 |
| AC-11 | A product page using the template helpers shows the same price the basket charges | TS-10 |

### Test data and preconditions

| Data | State |
|---|---|
| craft5 primary store with USD and EUR payment currencies added (GBP store) | **Made by task 1.2** — Sam approved the craft5 change (1 Oct 2026). Snapshot craft5 first; never commit there |
| Digital Products 4.1 and Events 3.1 installed in craft5 | **Made by task 1.2**, same approval |
| Commerce Bundles 3 at least through its phase 1 | **Not built yet** — AC-7's bundle leg waits for it (Appendix A) |
| A Commerce 4 site with 3.x installed and currency prices on variants, discounts and shipping | **Must be made** in `~/Projects/craftcms/craft4-commerce` (Appendix A) |
| A customer user group and a catalog pricing rule limited to it ("trade") | Made by TS-4 |
| A second store with its own site in craft5 | Not needed. TN-6 runs in the suite |

### Scenarios

**TS-1 — Editing currency prices** · Type: manual (CP) + integration · AC-1, AC-2 · BR-1–BR-4, BR-6
| # | Step | Expect |
|---|---|---|
| 1 | Add the layout element to a product type's variant layout. Open a variant | USD and EUR inputs under the price |
| 2 | Enter USD 120, leave EUR blank, save | Saved. USD shows 120. EUR shows "No price — not for sale in EUR" |
| 3 | Enter USD −5, save | Error "Enter a price of 0 or more for USD." Nothing is saved |
| 4 | Resave the variant from a console script without posting prices | USD is still 120 |
| 5 | Remove USD and EUR from the store and reopen the variant | The store-only notice is shown |

*Success criterion: typed prices persist, blanks mean not for sale, and nothing wipes them silently.*

**TS-2 — A basket in another currency** · Type: integration + manual (front end) · AC-3 · BR-7, BR-9, BR-10
| # | Step | Expect |
|---|---|---|
| 1 | Set a basket's payment currency to USD through `commerce/cart/update-cart` | Order currency and payment currency are both USD |
| 2 | Add the variant priced at USD 120 | Line price 120. The total is formatted in dollars |
| 3 | Pay with the dummy gateway | The transaction is in USD for the order total. No exchange-rate conversion |
| 4 | Change the variant's USD price to 130 | The completed order still shows 120. Its snapshot carries `currencyPrices` |

*Success criterion: a dollar shopper pays exactly the typed dollar price, in dollars.*

**TS-3 — Not priced in that currency** · Type: integration · AC-4 · BR-11
| # | Step | Expect |
|---|---|---|
| 1 | In a USD basket, add a variant with no USD price | Refused with Commerce's unavailable error |
| 2 | In a GBP basket, add it, then switch the basket to USD | The line is removed with "… is no longer available." |
| 3 | Switch back to GBP and add it again | Added at the GBP price |

*Success criterion: nothing is ever sold in a currency it was not priced in.*

**TS-4 — Promotions by ratio** · Type: integration · AC-5 · BR-7
| # | Step | Expect |
|---|---|---|
| 1 | Catalog pricing rule: 10% off the variant (base £100, USD 120). Run the pricing job | A USD line costs 108. A GBP line costs 90 |
| 2 | Limit the rule to the "trade" group, then shop as a trade customer and as a guest | Trade pays 108 and the guest pays 120 |
| 3 | Give the variant a base promotional price of £80 | The USD promotional price is 96 |
| 4 | Create a sale instead (catalog pricing off): 25% off | The USD sale price is 90 |
| 5 | Base price 0, USD 15 | The USD line costs 15 (ratio 1) |

*Success criterion: every promotion that moves the store price moves the currency price in proportion.*

**TS-5 — Per-currency discount and shipping** · Type: integration + manual (CP) · AC-6 · BR-12, BR-13
| # | Step | Expect |
|---|---|---|
| 1 | Discount "$6 off over $65" with *Payment currency is USD*. Discount "£5 off over £50" with *is GBP* | Both save. The condition offers GBP, USD and EUR |
| 2 | A USD basket of $70 | $6 off only |
| 3 | A GBP basket of £60 | £5 off only |
| 4 | Shipping rule "$9 flat" with *is USD* and "£7 flat" with *is GBP* on one method | USD basket shipping $9. GBP basket £7 |
| 5 | A 10% discount with no condition, in a USD basket | 10% off in dollars |

*Success criterion: fixed amounts reach only the currency they were written in.*

**TS-6 — Other purchasables** · Type: integration + manual (CP, front end) · AC-7 · BR-2, BR-17
| # | Step | Expect |
|---|---|---|
| 1 | Add the layout element to a bundle type, a digital product type and an event type's ticket-type layout | Inputs appear on each |
| 2 | Price each in USD and add each to a USD basket | Each line is at its USD price |
| 3 | Uninstall Digital Products and Events, then load the CP and a basket | No error. Variants and bundles still price |

*Success criterion: anything Commerce can sell can be priced in another currency, and no integration is required.*

**TS-7 — Drafts and duplicates** · Type: integration · AC-8 · BR-5
| # | Step | Expect |
|---|---|---|
| 1 | Draft a product and change a variant's USD price from 120 to 140 | The live variant is still 120 in a USD basket |
| 2 | Publish the draft | The live variant is 140 |
| 3 | Revert to the previous revision | 120 again |
| 4 | Duplicate the product | The copy's variants carry the same USD prices |

*Success criterion: a draft is invisible to shoppers until published, and history keeps its prices.*

**TS-8 — Upgrade from 3.x** · Type: manual (fixture) · AC-9 · BR-18–BR-20
| # | Step | Expect |
|---|---|---|
| 1 | On the Commerce 4 fixture, record each variant's 3.x currency prices and the discount and shipping currency amounts | A baseline |
| 2 | Update to 3.0.x and run `craft up` | The foreign keys on `iso` are gone. The site still works on Craft 4 |
| 3 | Upgrade to Craft 5, Commerce 5 and 4.0, then run `craft up` | No error. The migration prints copied, skipped and invalid counts |
| 4 | Open each variant | The same currency prices as the baseline |
| 5 | Run the legacy report | Every baseline discount and shipping amount is listed, with names and CP URLs |
| 6 | Run `craft up` again | Nothing to do |

*Success criterion: a Craft 4 shop keeps every price, and knows exactly which rules to rebuild.*

**TS-9 — Import from fields** · Type: integration · AC-10 · BR-21
| # | Step | Expect |
|---|---|---|
| 1 | Number field `priceUSD` on the product: 50. On one variant: 55. On another: empty. On a third: 0 | — |
| 2 | Import `--field=priceUSD --currency=USD` | The dry-run counts are printed. Nothing is written |
| 3 | Add `--apply` | Variant one 55, variant two 50 (from the product), the 0 skipped |
| 4 | Run it again | Every item unchanged |
| 5 | `--currency=JPY` | Stops: JPY is not a payment currency of the store |

*Success criterion: a shop's own price fields become currency prices in one reviewed command.*

**TS-10 — Templates and the adjust event** · Type: integration · AC-11 · BR-8, BR-14, BR-15
| # | Step | Expect |
|---|---|---|
| 1 | Render `variant\|currencyPrice('USD')` with the 10% rule from TS-4 | 108, the same as the basket line |
| 2 | Register a handler that rounds the price down to a whole dollar, with a USD price of 119.50 | The template and the basket both show 119 |
| 3 | `\|currencyPrice('JPY')` | null, and a warning is logged |
| 4 | A basket of 20 lines, recalculated | One currency-price query |

*Success criterion: what a page shows is what the basket charges.*

### Negative and edge cases

| ID | Case | Expect | Rules |
|---|---|---|---|
| TN-1 | A non-numeric price is posted | Validation error. Nothing is saved | BR-3 |
| TN-2 | Deleting a priced variant | Its rows go | §4 |
| TN-3 | A payment currency is removed and then re-added | The old prices apply again | §4, BR-1 |
| TN-4 | A basket's payment currency is set to an ISO the store lacks | Commerce resets it to the store currency, and the order currency follows | BR-10 |
| TN-5 | A completed order's payment currency is posted again | The order currency is unchanged | BR-10 |
| TN-6 | Two stores, each with USD | Each store's USD price is separate. Editing in store A's site leaves store B's untouched | BR-1, BR-6 |
| TN-7 | A store-currency basket | Behaves exactly as without the plugin, with no snapshot key | BR-7, BR-9 |
| TN-8 | Promotional price above the price after the ratio | No promotional price (Commerce's rule) | BR-7 |
| TN-9 | The migration runs before Commerce's store tables exist | Writes no prices, says why, and runs again on the next `craft up` (within one `craft up`, it copies once Commerce has finished) | BR-18 |
| TN-10 | The legacy report on a fresh 4.0 install | "No Craft 4 data found." | BR-20 |
| TN-11 | An import where a field holds text | Counted as failed, with the item id, and the rest continue | BR-21 |
| TN-12 | An editor without permission to edit the product | Cannot reach the inputs (Craft refuses the editor) | BR-22 |
| TN-13 | Event handler sets a negative price | Clamped to 0 and logged | BR-8 |
| TN-14 | Two editors save the same variant's prices at once | The last save wins with no duplicate rows (unique key) | §4 |
| TN-15 | A user with a Currencies field value saved under 3.x, after the upgrade | The value is shown and selectable among the store's payment currencies | BR-16 |
| TN-16 | A 3.x template using `currencyAddonDiscountPrice` | Twig's unknown-filter error, as the CHANGELOG warns (the 3.x code is gone, B5 #4 finds none of it) | BR-15, BR-23, BR-24 |

### Automated checks

The plugin has no suite. Task 0.1 adds Codeception (unit and integration against a Craft 5 + Commerce
5 test install), PHPStan level 5 and ECS. Every TS marked integration is a Codeception test. TS-1,
TS-5 and TS-6's CP steps and TS-8 are manual. TS-8 needs a real Commerce 4 → 5 upgrade, which the
suite cannot run.

**Test hooks the build must add:** each currency input has `data-currency-price="<ISO>"`. The
store-only notice has `data-currency-prices-none`. The condition rule's type is
`webdna\commerce\currencyprices\elements\conditions\PaymentCurrencyConditionRule`. The snapshot key is
fixed as `currencyPrices.{currency,currencyPrice,ratio}`.

### Regression checks

| Check | Why at risk |
|---|---|
| A store-currency basket prices exactly as without the plugin | BR-7 and BR-10 run on every line and order |
| Discounts and shipping rules without the new condition behave as before | Registering a rule changes every order condition builder |
| Commerce's own payment-currency screens still save | 3.x hooked them. 4.0 must not |
| Heathfield's pattern: its own `EVENT_POPULATE_LINE_ITEM` handler running alongside the plugin's | Two handlers set the same line. Order matters (Appendix A assumption) |

## 8. Build order

| Phase | Delivers | Depends on |
|---|---|---|
| 0 | Test harness, plugin skeleton on Craft 5 with the 3.x code removed | — |
| 1 | Storage, editing, line pricing, availability, order currency, condition, templates | 0 |
| 2 | Bundles, Digital Products and Events tested | 1. Bundles 3 phase 1 for 2.1 |
| 3 | Upgrade: 3.0.x foreign keys, 4.0 migration, legacy report | 1 (the price table). The Commerce 4 fixture |
| 4 | Import command | 1 |
| 5 | Release | all |

Phase 1 comes before everything because every later phase writes or reads its table. Phase 3 is
separate because it needs the Commerce 4 fixture and touches the 3.x line.

### Tasks

- [x] **0.1 Harness** — `docker-compose.yml`, `docker/`, `codeception.yml`, `tests/`, `phpstan.neon`,
      `ecs.php`, composer scripts, mirroring `~/Projects/plugins/auditor`. Rules: — · Verify: B5 #1 (empty suite green)
      — built: ECS and PHPStan cover `tests/` only and the Craft module installs Commerce only, so 0.2 adds
      `src/` to both and the plugin to `codeception.yml`. `HarnessTest` proves Craft 5 + Commerce 5 install.
- [x] **0.2 Craft 5 skeleton** — `composer.json` (BR-24 constraints, no `version`), `src/CurrencyPrices.php`
      rewritten for Craft 5. Delete `controllers/`, `adjusters/`, `models/`, `assetbundles/`,
      `services/{Shipping,Discounts,Addons}Service.php`, `records/` except what 1.1 needs, and the 3.x templates.
      Rules: BR-17, BR-23 · Verify: B5 #3, #4
      — built: all of `services/` and `records/` went too, along with `twigextensions/` (each was Craft 4 code
      calling the Addons service or 3.x sales; 1.1 and 1.5 write them new). The plugin class only registers
      `CurrencyField`, which is ported to Craft 5 signatures. `Install.php` is empty until 1.1, and the 3.x install
      is at `734fa63`. The three 3.x upgrade migrations stay. `PluginTest` proves the plugin installs after Commerce.
- [x] **1.1 Price storage** — `migrations/Install.php` (new table only), `records/PriceRecord.php`,
      `services/Prices.php` (read per item, batch read per order, write, copy). Rules: BR-1, BR-14 · Verify: unit tests, TN-2, TN-6, TN-14
      — built: `CurrencyPrices::getInstance()->getPrices()`. `savePrices()` takes ISO ⇒ price, null or blank deletes,
      currencies not given are untouched, and it throws for an ineligible currency or a negative price (1.2 turns
      those into BR-3's validation error). Each write is one upsert on the unique key (TN-14). Deletion is the
      `elements.id` cascade, so a soft-deleted item keeps its rows until it is hard-deleted, and a restore keeps
      its prices. `copyPrices()` replaces the target's rows in every store, for BR-5. `tests/integration/PricesTest.php`.
- [x] **1.2 Layout element and saving** — first set up craft5 (snapshot, USD + EUR payment currencies, Digital Products 4.1, Events 3.1). `fieldlayoutelements/CurrencyPricesField.php`, its template,
      save, validate, duplicate, draft and revision handling, adding it to variant layouts on install.
      Rules: BR-2–BR-6, BR-22 · Verify: TS-1, TS-7, TN-1, TN-3, TN-12
      — built: the inputs post `currencyPrices[<ISO>]` onto `behaviors/CurrencyPricesBehavior` (null = not posted,
      BR-4). `services/Purchasables` (`getPurchasables()`) holds the handlers, all registered on Commerce's base
      `Purchasable`: validation, the after-save write for the saved site's store, and the copy. It also holds
      `addToVariantLayouts()` / `removeFromVariantLayouts()`, which the install and uninstall call unless project
      config is being applied. Every duplicate, new draft, applied draft, revision and revert is saved with
      `duplicateOf` set, so one rule copies rows (BR-5). A posted currency the store no longer accepts is ignored
      (TN-3). A product draft shares its variants until one is edited. `tests/integration/CurrencyPricesFieldTest.php`
      replays the CP's copy-on-write and `changedattributes` row. craft5's store is **USD**, not GBP, so it has
      EUR + GBP as its extra currencies. B5 #3 and #5 pass. B5 #6 passed at the start of 1.3: 2 inputs, EUR and
      GBP. It found that a real `plugin/install` added nothing to the layouts: Craft runs the install before it
      enables the plugin, and a layout tab silently drops an element whose plugin is not enabled. The plugin's
      `afterInstall()` now adds it on Craft's after-install event. Uninstall still removes it in the migration.
- [x] **1.3 Line pricing, order currency, availability, event** — `events/DefineCurrencyPriceEvent.php`,
      handlers in `CurrencyPrices.php` or `services/Pricing.php`. Rules: BR-7–BR-11 · Verify: TS-2, TS-3, TS-4, TN-4, TN-5, TN-7, TN-8, TN-13
      — built: `services/Pricing` (`getPricing()`) holds every handler. The ratio uses the line's own price and
      promotional price after Commerce populates it, so a discount that ignores promotions also drops the
      currency promotional price. `Pricing::definePrices()` raises the event and applies the clamp (TN-13) and
      the promotional rule (TN-8); 1.5's filters reuse it. The order's currency is set on the order's
      before-save and before its lines are refreshed. A payment currency the store lacks is reset to the
      store currency first (TN-4), so its lines are not removed. Commerce formats every `…AsCurrency` amount in
      the store currency, so `behaviors/OrderCurrencyBehavior` formats an order's and its lines' amounts, and
      their `toArray()`, in the order's currency (TS-2 #2). The add-to-cart refusal is Commerce's line error
      "The item is not enabled for sale." A recalculation reads the basket's prices in one query (BR-14).
      `tests/integration/PricingTest.php` sets the payment currency the way `commerce/cart/update-cart` does
      rather than over HTTP. It pays with the Dummy gateway at a USD rate of 1.25, so any conversion would show.
      Commerce memoises a purchasable by id whoever the customer is (`Purchasables::getPurchasableById()`), so
      the test resets that service between customers. In one real request there is one customer.
- [x] **1.4 Payment currency condition** — `elements/conditions/PaymentCurrencyConditionRule.php`.
      Rules: BR-12, BR-13 · Verify: TS-5
      — built: a `BaseMultiSelectConditionRule` registered on `OrderCondition`'s register-rules event. The
      discount, shipping method and shipping rule conditions extend it, so all three offer it. Its options
      are the payment currencies of the condition's store (`HasStoreInterface`), the store currency first.
      The order index's condition has no store, so it uses the current store. It matches
      `Order::getPaymentCurrency()` and filters the order index on `commerce_orders.paymentCurrency`. BR-13's
      text is a line under the rule's input. The README's copy waits for 5.1 (BR-24).
      `tests/integration/PaymentCurrencyConditionTest.php` covers TS-5 #1–#5, *is not one of*, and the
      order-index query. A shipping rule saved without `storeId` makes every cart save in that store return
      false with no errors. Commerce's `getStore()` throws `InvalidConfigException`, and Craft's save swallows
      that class. The CP always sets the store. TS-5 #1's manual CP check (rule offered, instructions shown) is
      Sam's, in craft5.
- [x] **1.5 Templates and field** — `twigextensions/CurrencyPricesTwigExtension.php`, `fields/CurrencyField.php`
      on Craft 5. Rules: BR-15, BR-16 · Verify: TS-10, TN-15
      — built: the extension is registered in `init()`. Its filters price an item through
      `Pricing::definePrices()` with `Pricing::ratio()` (now public), for the purchasable's store. The current
      customer comes from the logged-in identity when the element is queried. The 3.x `format` and `stripZeros`
      arguments still work, but `format` now defaults to false, so `|currencyPrice('USD')` gives `120`, not
      `$120.00`. **The 5.1 CHANGELOG must say so** (BR-24). The field offers the payment currencies of the
      store for the element's site (else the current site). Commerce lists them alphabetically. A saved value
      the store no longer accepts stays in the list, so a save does not drop it. The input renders only the
      select, because Craft 5's layout element shows the label and instructions. `tests/integration/TemplatesTest.php`
      covers TS-10 #1–#3, BR-14 per item, TN-15 and TN-16. PricingTest's recalculation test now has TS-10 #4's
      20 lines. User groups live in project config, which outlasts a test, so `createUserInGroup()` makes each
      handle unique.
- [ ] **2.1 Bundles 3** — test the layout element and the basket with Bundles 3 installed. Rules: BR-2, BR-17
      · Verify: TS-6 (bundle leg)
- [x] **2.2 Digital Products and Events** — the same for both, and the uninstalled case. Rules: BR-2, BR-17 · Verify: TS-6
      — built before 2.1, because Bundles 3 is not built yet (Sam, 2 Oct 2026). A digital product is an ordinary
      purchasable and needed no code. An Events ticket did (A#6): Events 3.1 generates one per session and
      ticket type, its `defineFieldLayouts()` is empty, and its price is `TicketType->price`, a plain element
      edited on the event type's ticket-type layout. So the ticket type holds the rows, and
      `Purchasables::getPriceOwnerId()` prices every ticket from its `typeId`, for lines, availability, the
      one-query refresh and the Twig filters. The element, rules and save handlers are registered on
      `Purchasable` and, while Events is enabled, on `TicketType`. Events' classes are named only as string
      constants and matched by name, never by `instanceof`, so PHPStan passes with Events absent. Digital
      Products 4.1 and Events 3.1 are dev dependencies. A new `integrations` Codeception suite installs both
      (`tests/integrations/`: DigitalProductsTest, EventsTest, 14 tests). The `integration` suite installs
      neither, which is the uninstalled case (`WithoutIntegrationsTest`, TS-6 #3). With both removed from
      vendor, PHPStan on `src` and the `integration` suite both passed. A mutation (tickets priced by their
      own id) failed 5 Events tests. TS-6's CP and front-end walk in craft5 (AC-7) is Sam's.
- [x] **3.1 3.0.x foreign-key patch** — on a new `3.x` branch from `734fa63`: a migration dropping the three `iso`
      foreign keys, the CHANGELOG and the README upgrade note. First prove on the fixture that the keys break
      Commerce's upgrade. Rules: BR-19 · Verify: TS-8 steps 1–2
      — built 2 Oct 2026 as **3.0.0-beta.6** (`3.x` branch, 617a9ff), keeping 3.x's beta line; the version is Sam's to
      change. The fixture (A#2) is craft4-commerce on Craft 4.18.9, Commerce 4.12.4 and MariaDB 10.11, with GBP and EUR
      payment currencies. 3.0.0-beta.5 is installed from Packagist (it is 734fa63). Variants 4, 6 and 8 have GBP/EUR
      prices, discount 4 and shipping rule 1 (and its category 1) have GBP/EUR amounts. Snapshots `cp3-fixture-3.0.0-beta.5`
      and `cp3-fixture-3.0.0-beta.6`, with composer files in `storage/cp3-fixture-composer` and `storage/cp3-patched-composer`.
      A#1 proven: the Craft 5.11.4 / Commerce 5.7.5 upgrade stopped at m230920_051125 with 1553 "Cannot drop index …
      needed in a foreign key constraint". On 3.0.0-beta.6, `m261002_000000_drop_iso_foreign_keys` dropped the three
      keys, the site still served 200 on Craft 4, and the same upgrade then applied all 162 migrations with the 3.x rows
      intact. Install.php no longer adds the keys (a fresh install has only the four `id` keys). With no CASCADE,
      `removeCurrency()` deletes a removed currency's discount and shipping rows itself, and `renameCurrency()` moves
      them to the new ISO. schemaVersion is 1.2.1, so **3.2 must set 4.0's above 1.2.1**, or Craft refuses the
      downgrade. Two findings for 3.2 and 3.3: 3.x stores discount `purchaseTotal` negated, like the two discount
      amounts; and the first `craft up` after a schema change exits 1 on stale project-config YAML (Craft's own
      behaviour, also seen for Craft and Commerce), then passes when run again.
- [x] **3.2 Upgrade migration** — `migrations/m261001_000000_currency_prices_4.php`. Rules: BR-18, BR-19
      · Verify: TS-8 steps 3–4 and 6, TN-9
      — built 2 Oct 2026; schemaVersion 4.0.0. **`craft up` runs this before Commerce's migrations**: Craft
      held the fixture's plugins as blitz, commerce-currency-prices, commerce. So the migration drops any 3.x
      `iso` foreign keys first (on 3.0.0-beta.5 that lets Commerce's m230920 pass in the same run, no 3.0.x step
      needed), then copies once Commerce has finished. The copy runs from a project-config handler on
      `plugins.commerce.schemaVersion`, which Craft writes after Commerce's track in the console and the CP updater.
      Refusing outright was tried first: Craft restores its backup and the next `craft up` fails the same way.
      **TN-9 as built:** run on its own before Commerce (`migrate/up --plugin=…`), it writes nothing and takes itself
      out of the migration history at the end of the request, with a message, so `craft up` runs it again; it
      returns false only if Commerce is up to date but has no `commerce_stores` table. Commerce 4 already has that
      table, so the pending-Commerce check is what does the work. The copy skips the store-currency column (3.x kept
      the base price there), currencies the primary store does not accept, deleted elements and rows already copied,
      counts negatives as invalid, and prints copied / skipped (by reason) / invalid. `Install::createPriceTable()` is
      shared, since a 3.x site never ran the install. The layout element is added where the upgrade is made (the
      plugin's YAML schemaVersion is below 4.0.0 at the start), and other environments get it with the YAML.
      `addToVariantLayouts()` now falls back to the end of the first tab, or a new Content tab: Commerce defines its
      variant fields only for CP requests, so on the console the fixture's `clothing` type (no saved variant layout)
      had an empty one. Fixture evidence (Craft 5.11.4, Commerce 5.7.5): from `cp3-fixture-3.0.0-beta.6`, 163
      migrations, "copied: 6, skipped: 3 (USD), invalid: 0", element on 1 layout. The CP slideouts of variants 4/6/8
      show EUR 20/21/22 and GBP 10/11/12 beside base prices 62.89/33.36/171.9 (TS-8 #4). The old tables are intact
      (2/2/2 rows, 3 price rows) with 0 iso keys. A second `craft up` exits 0, "No new migrations found" (TS-8 #6), once
      the YAML is written: the first run exits 1 on Craft's stale-YAML check. From `cp3-fixture-3.0.0-beta.5` (keys
      present, YAML already upgraded, so it ran like a deploy): exit 0, keys dropped before m230920, same 6 prices,
      layout from the YAML, then "No pending project config YAML changes". Logs: craft4-commerce `storage/cp4-*.log`.
      Snapshot `cp4-upgraded-from-3.0.0-beta.6` (composer in `storage/cp4-upgraded-composer`) is the starting point for 3.3.
- [x] **3.3 Legacy report** — `console/controllers/LegacyController.php`. Rules: BR-20 · Verify: TS-8 step 5, TN-10
      — built 2 Oct 2026. It reads the four 3.x amount tables directly and touches no Commerce service. Each
      record appears once, with its name and its store-settings CP URL (a shipping rule's store comes through its
      method, since Commerce 5's rules carry no `storeId`) or "deleted", followed by one line per currency listing
      the amounts that are not zero. 3.x saved discount and Addons amounts negated, so they are flipped back. An
      Addons discount gets its name if `addons_discounts` survives, but never a URL, because Addons has no
      Craft 5 version. A row with no amount other than 0 is left out. When no table has an amount, the report
      prints "No Craft 4 data found." `LegacyReportTest` (3 tests) covers this. On the fixture
      (`cp4-upgraded-from-3.0.0-beta.6`), every baseline amount is listed with its sign flipped: discount 4
      "Currency Prices 3.x fixture" GBP 40/4/0.8 and EUR 45/4.5/0.9, shipping rule 1 "Free Everywhere" GBP 3/1
      and EUR 3.5/1, and category "General" on rule 1 GBP 2 and EUR 2.5, each with a
      `…/store-management/primaryStore/…` URL (TS-8 #5). In craft5, which never had 3.x, it prints "No Craft 4
      data found." and exits 0 (TN-10). The URLs follow Commerce's own `getCpEditUrl()` and the shipping-method
      template's rule link. Nobody has opened them in a browser yet: Sam does that during TS-8.
- [x] **4.1 Import** — `console/controllers/ImportController.php`. Rules: BR-21 · Verify: TS-9, TN-11
      — built before 3.x, which is still blocked on A#1/#2 (2 Oct 2026). It walks every purchasable type
      Commerce registers, in every site of the store, and prices each priced element once (an Events ticket
      through its ticket type, read from the ticket type's field). An element whose layout lacks the field,
      and whose owner's does too, counts as skipped as empty. "Unchanged" compares to 4 places, the column's
      scale. A missing option, an unknown store or field, the store currency, or a currency the store does
      not accept exits 64 before anything is read. Text, a negative, or anything else that is not a number
      fails with the item id (TN-11). Any failure makes the exit code 1. The dry run prints the same counts,
      then a line saying nothing was written. `ImportTest` (9 tests) covers TS-9 #1–#4, overwrite,
      `--include-zero`, TN-11 and the stops. The ticket-type path has no test of its own.
- [ ] **5.1 Release** — README, CHANGELOG `## Unreleased`, Store metadata, regression checks, B4.
      Rules: BR-24 · Verify: B5 all, TN-16, the regression table

---

# Appendix A - Open questions

| # | Question | Owner | State |
|---|---|---|---|
| 1 | Do 3.x's foreign keys on `commerce_paymentcurrencies.iso` actually stop Commerce's `m230920_051125` upgrade? Read from source, not yet run | Build (3.1) | **Closed 2 Oct 2026.** Yes: MariaDB 10.11 refused with 1553 and the upgrade stopped. Without the keys it passes (§8 3.1) |
| 2 | A Commerce 4 fixture with 3.x and currency prices on variants, discounts and shipping, in `~/Projects/craftcms/craft4-commerce` | Build (3.1) | **Closed 2 Oct 2026.** Built. Snapshots `cp3-fixture-3.0.0-beta.5` and `cp3-fixture-3.0.0-beta.6` (§8 3.1) |
| 4 | Bundles 3 phase 1 must exist for 2.1 | Bundles 3 build | **Open — blocking build of 2.1** |
| 5 | Plugin Store price for 4.0, and the upgrade price from 3.x licences | Sam | **Open — blocking release** |
| 6 | Do Events 3 tickets use a field layout that accepts a native layout element? | Build (2.2) | **Closed 2 Oct 2026.** No. A ticket has no layout of its own. The ticket-type layout (`TicketType`, an element) does, and it holds the prices (BR-2, §8 2.2) |
| 7 | Sam to approve the copy quoted in §3 and §5 | Sam | **Open — blocking sign-off** |

**Assumptions**

- The plugin's handler runs after a site module's own `EVENT_POPULATE_LINE_ITEM` handler only if it is
  registered later. A site that sets line prices itself, as heathfield does, owns that order. 4.0 documents
  it and does not try to detect it.
- Keeping `order.currency` equal to `paymentCurrency` on an incomplete order is safe in Commerce 5.
  Heathfield's live site does it on every new order today.
- 26 installs (Plugin Store, 1 Oct 2026) are on 3.x. None runs 3.x on more than one store, because Craft
  4 has one.
- Bundles 3 keeps its contents' unit prices in its snapshot in the store currency. This plugin prices
  the bundle line only.

---

# Appendix B - build scaffolding

## B1. Before writing anything

Read `_PROFILE.commerce-currency-prices.md` in full, especially §5 Traps. Snapshot craft5 before
changing its store.

## B2. Context to load

1. `git show 734fa63:src/CurrencyPrices.php` — 3.x's event wiring: what it hooked and why each hook goes (L113-L452)
2. `git show 734fa63:src/migrations/Install.php` — the 3.x tables and the `iso` foreign keys (L73-L358), for 3.1 and 3.2
3. `~/projects/craft5/vendor/craftcms/commerce/src/models/LineItem.php` L894-L975 — population order
4. `…/commerce/src/base/Purchasable.php` L462-L660 — availability, base, price, promotional price
5. `…/commerce/src/elements/Order.php` L1300-L1315, L2699-L2735 — currency defaults and payment amount
6. `…/commerce/src/services/Purchasables.php` L60-L185 and `…/helpers/Order.php` L60-L100 — availability and line removal
7. `…/commerce/src/elements/conditions/orders/OrderCondition.php` and one rule such as `CouponCodeConditionRule.php` — the shape of a rule
8. `…/commerce/src/fieldlayoutelements/PurchasablePriceField.php` — the native element to sit beside
9. `…/commerce/src/migrations/m230920_051125_move_primary_currency_to_store_settings.php` — the index drop (3.1)
10. `~/Projects/heathfield/modules/site/Module.php` L380-L460 and `behaviors/VariantBehavior.php` — the target case
    (rounding and trade pricing via BR-8). Read only.

## B3. Guardrails

- **Do not fork or replace a Commerce adjuster.** BR-12 exists so that fixed amounts per currency need no fork.
  A fork breaks on each Commerce release and collides with sites (heathfield) that replace adjusters themselves.
- **Do not override a purchasable's `getPrice()` or write `basePrice`.** Bundles 3 and catalog pricing read
  Commerce's own price, and the ratio (BR-7) depends on it being untouched.
- **Never convert by exchange rate.** A rate left at 1.0 sells a $100 item for £100 (§1).
- **Never treat a missing price as 0.** That sells the item for nothing (BR-3, BR-11).
- **Do not alter Commerce's tables in a migration.** BR-19 drops only the plugin's own foreign keys.
- **Do not drop the Craft 4 tables in the upgrade migration.** The report (BR-20) is the only record of
  amounts a person must rebuild.
- **Do not reference Bundles, Digital Products or Events classes without checking the plugin is installed.**
  It fatals on a site without them (BR-17). The Bundles 2 code did exactly this.
- **Do not commit in craft5 or in heathfield.**

## B4. Definition of done

- [ ] Every task in §8 ticked, with its verification run
- [ ] B5 #1–#4 pass, output recorded in the commit message or the build memory
- [ ] AC-1–AC-11 walked by Sam in craft5 (TS-8 on the fixture)
- [ ] The test hooks in §7 exist
- [ ] Regression checks done
- [ ] README and CHANGELOG cover every removal and the upgrade steps
- [ ] Appendix A has no open row except those blocking release

## B5. Verification

The profile's §4 commands #1–#4, plus:

```bash
# 5. Install and uninstall cleanly in craft5
ddev exec php craft plugin/install commerce-currency-prices && ddev exec php craft plugin/uninstall commerce-currency-prices && ddev exec php craft plugin/install commerce-currency-prices
# expect: "*** installed …successfully", "*** uninstalled …successfully", "*** installed …successfully"

# 6. Store-only notice and inputs render (craft5, impersonation cookie jar)
# A variant has no edit page of its own in Craft 5: its layout renders in a slideout, fetched like this
curl -s -b jar -c jar -H 'Accept: application/json' -H 'X-Requested-With: XMLHttpRequest' -H 'X-Craft-Container-Id: slideout1' \
  'https://craft5.ddev.site/admin/actions/elements/edit?elementId=<variant id>' | grep -o 'data-currency-price=\\"[A-Z]*' | sort -u
# expect: one line per extra payment currency (none, and data-currency-prices-none in the page, when there are none)
```

| AC | Checked by |
|---|---|
| AC-1, AC-2 | TS-1 (Codeception + CP walk) |
| AC-3, AC-4, AC-5, AC-8, AC-10, AC-11 | Codeception (TS-2, 3, 4, 7, 9, 10) and a front-end walk of TS-2 |
| AC-6 | Codeception + CP walk (TS-5) |
| AC-7 | TS-6 in craft5 |
| AC-9 | TS-8 on the Commerce 4 fixture |

## B6. Out of bounds

- Heathfield's code, templates and data (read only).
- formech's copy of the plugin.
- Commerce's own tables and migrations.
- Pushing, tagging or publishing to the Plugin Store (Sam's).

---

## Change log

| Date | Version | Change | By |
|---|---|---|---|
| 2026-10-01 | 0.1 | First draft from the interview with Sam | Claude |
| 2026-10-01 | 0.1 | Sam approved changing craft5 for task 1.2. Appendix A #3 is closed and moved into the test data | Claude |
| 2026-10-01 | 0.1 | Task 0.2 built. B2 #1 and #2 now point at the 3.x source in `734fa63`, since 0.2 replaced both files | Claude |
| 2026-10-01 | 0.1 | Task 1.2 built. craft5's store turned out to be in USD, so EUR and GBP were added as its extra currencies instead of USD and EUR | Claude |
| 2026-10-02 | 0.1 | B5 #6 run. It found the install bug noted under 1.2. The command now fetches the variant slideout, since a variant has no edit page of its own | Claude |
| 2026-10-02 | 0.1 | Task 1.3 built. Added `OrderCurrencyBehavior`, because Commerce formats amounts in the store currency whatever the order's currency is | Claude |
| 2026-10-02 | 0.1 | Task 2.2 built ahead of 2.1. A#6 closed: Events tickets are priced from their ticket type. BR-2, the §4 `purchasableId` row and TS-6 #1 now say so | Claude |
| 2026-10-02 | 0.1 | Task 3.1 built as 3.0.0-beta.6 on the `3.x` branch. A#1 and A#2 closed: the iso keys do stop Commerce 5's upgrade, and the fixture is in craft4-commerce | Claude |
