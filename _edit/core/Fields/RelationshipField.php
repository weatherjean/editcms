<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class RelationshipField extends BaseField
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
        return !empty($value) ? (int) $value : null;
    }

    public function toDatabase(mixed $value): string
    {
        return (string) ($value ?? '');
    }

    public function fromDatabase(string $value): mixed
    {
        return !empty($value) ? (int) $value : null;
    }

    public function renderInput(array $config, mixed $value = null): string
    {
        $name = $this->getName($config);
        $label = $this->getLabel($config);
        $required = $this->isRequired($config) ? 'required' : '';
        $target = $config['target'] ?? 'content';
        $escapedValue = $value ? $this->escape((string) $value) : '';

        return <<<HTML
        <div class="field-group">
            <label for="{$name}" class="field-label">{$label}</label>
            <select
                id="{$name}"
                name="{$name}"
                class="field-select field-relationship"
                data-relationship-target="{$target}"
                {$required}
            >
                <option value="">-- Select --</option>
                <!-- Options will be loaded via JS from API -->
            </select>
            <input type="hidden" name="{$name}_value" value="{$escapedValue}" />
        </div>
        HTML;
    }
}
