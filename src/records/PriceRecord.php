<?php
/**
 * @link https://webdna.co.uk/
 * @copyright Copyright (c) webdna
 */

namespace webdna\commerce\currencyprices\records;

use craft\db\ActiveRecord;

/**
 * One typed price for a purchasable in one of its store's payment currencies (spec §4).
 *
 * An absent row means "no price", so a blank input deletes the row rather than storing 0 (BR-3).
 *
 * @property int $id
 * @property int $purchasableId
 * @property int $storeId
 * @property string $currency
 * @property string $price
 *
 * @author webdna
 * @since 4.0.0
 */
class PriceRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%currencyprices_prices}}';
    }
}
