<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class WysiwygField extends BaseField
{
    public function toDatabase(mixed $value): string
    {
        return (string) $value;
    }

    public function fromDatabase(string $value): mixed
    {
        return $value;
    }

    public function sanitize(mixed $value, array $config): mixed
    {
        // Allow HTML tags for WYSIWYG content
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
            <textarea
                id="{$name}"
                name="{$name}"
                class="field-wysiwyg"
                data-wysiwyg="true"
                {$required}
            >{$escapedValue}</textarea>
        </div>
        HTML;
    }
}
