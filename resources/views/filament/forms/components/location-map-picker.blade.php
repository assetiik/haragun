@php
    $latPath = $getLatitudeStatePath();
    $lngPath = $getLongitudeStatePath();
    $defaultLat = $getDefaultLatitude();
    $defaultLng = $getDefaultLongitude();
    $defaultZoom = $getDefaultZoom();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="haragunLocationMap({
            latPath: @js($latPath),
            lngPath: @js($lngPath),
            defaultLat: {{ $defaultLat }},
            defaultLng: {{ $defaultLng }},
            defaultZoom: {{ $defaultZoom }},
        })"
        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
    >
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-3 py-2 text-sm text-gray-600 dark:border-white/10 dark:text-gray-300">
            <span>Кликните по карте или перетащите метку — координаты уйдут во Flutter</span>
            <button
                type="button"
                class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-500"
                x-on:click="useMyLocation()"
            >
                Моё местоположение
            </button>
        </div>
        <div x-ref="map" style="height: 360px; width: 100%;"></div>
    </div>
</x-dynamic-component>
