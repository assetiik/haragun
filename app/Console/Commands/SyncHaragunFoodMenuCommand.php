<?php

namespace App\Console\Commands;

use App\Support\HaragunFoodCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('haragun:sync-food-menu')]
#[Description('Заполнить раздел Еда Haragun по меню фудкорта')]
class SyncHaragunFoodMenuCommand extends Command
{
    public function handle(): int
    {
        try {
            $result = HaragunFoodCatalog::sync();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Создано: {$result['created']}, обновлено: {$result['updated']}");

        if ($result['skipped'] !== []) {
            $this->warn('Нет категорий (тип «Еда») для: '.implode(', ', $result['skipped']));
            $this->line('Создай их в Haragun → Категории блюд с типом «Еда».');
        }

        return self::SUCCESS;
    }
}
