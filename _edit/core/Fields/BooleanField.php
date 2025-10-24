<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class BooleanField extends BaseField
{
    public function sanitize(mixed $value, array $config): mixed
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function toDatabase(mixed $value): string
    {
        return $value ? '1' : '0';
    }

    public function fromDatabase(string $value): mixed
    {
        return $value === '1';
    }

    public function renderInput(array $config, mixed $value = null): string
    {
        $name = $this->getName($config);
        $label = $this->getLabel($config);
        $checked = $value ? 'checked' : '';
        $description = isset($config['description']) ? $this->escape($config['description']) : '';

        return <<<HTML
        <div class="field-group">
            <label class="field-checkbox">
                <input
                    type="checkbox"
                    id="{$name}"
                    name="{$name}"
                    value="1"
                    {$checked}
                />
                <span>{$label}</span>
            </label>
            {$description}
        </div>
        HTML;
    }
}
