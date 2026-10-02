<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\services;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Model;
use craft\commerce\base\Purchasable;
use craft\commerce\fieldlayoutelements\PurchasablePriceField;
use craft\commerce\Plugin as Commerce;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\DefineRulesEvent;
use craft\events\ModelEvent;
use craft\helpers\Localization;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use webdna\commerce\currencyprices\behaviors\CurrencyPricesBehavior;
use webdna\commerce\currencyprices\CurrencyPrices;
use webdna\commerce\currencyprices\fieldlayoutelements\CurrencyPricesField;
use yii\base\Component;

/**
 * Edits a purchasable's currency prices: the layout element, validation, and the writes in the element's
 * own save (BR-2–BR-6).
 *
 * The handlers are registered on Commerce's base `Purchasable`, so variants, bundles and digital products
 * are covered without referencing their classes (BR-17).
 *
 * An Events ticket is the exception. Events generates one per session and ticket type, and a ticket has
 * no layout of its own: its price is the ticket type's, edited on the event type's ticket-type layout. So
 * a ticket type holds the currency prices, and every ticket of that type is priced from them. Events'
 * classes are named here as strings, and used only while Events is enabled.
 *
 * @author webdna
 * @since 4.0.0
 */
class Purchasables extends Component
{
    /**
     * The attribute the layout element posts and the behavior holds.
     */
    public const ATTRIBUTE = 'currencyPrices';

    public const BEHAVIOR = 'currencyPrices';

    public const EVENTS_HANDLE = 'events';

    public const EVENTS_TICKET = 'verbb\\events\\elements\\Ticket';

    public const EVENTS_TICKET_TYPE = 'verbb\\events\\elements\\TicketType';

    /**
     * @var string[]|null Class names, which need not exist
     */
    private ?array $_pricedElementTypes = null;

    /**
     * The element classes that carry currency prices: Commerce's base purchasable, and an Events ticket
     * type while Events is enabled.
     *
     * @return string[] Class names
     */
    public function getPricedElementTypes(): array
    {
        if ($this->_pricedElementTypes === null) {
            $this->_pricedElementTypes = [Purchasable::class];

            if (Craft::$app->getPlugins()->isPluginEnabled(self::EVENTS_HANDLE) && class_exists(self::EVENTS_TICKET_TYPE)) {
                $this->_pricedElementTypes[] = self::EVENTS_TICKET_TYPE;
            }
        }

        return $this->_pricedElementTypes;
    }

    public function isPricedElement(mixed $element): bool
    {
        if (!$element instanceof ElementInterface) {
            return false;
        }

        foreach ($this->getPricedElementTypes() as $type) {
            if ($element instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * The element whose currency prices price this purchasable: an Events ticket's ticket type, otherwise
     * the purchasable itself.
     */
    public function getPriceOwnerId(Purchasable $purchasable): int
    {
        // Compared by name, never `instanceof`, so nothing here needs Events' classes to exist (BR-17).
        $classes = [$purchasable::class, ...array_values(class_parents($purchasable) ?: [])];

        if (in_array(self::EVENTS_TICKET_TYPE, $this->getPricedElementTypes(), true) && in_array(self::EVENTS_TICKET, $classes, true)) {
            $typeId = $purchasable->getAttributes(['typeId'])['typeId'] ?? null;
            if ($typeId) {
                return (int)$typeId;
            }
        }

        return (int)$purchasable->id;
    }

    public function defineBehaviors(DefineBehaviorsEvent $event): void
    {
        $event->behaviors[self::BEHAVIOR] = CurrencyPricesBehavior::class;
    }

    /**
     * Makes the posted prices assignable from the request, and fails the element's validation for a
     * negative or non-numeric one (BR-3, TN-1).
     */
    public function defineRules(DefineRulesEvent $event): void
    {
        /** @var ElementInterface&Model $purchasable */
        $purchasable = $event->sender;

        $event->rules[] = [[self::ATTRIBUTE], 'safe'];
        $event->rules[] = [[self::ATTRIBUTE], function(string $attribute) use ($purchasable): void {
            foreach ($this->normalizePostedPrices($purchasable)['invalid'] as $currency) {
                $purchasable->addError($attribute, Craft::t('commerce-currency-prices', 'Enter a price of 0 or more for {currency}.', [
                    'currency' => $currency,
                ]));
            }
        }];
    }

    /**
     * Offers the layout element on the field layout of every purchasable type, and on an Events ticket-type
     * layout (BR-2).
     */
    public function defineNativeFields(DefineFieldLayoutFieldsEvent $event): void
    {
        /** @var FieldLayout $fieldLayout */
        $fieldLayout = $event->sender;
        if (!$fieldLayout->type) {
            return;
        }

        foreach ($this->getPricedElementTypes() as $type) {
            if (is_a($fieldLayout->type, $type, true)) {
                $event->fields[] = CurrencyPricesField::class;
                return;
            }
        }
    }

    /**
     * Runs after Commerce has written the element's store values (BR-4). A duplicate, a new draft, an
     * applied draft and a revision are all saved with `duplicateOf` set, so each copies its source's rows
     * first (BR-5). Posted prices are then written for the store of the site being saved (BR-6).
     * Propagating saves to the element's other sites write nothing.
     */
    public function afterSave(ModelEvent $event): void
    {
        $purchasable = $event->sender;
        if (!$purchasable instanceof Element || !$this->isPricedElement($purchasable) || $purchasable->propagating || !$purchasable->id) {
            return;
        }

        $prices = CurrencyPrices::getInstance()->getPrices();
        $source = $purchasable->duplicateOf;

        if ($source instanceof ElementInterface && $source->id && $source->id !== $purchasable->id) {
            $prices->copyPrices($source->id, $purchasable->id);
        }

        /** @var CurrencyPricesBehavior|null $behavior */
        $behavior = $purchasable->getBehavior(self::BEHAVIOR);
        if ($behavior === null || $behavior->currencyPrices === null) {
            return;
        }

        $posted = $this->normalizePostedPrices($purchasable);
        $behavior->currencyPrices = null;

        if ($posted['storeId'] === null || empty($posted['prices'])) {
            return;
        }

        foreach ($posted['invalid'] as $currency) {
            Craft::warning("Skipped an invalid $currency price on purchasable $purchasable->id saved without validation.", __METHOD__);
        }

        $prices->savePrices($purchasable->id, $posted['storeId'], $posted['prices']);
    }

    /**
     * The posted prices, limited to the eligible currencies of the element's store (BR-1). A currency
     * the store no longer accepts is ignored, so its row survives for when it is re-added (TN-3).
     *
     * @return array{storeId: int|null, prices: array<string, float|null>, invalid: string[]}
     */
    public function normalizePostedPrices(ElementInterface $purchasable): array
    {
        /** @var CurrencyPricesBehavior|null $behavior */
        $behavior = $purchasable->getBehavior(self::BEHAVIOR);
        $storeId = $this->getStoreId($purchasable);
        $result = ['storeId' => $storeId, 'prices' => [], 'invalid' => []];

        if ($storeId === null || $behavior === null || empty($behavior->currencyPrices)) {
            return $result;
        }

        $posted = array_change_key_case($behavior->currencyPrices, CASE_UPPER);

        foreach (CurrencyPrices::getInstance()->getPrices()->getEligibleCurrencies($storeId) as $currency) {
            if (!array_key_exists($currency, $posted)) {
                continue;
            }

            $value = $posted[$currency];
            if (is_array($value)) {
                $locale = $value['locale'] ?? null;
                $value = $value['value'] ?? null;
                if (is_string($value) && $locale) {
                    $value = Localization::normalizeNumber($value, $locale);
                }
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                $result['prices'][$currency] = null;
            } elseif (is_numeric($value) && $value >= 0) {
                $result['prices'][$currency] = (float)$value;
            } else {
                $result['invalid'][] = $currency;
            }
        }

        return $result;
    }

    /**
     * The store of the element's site, or null when the site belongs to none.
     */
    public function getStoreId(ElementInterface $purchasable): ?int
    {
        try {
            if ($purchasable instanceof Purchasable) {
                return $purchasable->getStore()->id;
            }

            return $purchasable->siteId ? Commerce::getInstance()->getStores()->getStoreBySiteId($purchasable->siteId)?->id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Adds the layout element below the price element of each variant layout that lacks one (BR-2).
     * Other purchasable types' layouts are left for an admin.
     *
     * A layout without the price element gets it at the end of its first tab, or in a new tab if it has
     * none. That happens on the console, where Commerce defines its variant fields only for CP requests,
     * so a product type with no saved variant layout (common after the Commerce 4 → 5 upgrade) has an
     * empty one. In the CP, Craft puts Commerce's mandatory fields ahead of it.
     *
     * @return int The number of layouts changed
     */
    public function addToVariantLayouts(): int
    {
        return $this->changeVariantLayouts(function(FieldLayout $layout): void {
            $element = new CurrencyPricesField(['uid' => StringHelper::UUID()]);
            $tabs = $layout->getTabs();

            foreach ($tabs as $tab) {
                $elements = $tab->getElements();
                foreach ($elements as $i => $tabElement) {
                    if ($tabElement instanceof PurchasablePriceField) {
                        array_splice($elements, $i + 1, 0, [$element]);
                        $tab->setElements($elements);
                        return;
                    }
                }
            }

            if (!empty($tabs)) {
                $tabs[0]->setElements([...$tabs[0]->getElements(), $element]);
                return;
            }

            $layout->setTabs([
                new FieldLayoutTab([
                    'layout' => $layout,
                    'name' => Craft::t('app', 'Content'),
                    'uid' => StringHelper::UUID(),
                    'elements' => [$element],
                ]),
            ]);
        }, false);
    }

    /**
     * Takes the layout element out of every variant layout, on uninstall.
     *
     * @return int The number of layouts changed
     */
    public function removeFromVariantLayouts(): int
    {
        return $this->changeVariantLayouts(function(FieldLayout $layout): void {
            foreach ($layout->getTabs() as $tab) {
                $elements = $tab->getElements();
                $kept = array_values(array_filter($elements, fn($element) => !$element instanceof CurrencyPricesField));
                if (count($kept) !== count($elements)) {
                    $tab->setElements($kept);
                }
            }
        }, true);
    }

    /**
     * @param callable(FieldLayout): void $change Changes the layout
     * @param bool $whenPresent Whether to change layouts that have the element (true) or those that lack it
     */
    private function changeVariantLayouts(callable $change, bool $whenPresent): int
    {
        $productTypes = Commerce::getInstance()->getProductTypes();
        $changed = 0;

        foreach ($productTypes->getAllProductTypes() as $productType) {
            $layout = $productType->getVariantFieldLayout();
            if (!empty($layout->getElementsByType(CurrencyPricesField::class)) !== $whenPresent) {
                continue;
            }

            $change($layout);

            // The layout is the product type's own cached instance, so the save picks up the change.
            if ($productTypes->saveProductType($productType)) {
                $changed++;
            } else {
                Craft::warning("Could not update the variant layout of product type $productType->handle: " . implode(', ', $productType->getFirstErrors()), __METHOD__);
            }
        }

        return $changed;
    }
}
