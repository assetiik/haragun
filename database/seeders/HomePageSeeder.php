<?php

namespace Database\Seeders;

use App\Enums\EstablishmentType;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\City;
use App\Models\Establishment;
use Illuminate\Database\Seeder;

class HomePageSeeder extends Seeder
{
    public function run(): void
    {
        $almaty = City::query()->updateOrCreate(
            ['slug' => 'almaty'],
            [
                'name' => 'Алматы',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        City::query()->updateOrCreate(
            ['slug' => 'astana'],
            [
                'name' => 'Астана',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        Banner::query()->updateOrCreate(
            [
                'city_id' => $almaty->id,
                'title' => '-20% на аренду коньков',
            ],
            [
                'subtitle' => 'В будни до 17:00',
                'background_color' => '#6B4EFF',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Banner::query()->updateOrCreate(
            [
                'city_id' => $almaty->id,
                'title' => 'Кофе в подарок',
            ],
            [
                'subtitle' => 'При заказе от 5000 ₸',
                'background_color' => '#8B5E3C',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        $arenaBrand = Brand::query()->updateOrCreate(
            [
                'city_id' => $almaty->id,
                'slug' => 'haragun-ice-arena',
            ],
            [
                'name' => 'Ледовая арена Haragun',
                'type' => EstablishmentType::IceArena,
                'icon_key' => 'ice_arena',
                'accent_color' => '#6B4EFF',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Establishment::query()->updateOrCreate(
            [
                'city_id' => $almaty->id,
                'name' => 'Ледовая арена Haragun',
            ],
            [
                'brand_id' => $arenaBrand->id,
                'address' => 'пр. Достык, 240',
                'phone' => '77770000001',
                'type' => EstablishmentType::IceArena,
                'latitude' => 43.2335,
                'longitude' => 76.9560,
                'opens_at' => '10:00:00',
                'closes_at' => '22:00:00',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        $moscowBrand = Brand::query()->updateOrCreate(
            [
                'city_id' => $almaty->id,
                'slug' => 'coffee-moscow',
            ],
            [
                'name' => 'Кофейня «Москва»',
                'type' => EstablishmentType::Coffee,
                'icon_key' => 'coffee',
                'accent_color' => '#C26E40',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        $moscowAddresses = [
            [
                'address' => 'ул. Сейфуллина 154',
                'phone' => '77777777777',
                'latitude' => 43.2560,
                'longitude' => 76.9280,
                'opens_at' => '10:00:00',
                'closes_at' => '18:00:00',
                'sort_order' => 1,
            ],
            [
                'address' => 'ул. Қабанбай батыр 128',
                'phone' => '77777777778',
                'latitude' => 43.2389,
                'longitude' => 76.9455,
                'opens_at' => '09:00:00',
                'closes_at' => '22:00:00',
                'sort_order' => 2,
            ],
            [
                'address' => 'ул. Кунаева, 12',
                'phone' => '77777777779',
                'latitude' => 43.2567,
                'longitude' => 76.9286,
                'opens_at' => '08:00:00',
                'closes_at' => '23:00:00',
                'sort_order' => 3,
            ],
        ];

        foreach ($moscowAddresses as $address) {
            Establishment::query()->updateOrCreate(
                [
                    'brand_id' => $moscowBrand->id,
                    'address' => $address['address'],
                ],
                [
                    'city_id' => $almaty->id,
                    'name' => $moscowBrand->name,
                    'phone' => $address['phone'],
                    'type' => EstablishmentType::Coffee,
                    'latitude' => $address['latitude'],
                    'longitude' => $address['longitude'],
                    'opens_at' => $address['opens_at'],
                    'closes_at' => $address['closes_at'],
                    'is_active' => true,
                    'sort_order' => $address['sort_order'],
                ],
            );
        }

        $arbatBrand = Brand::query()->updateOrCreate(
            [
                'city_id' => $almaty->id,
                'slug' => 'coffee-arbat',
            ],
            [
                'name' => 'Кофейня «Арбат»',
                'type' => EstablishmentType::Coffee,
                'icon_key' => 'coffee',
                'accent_color' => '#C26E40',
                'is_active' => true,
                'sort_order' => 3,
            ],
        );

        Establishment::query()->updateOrCreate(
            [
                'brand_id' => $arbatBrand->id,
                'address' => 'пр. Абая, 44',
            ],
            [
                'city_id' => $almaty->id,
                'name' => $arbatBrand->name,
                'phone' => '77771112233',
                'type' => EstablishmentType::Coffee,
                'latitude' => 43.2380,
                'longitude' => 76.9450,
                'opens_at' => '09:00:00',
                'closes_at' => '22:00:00',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Establishment::query()->updateOrCreate(
            [
                'brand_id' => $arbatBrand->id,
                'address' => 'ул. Толе би, 101',
            ],
            [
                'city_id' => $almaty->id,
                'name' => $arbatBrand->name,
                'phone' => '77771112234',
                'type' => EstablishmentType::Coffee,
                'latitude' => 43.2505,
                'longitude' => 76.9188,
                'opens_at' => '10:00:00',
                'closes_at' => '21:00:00',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );
    }
}
