<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class SlugField extends BaseField
{
    public function sanitize(mixed $value, array $config): mixed
    {
        if (empty($value)) {
            return '';
        }

        // Convert to lowercase and replace spaces/special chars with hyphens
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
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
        $sourceField = isset($config['source']) ? $this->escape($config['source']) : 'title';

        return <<<HTML
        <div class="field-group">
            <label for="{$name}" class="field-label">{$label}</label>
            <input
                type="text"
                id="{$name}"
                name="{$name}"
                value="{$escapedValue}"
                class="field-input field-slug"
                data-slug-source="{$sourceField}"
                {$required}
            />
            <small class="field-hint">Auto-generated from {$sourceField}. Edit to customize.</small>
        </div>
        HTML;
    }
}
