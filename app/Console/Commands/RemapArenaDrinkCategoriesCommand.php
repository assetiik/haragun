<?php

namespace App\Console\Commands;

use App\Support\ArenaDrinkCategoryRemapper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('haragun:remap-arena-drink-categories')]
#[Description('Привязать напитки арены к категориям из «Категории блюд» по slug')]
class RemapArenaDrinkCategoriesCommand extends Command
{
    public function handle(): int
    {
        $moved = ArenaDrinkCategoryRemapper::remap();

        $this->info("Обновлено товаров: {$moved}");

        return self::SUCCESS;
    }
}
