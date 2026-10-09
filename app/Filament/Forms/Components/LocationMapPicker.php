<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class LocationMapPicker extends Field
{
    protected string $view = 'filament.forms.components.location-map-picker';

    protected string $latitudeField = 'latitude';

    protected string $longitudeField = 'longitude';

    /**
     * Default map center (Almaty).
     */
    protected float $defaultLatitude = 43.238949;

    protected float $defaultLongitude = 76.945813;

    protected int $defaultZoom = 14;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrated(false);
    }

    public function latitudeField(string $name): static
    {
        $this->latitudeField = $name;

        return $this;
    }

    public function longitudeField(string $name): static
    {
        $this->longitudeField = $name;

        return $this;
    }

    public function defaultCenter(float $latitude, float $longitude): static
    {
        $this->defaultLatitude = $latitude;
        $this->defaultLongitude = $longitude;

        return $this;
    }

    public function defaultZoom(int $zoom): static
    {
        $this->defaultZoom = $zoom;

        return $this;
    }

    public function getLatitudeStatePath(): string
    {
        return $this->resolveRelativeStatePath($this->latitudeField);
    }

    public function getLongitudeStatePath(): string
    {
        return $this->resolveRelativeStatePath($this->longitudeField);
    }

    public function getDefaultLatitude(): float
    {
        return $this->defaultLatitude;
    }

    public function getDefaultLongitude(): float
    {
        return $this->defaultLongitude;
    }

    public function getDefaultZoom(): int
    {
        return $this->defaultZoom;
    }
}
