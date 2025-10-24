<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class MediaField extends BaseField
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
        $escapedValue = $value ? $this->escape((string) $value) : '';

        return <<<HTML
        <div class="field-group">
            <label class="field-label">{$label}</label>
            <div class="media-field" data-field="{$name}">
                <input
                    type="hidden"
                    id="{$name}"
                    name="{$name}"
                    value="{$escapedValue}"
                    {$required}
                />
                <button type="button" class="btn btn-secondary media-picker-btn" data-target="{$name}">
                    Select Media
                </button>
                <div class="media-preview" id="{$name}-preview">
                    <!-- Preview will be rendered here by JS -->
                </div>
            </div>
        </div>
        HTML;
    }
}
