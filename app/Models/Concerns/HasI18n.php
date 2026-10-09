<?php

namespace App\Models\Concerns;

trait HasI18n
{
    public function getAttribute($key): mixed
    {
        $value = parent::getAttribute($key);

        if (! is_string($key) || ! is_string($value) || ! in_array($key, $this->translatableAttributes(), true)) {
            return $value;
        }

        $locale = app()->getLocale();

        if ($locale === 'ru') {
            return $value;
        }

        $translated = data_get($this->getAttribute('i18n'), $key.'.'.$locale);

        return is_string($translated) && $translated !== '' ? $translated : $value;
    }

    public function stored(string $attribute): string
    {
        $value = $this->getAttributes()[$attribute] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @return list<string>
     */
    protected function translatableAttributes(): array
    {
        return [];
    }
}
