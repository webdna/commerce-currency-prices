<?php

declare(strict_types=1);

use craft\ecs\SetList;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function(ECSConfig $ecsConfig): void {
    // src/ joins in task 0.2, when the 3.x Craft 4 code is replaced.
    $ecsConfig->paths([
        __DIR__ . '/tests',
        __FILE__,
    ]);
    $ecsConfig->skip([
        __DIR__ . '/tests/_craft/storage',
        __DIR__ . '/tests/_output',
        __DIR__ . '/tests/Support/_generated',
    ]);
    $ecsConfig->parallel();
    $ecsConfig->sets([SetList::CRAFT_CMS_4]);
};
