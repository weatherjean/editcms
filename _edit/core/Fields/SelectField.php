<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class SelectField extends BaseField
{
    public function validate(mixed $value, array $config): bool
    {
        if (!parent::validate($value, $config)) {
            return false;
        }

        // Check if value is in allowed options
        if (!empty($value) && isset($config['options'])) {
            return in_array($value, $config['options'], true);
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
        $options = $config['options'] ?? [];

        $optionsHtml = '<option value="">-- Select --</option>';
        foreach ($options as $option) {
            $escapedOption = $this->escape($option);
            $selected = ($value === $option) ? 'selected' : '';
            $optionsHtml .= "<option value=\"{$escapedOption}\" {$selected}>{$escapedOption}</option>";
        }

        return <<<HTML
        <div class="field-group">
            <label for="{$name}" class="field-label">{$label}</label>
            <select
                id="{$name}"
                name="{$name}"
                class="field-select"
                {$required}
            >
                {$optionsHtml}
            </select>
        </div>
        HTML;
    }
}
