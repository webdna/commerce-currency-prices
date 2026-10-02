<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\behaviors;

use craft\base\ElementInterface;
use yii\base\Behavior;

/**
 * Carries the currency prices posted for a purchasable until its save writes them (BR-4).
 *
 * Null means nothing was posted, and the save leaves the item's rows untouched. Set it to an array of
 * ISO ⇒ price (a number, a blank, or the money input's `['value' => …, 'locale' => …]`) to write
 * those currencies; a blank deletes one (BR-3). Currencies left out are untouched.
 *
 * @property ElementInterface $owner A purchasable, or an Events ticket type
 * @author webdna
 * @since 4.0.0
 */
class CurrencyPricesBehavior extends Behavior
{
    /**
     * @var array<string, mixed>|null Posted price by ISO code, or null when none were posted
     */
    public ?array $currencyPrices = null;
}
