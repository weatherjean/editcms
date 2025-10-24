<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class NumberField extends BaseField
{
    public function validate(mixed $value, array $config): bool
    {
        if (!parent::validate($value, $config)) {
            return false;
        }

        if (!empty($value) && !is_numeric($value)) {
            return false;
        }

        return true;
    }

    public function sanitize(mixed $value, array $config): mixed
    {
        if (empty($value)) {
            return null;
        }

        return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    public function toDatabase(mixed $value): string
    {
        return (string) $value;
    }

    public function fromDatabase(string $value): mixed
    {
        if (empty($value)) {
            return null;
        }

        return strpos($value, '.') !== false ? (float) $value : (int) $value;
    }

    public function renderInput(array $config, mixed $value = null): string
    {
        $name = $this->getName($config);
        $label = $this->getLabel($config);
        $required = $this->isRequired($config) ? 'required' : '';
        $escapedValue = $value !== null ? $this->escape((string) $value) : '';
        $min = isset($config['min']) ? "min=\"{$config['min']}\"" : '';
        $max = isset($config['max']) ? "max=\"{$config['max']}\"" : '';
        $step = isset($config['step']) ? "step=\"{$config['step']}\"" : '';

        return <<<HTML
        <div class="field-group">
            <label for="{$name}" class="field-label">{$label}</label>
            <input
                type="number"
                id="{$name}"
                name="{$name}"
                value="{$escapedValue}"
                class="field-input"
                {$min}
                {$max}
                {$step}
                {$required}
            />
        </div>
        HTML;
    }
}
