<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class DateField extends BaseField
{
    public function validate(mixed $value, array $config): bool
    {
        if (!parent::validate($value, $config)) {
            return false;
        }

        if (!empty($value)) {
            $date = \DateTime::createFromFormat('Y-m-d', $value);
            return $date && $date->format('Y-m-d') === $value;
        }

        return true;
    }

    public function toDatabase(mixed $value): string
    {
        return (string) $value;
    }

    public function fromDatabase(string $value): mixed
    {
        return $value;
    }

    public function renderInput(array $config, mixed $value = null): string
    {
        $name = $this->getName($config);
        $label = $this->getLabel($config);
        $required = $this->isRequired($config) ? 'required' : '';
        $escapedValue = $value ? $this->escape((string) $value) : '';

        return <<<HTML
        <div class="field-group">
            <label for="{$name}" class="field-label">{$label}</label>
            <input
                type="date"
                id="{$name}"
                name="{$name}"
                value="{$escapedValue}"
                class="field-input"
                {$required}
            />
        </div>
        HTML;
    }
}
