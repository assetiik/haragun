<?php

namespace App\Console\Commands;

use App\Services\DssSoft\DssSoftClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('dss-soft:ping')]
#[Description('Проверить доступ к DSS-Soft: события и дни катка')]
class DssSoftPingCommand extends Command
{
    public function handle(DssSoftClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('DSS-Soft не настроен. Заполни DSS_SOFT_PARTNER_TOKEN и ключ в .env');

            return self::FAILURE;
        }

        try {
            $shows = $client->shows();
            $showId = $client->showId();
            $match = collect($shows)->firstWhere('id', $showId);

            $this->info('Show id: '.$showId);
            $this->info('Событий в списке: '.count($shows));

            if ($match === null) {
                $this->warn("Id {$showId} в списке событий нет — возможно, это не show id.");
            } else {
                $title = data_get($match, 'title.ru') ?? data_get($match, 'title') ?? 'без названия';
                $this->info('Найдено: '.$title);
            }

            $days = $client->days($showId);
            $this->info('Дней: '.count($days));

            foreach (array_slice($days, 0, 5) as $day) {
                $this->line('  '.$day['id'].'  '.($day['date'] ?? ''));
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
