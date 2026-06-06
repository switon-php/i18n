<?php

declare(strict_types=1);

use Switon\Core\App;
use Switon\Core\TranslatorInterface;

if (!function_exists('t')) {
    function t(string $id, array $bind = [], bool $useICU = false): string
    {
        return App::get(TranslatorInterface::class)->translate($id, $bind, $useICU);
    }
}
