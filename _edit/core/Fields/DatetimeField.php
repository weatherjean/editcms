<?php

declare(strict_types=1);

namespace Edit\Core\Fields;

class DatetimeField extends BaseField
{
    public function validate(mixed $value, array $config): bool
    {
        if (!parent::validate($value, $config)) {
            return false;
        }

        if (!empty($value)) {
            // Accept both datetime-local format and ISO 8601
            $formats = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:s\Z', 'Y-m-d H:i:s'];
            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date) {
                    return true;
                }
            }

            // Also try to parse with DateTime constructor for flexible format support
            try {
                new \DateTime($value);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }

    public function toDatabase(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        // Convert to ISO 8601 format for storage
        $date = new \DateTime($value);
        return $date->format('Y-m-d\TH:i:s\Z');
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

        // Convert ISO to datetime-local format if needed
        $displayValue = '';
        if ($value) {
            $date = new \DateTime($value);
            $displayValue = $date->format('Y-m-d\TH:i');
        }
        $escapedValue = $this->escape($displayValue);

        return <<<HTML
        <div class="field-group">
            <label for="{$name}" class="field-label">{$label}</label>
            <input
                type="datetime-local"
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
