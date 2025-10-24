<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class TextareaField extends BaseField
{
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
        $rows = $config['rows'] ?? 5;
        $placeholder = isset($config['placeholder']) ? $this->escape($config['placeholder']) : '';

        return <<<HTML
        <div class="field-group">
            <label for="{$name}" class="field-label">{$label}</label>
            <textarea
                id="{$name}"
                name="{$name}"
                rows="{$rows}"
                placeholder="{$placeholder}"
                class="field-textarea"
                {$required}
            >{$escapedValue}</textarea>
        </div>
        HTML;
    }
}
