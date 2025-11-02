<?php

declare(strict_types=1);

namespace Edit\Core\ContentTypes;

/**
 * ContentTypeRegistry - Loads and manages content types from modular config files
 *
 * Configuration structure:
 * - modules/*.json: Post types with their specific field groups
 * - field-groups/*.json: Shared/reusable field groups
 * - blocks/*.json: Flexible content blocks (managed by BlockRegistry)
 */
class ContentTypeRegistry
{
    private array $contentTypes = [];
    private array $postTypes = [];
    private array $fieldGroups = [];
    private string $configPath;
    private bool $loaded = false;

    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? EDIT_BASE_PATH . '/config';
    }

    /**
     * Load content types from modular config files
     */
    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->contentTypes = [];
        $this->postTypes = [];
        $this->fieldGroups = [];

        // Load modules (post types + their field groups)
        $this->loadModules();

        // Load shared field groups
        $this->loadSharedFieldGroups();

        // Build content types from post types and field groups
        $this->buildContentTypes();

        $this->loaded = true;
    }

    /**
     * Load modules from modules/ directory
     * Each module can contain post_types and field_groups
     */
    private function loadModules(): void
    {
        $modulesPath = $this->configPath . '/modules';

        if (!is_dir($modulesPath)) {
            return;
        }

        $files = glob($modulesPath . '/*.json');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $module = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Failed to parse module file: {$file} - " . json_last_error_msg());
                continue;
            }

            // Merge post types from this module
            if (isset($module['post_types']) && is_array($module['post_types'])) {
                foreach ($module['post_types'] as $postType) {
                    if (isset($postType['key'])) {
                        $this->postTypes[$postType['key']] = $postType;
                    }
                }
            }

            // Merge field groups from this module
            if (isset($module['field_groups']) && is_array($module['field_groups'])) {
                foreach ($module['field_groups'] as $fieldGroup) {
                    if (isset($fieldGroup['key'])) {
                        $this->fieldGroups[$fieldGroup['key']] = $fieldGroup;
                    }
                }
            }
        }
    }

    /**
     * Load shared field groups from field-groups/ directory
     */
    private function loadSharedFieldGroups(): void
    {
        $fieldGroupsPath = $this->configPath . '/field-groups';

        if (!is_dir($fieldGroupsPath)) {
            return;
        }

        $files = glob($fieldGroupsPath . '/*.json');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $fieldGroup = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Failed to parse field group file: {$file} - " . json_last_error_msg());
                continue;
            }

            if (isset($fieldGroup['key'])) {
                // Shared field groups can override module field groups
                $this->fieldGroups[$fieldGroup['key']] = $fieldGroup;
            }
        }
    }

    /**
     * Build content types by combining post types with their field groups
     */
    private function buildContentTypes(): void
    {
        foreach ($this->postTypes as $key => $postType) {
            // Find all field groups assigned to this post type
            $assignedFieldGroups = array_filter($this->fieldGroups, function ($group) use ($key) {
                return in_array($key, $group['locations'] ?? []);
            });

            // Build fields array from all assigned field groups
            $fields = [];
            foreach ($assignedFieldGroups as $group) {
                foreach ($group['fields'] ?? [] as $field) {
                    $fields[$field['key']] = $field;
                }
            }

            $this->contentTypes[$key] = [
                'key' => $key,
                'label' => $postType['label'],
                'label_plural' => $postType['label_plural'],
                'description' => $postType['description'] ?? '',
                'icon' => $postType['icon'] ?? 'file',
                'allow_open' => $postType['allow_open'] ?? false,
                'fields' => $fields
            ];
        }
    }

    /**
     * Get all content types
     */
    public function getAll(): array
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->contentTypes;
    }

    /**
     * Get a single content type by key
     */
    public function get(string $type): ?array
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->contentTypes[$type] ?? null;
    }

    /**
     * Check if a content type exists
     */
    public function exists(string $type): bool
    {
        if (!$this->loaded) {
            $this->load();
        }

        return isset($this->contentTypes[$type]);
    }

    /**
     * Get all content type keys
     */
    public function getTypes(): array
    {
        if (!$this->loaded) {
            $this->load();
        }

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
     * Get all post types (raw data from config)
     */
    public function getPostTypes(): array
    {
        if (!$this->loaded) {
            $this->load();
        }

        return array_values($this->postTypes);
    }

    /**
     * Get all field groups (raw data from config)
     */
    public function getFieldGroups(): array
    {
        if (!$this->loaded) {
            $this->load();
        }

        return array_values($this->fieldGroups);
    }

    /**
     * Reload configuration from disk
     */
    public function reload(): void
    {
        $this->loaded = false;
        $this->load();
    }
}
