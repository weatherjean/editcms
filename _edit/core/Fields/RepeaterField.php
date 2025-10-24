<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class RepeaterField extends BaseField
{
    public function validate(mixed $value, array $config): bool
    {
        if ($this->isRequired($config) && empty($value)) {
            return false;
        }

        // Value should be an array
        if (!empty($value) && !is_array($value)) {
            return false;
        }

        return true;
    }

    public function sanitize(mixed $value, array $config): mixed
    {
        if (!is_array($value)) {
            return [];
        }

        return $value;
    }

    public function toDatabase(mixed $value): string
    {
        return json_encode($value ?? []);
    }

    public function fromDatabase(string $value): mixed
    {
        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function renderInput(array $config, mixed $value = null): string
    {
        $name = $this->getName($config);
        $label = $this->getLabel($config);
        $subFields = $config['fields'] ?? [];
        $values = is_array($value) ? $value : [];

        // Encode config for JS
        $configJson = htmlspecialchars(json_encode($subFields), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
        <div class="field-group field-repeater" data-field="{$name}">
            <label class="field-label">{$label}</label>
            <div class="repeater-items" id="{$name}-items" data-config='{$configJson}'>
        HTML;

        // Render existing items
        foreach ($values as $index => $itemData) {
            $html .= $this->renderRepeaterItem($name, $index, $subFields, $itemData);
        }

        $html .= <<<HTML
            </div>
            <button type="button" class="btn btn-secondary repeater-add" data-target="{$name}">
                + Add {$label}
            </button>
        </div>
        HTML;

        return $html;
    }

    private function renderRepeaterItem(string $parentName, int $index, array $fields, array $values = []): string
    {
        $html = '<div class="repeater-item" data-index="' . $index . '">';

        foreach ($fields as $fieldKey => $fieldConfig) {
            $fieldName = "{$parentName}[{$index}][{$fieldKey}]";
            $fieldValue = $values[$fieldKey] ?? null;
            $fieldType = $fieldConfig['type'] ?? 'text';

            // Simple rendering for repeater sub-fields
            $label = $fieldConfig['label'] ?? $fieldKey;
            $escapedValue = $fieldValue ? htmlspecialchars((string) $fieldValue, ENT_QUOTES, 'UTF-8') : '';

            if ($fieldType === 'select') {
                $options = $fieldConfig['options'] ?? [];
                $optionsHtml = '';
                foreach ($options as $option) {
                    $selected = ($fieldValue === $option) ? 'selected' : '';
                    $optionsHtml .= "<option value=\"{$option}\" {$selected}>{$option}</option>";
                }

                $html .= <<<HTML
                <div class="repeater-field">
                    <label>{$label}</label>
                    <select name="{$fieldName}" class="field-select">
                        <option value="">-- Select --</option>
                        {$optionsHtml}
                    </select>
                </div>
                HTML;
            } else {
                $html .= <<<HTML
                <div class="repeater-field">
                    <label>{$label}</label>
                    <input type="text" name="{$fieldName}" value="{$escapedValue}" class="field-input" />
                </div>
                HTML;
            }
        }

        $html .= <<<HTML
            <button type="button" class="btn btn-danger repeater-remove">Remove</button>
        </div>
        HTML;

        return $html;
    }
}
