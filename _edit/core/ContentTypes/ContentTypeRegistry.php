<?php

declare(strict_types=1);

namespace Edit\Core\ContentTypes;

class ContentTypeRegistry
{
    private array $contentTypes = [];
    private string $configPath;

    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? EDIT_BASE_PATH . '/config';
    }

    /**
     * Load content types from JSON config file
     */
    public function load(): void
    {
        $this->contentTypes = [];

        // Load config from single JSON file
        $configFile = $this->configPath . '/config.json';
        if (!file_exists($configFile)) {
            throw new \RuntimeException("Config file not found: {$configFile}");
        }

        $config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON in config.json: " . json_last_error_msg());
        }

        $postTypes = $config['post_types'] ?? [];
        $fieldGroups = $config['field_groups'] ?? [];

        // Build content types from post types and field groups
        foreach ($postTypes as $postType) {
            $key = $postType['key'];

            // Find all field groups assigned to this post type
            $assignedFieldGroups = array_filter($fieldGroups, function ($group) use ($key) {
                return in_array($key, $group['locations'] ?? []);
            });

            // Build fields array from all assigned field groups
            $fields = [];
            foreach ($assignedFieldGroups as $group) {
                foreach ($group['fields'] ?? [] as $field) {
                    $fields[$field['key']] = [
                        'type' => $field['type'],
                        'label' => $field['label'],
                        'instructions' => $field['instructions'] ?? '',
                        'required' => $field['required'] ?? false,
                        'default_value' => $field['default_value'] ?? '',
                    ];

                    // Merge field config if present
                    if (isset($field['config']) && is_array($field['config'])) {
                        $fields[$field['key']] = array_merge($fields[$field['key']], $field['config']);
                    }
                }
            }

            $this->contentTypes[$key] = [
                'label' => $postType['label'],
                'label_plural' => $postType['label_plural'],
                'description' => $postType['description'] ?? '',
                'icon' => $postType['icon'] ?? 'file',
                'fields' => $fields
            ];
        }
    }

    /**
     * Get all content types
     */
    public function getAll(): array
    {
        return $this->contentTypes;
    }

    /**
     * Get a single content type by key
     */
    public function get(string $type): ?array
    {
        return $this->contentTypes[$type] ?? null;
    }

    /**
     * Check if a content type exists
     */
    public function exists(string $type): bool
    {
        return isset($this->contentTypes[$type]);
    }

    /**
     * Get all content type keys
     */
    public function getTypes(): array
    {
        return array_keys($this->contentTypes);
    }

    /**
     * Get field configuration for a specific content type
     */
    public function getFields(string $type): array
    {
        $contentType = $this->get($type);
        return $contentType['fields'] ?? [];
    }

    /**
     * Get a specific field configuration
     */
    public function getField(string $type, string $fieldKey): ?array
    {
        $fields = $this->getFields($type);
        return $fields[$fieldKey] ?? null;
    }

    /**
     * Get raw config from JSON
     */
    public function getConfig(): array
    {
        $configFile = $this->configPath . '/config.json';
        $config = json_decode(file_get_contents($configFile), true);
        return $config ?? ['post_types' => [], 'field_groups' => []];
    }

    /**
     * Get raw post types from JSON
     */
    public function getPostTypes(): array
    {
        $config = $this->getConfig();
        return $config['post_types'] ?? [];
    }

    /**
     * Get raw field groups from JSON
     */
    public function getFieldGroups(): array
    {
        $config = $this->getConfig();
        return $config['field_groups'] ?? [];
    }

    /**
     * Save entire config to JSON
     */
    public function saveConfig(array $config): bool
    {
        $configFile = $this->configPath . '/config.json';
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to encode config: " . json_last_error_msg());
        }

        return file_put_contents($configFile, $json) !== false;
    }
}
