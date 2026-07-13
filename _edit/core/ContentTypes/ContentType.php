<?php

declare(strict_types=1);

namespace Edit\Core\ContentTypes;

use Edit\Core\Database\Database;

class ContentType
{
    private Database $db;
    private string $type;
    private array $config;
    private array $fieldInstances = [];
    private $blockRegistry = null;

    public function __construct(Database $db, string $type, array $config, $blockRegistry = null, private bool $resolveReferences = true)
    {
        $this->db = $db;
        $this->type = $type;
        $this->config = $config;
        $this->blockRegistry = $blockRegistry;
        $this->initializeFields();
    }

    /**
     * Initialize field type instances
     */
    private function initializeFields(): void
    {
        foreach (ContentSchema::fields($this->config) as $fieldKey => $fieldConfig) {
            $fieldType = $fieldConfig['type'];
            if ($fieldType === 'flexible_content') {
                continue;
            }
            $className = 'Edit\\Core\\Fields\\' . ucfirst($fieldType) . 'Field';

            if (class_exists($className)) {
                $this->fieldInstances[$fieldKey] = new $className();
            }
        }
    }

    /**
     * Create new content
     */
    public function create(array $data): int
    {
        $this->db->beginTransaction();

        try {
            // Extract core fields
            $slug = $data['slug'] ?? null;
            $status = array_key_exists('status', $data) ? $data['status'] : 'draft';
            $authorId = $data['author_id'] ?? null;

            $data['fields'] = (new ContentValidator($this->db, $this->blockRegistry))->validate(array_key_exists('fields', $data) ? $data['fields'] : [], $this->config, $status === 'published');
            if (!in_array($status, ['draft', 'published'], true)) {
                throw new \InvalidArgumentException('Invalid content status');
            }

            // Slug is required and must be URL-safe
            if (!is_string($slug) || $slug === '') {
                throw new \InvalidArgumentException("Slug is required");
            }
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                throw new \InvalidArgumentException("Slug must contain only lowercase letters, numbers, and hyphens (no spaces or special characters)");
            }

            // Ensure slug uniqueness for this content type
            if ($this->slugExists($slug)) {
                throw new \InvalidArgumentException("Slug already exists for this content type");
            }

            $contentId = $this->db->table('content')->insert([
                'type' => $this->type,
                'slug' => $slug,
                'status' => $status,
                'author_id' => $authorId
            ]);

            // Save meta fields
            if (isset($data['fields'])) {
                $this->saveMeta($contentId, $data['fields']);
            }

            $this->db->commit();
            return $contentId;
        } catch (\Throwable $e) {
            try {
                $this->db->rollback();
            } catch (\Exception $rollbackEx) {
                // Log rollback failure but don't mask original error
                error_log("Rollback failed: " . $rollbackEx->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Update existing content
     */
    public function update(int $id, array $data): bool
    {
        return $this->transaction(fn () => $this->updateRecord($id, $data));
    }

    /** Called only inside the transaction owned by update or restore. */
    private function updateRecord(int $id, array $data): bool
    {
        // Get current content before updating (for revision snapshot)
        $currentContent = $this->db->table('content')
            ->where('id', $id)
            ->where('type', $this->type)
            ->first();

        if (!$currentContent) {
            throw new \OutOfBoundsException("Content not found");
        }

        $status = array_key_exists('status', $data) ? $data['status'] : $currentContent['status'];
        if (!in_array($status, ['draft', 'published'], true)) {
            throw new \InvalidArgumentException('Invalid content status');
        }
        $fields = array_key_exists('fields', $data) ? $data['fields'] : $this->getMeta($id);
        $validated = (new ContentValidator($this->db, $this->blockRegistry))->validate($fields, $this->config, $status === 'published');
        if (array_key_exists('fields', $data)) {
            $data['fields'] = $validated;
        }

        // Build update data for core fields
        $updateData = [];

        if (array_key_exists('slug', $data)) {
            // Slug is required and cannot be empty
            if (!is_string($data['slug']) || $data['slug'] === '') {
                throw new \InvalidArgumentException("Slug is required and cannot be empty");
            }
            // Validate slug format
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['slug'])) {
                throw new \InvalidArgumentException("Slug must contain only lowercase letters, numbers, and hyphens (no spaces or special characters)");
            }

            // Check slug uniqueness (excluding current item)
            $count = $this->db->table('content')
                ->where('type', $this->type)
                ->where('slug', $data['slug'])
                ->where('id', '!=', $id)
                ->count();

            if ($count > 0) {
                throw new \InvalidArgumentException("Slug already exists for this content type");
            }
            $updateData['slug'] = $data['slug'];
        }

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        if (isset($data['author_id'])) {
            $updateData['author_id'] = $data['author_id'];
        }

        // Always update timestamp
        // Note: QueryBuilder doesn't support CURRENT_TIMESTAMP directly, so we use PHP
        $updateData['updated_at'] = now();

        // Create revision snapshot before updating
        $this->createRevision($id, $currentContent);

        if (!empty($updateData)) {
            $this->db->table('content')
                ->where('id', $id)
                ->update($updateData);
        }

        if (isset($data['fields'])) {
            $this->db->table('content_meta')
                ->where('content_id', $id)
                ->delete();
            $this->saveMeta($id, $data['fields']);
        }

        // Cleanup old revisions (keep last 10)
        $this->cleanupOldRevisions($id);

        return true;
    }

    private function transaction(callable $operation): bool
    {
        $this->db->beginTransaction();
        try {
            $result = $operation();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Delete content
     */
    public function delete(int $id): bool
    {
        try {
            // Foreign key cascade will delete meta automatically
            $this->db->table('content')
                ->where('id', $id)
                ->where('type', $this->type)
                ->delete();
            return true;
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to delete content: {$e->getMessage()}");
        }
    }

    /**
     * Find single content by ID
     */
    public function find(int $id): ?array
    {
        $result = $this->db->table('content')
            ->where('id', $id)
            ->where('type', $this->type)
            ->first();

        if (!$result) {
            return null;
        }

        $result['fields'] = $this->getMeta($id);

        // Populate relationships
        if ($this->resolveReferences) {
            $result = $this->populateRelationships($result);
        }

        return $result;
    }

    /**
     * Get all content of this type
     */
    public function all(array $filters = []): array
    {
        // Start building query
        $query = $this->db->table('content')->where('type', $this->type);

        // Apply basic filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['slug'])) {
            $query->where('slug', $filters['slug']);
        }

        if (isset($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }

        // Apply field filters using EXISTS subqueries
        $this->applyFieldFilters($query, $filters);

        // Apply ordering
        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDir = $filters['order_dir'] ?? 'DESC';
        $orderDir = strtoupper($orderDir);
        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException('Invalid sort direction');
        }
        if (in_array($orderBy, ContentQuery::CORE_SORTS, true)) {
            $query->orderByExpression('content.' . $orderBy . ' ' . $orderDir . ', content.id ASC');
        } else {
            [$path,$field] = ContentQuery::field($this->config, $orderBy, !$this->resolveReferences);
            $value = ContentQuery::expression($field, 'sort_meta.meta_value');
            $expression = "(SELECT {$value} FROM content_meta sort_meta WHERE sort_meta.content_id = content.id AND sort_meta.meta_key = ? LIMIT 1)";
            $query->orderByExpression("{$expression} IS NULL ASC, {$expression} {$orderDir}, content.id ASC", [$path, $path]);
        }

        // Apply pagination
        if (isset($filters['limit'])) {
            $query->limit((int) $filters['limit']);

            if (isset($filters['offset'])) {
                $query->offset((int) $filters['offset']);
            }
        }

        // Execute query
        $results = $query->get();

        // Batch load meta for all content items (solves N+1 query problem)
        if (!empty($results)) {
            $contentIds = array_column($results, 'id');
            $allMeta = $this->getMetaBatch($contentIds);

            // Populate meta and relationships for each item
            foreach ($results as &$item) {
                $item['fields'] = $allMeta[(int)$item['id']] ?? [];
                if ($this->resolveReferences) {
                    $item = $this->populateRelationships($item);
                }
            }
        }

        return $results;
    }

    /**
     * Count content items matching filters
     */
    public function count(array $filters = []): int
    {
        // Start building query
        $query = $this->db->table('content')->where('type', $this->type);

        // Apply basic filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['slug'])) {
            $query->where('slug', $filters['slug']);
        }

        if (isset($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }

        // Apply field filters using EXISTS subqueries
        $this->applyFieldFilters($query, $filters);

        return $query->count();
    }

    /**
     * Get the database instance (for public API)
     */
    public function getDatabase(): Database
    {
        return $this->db;
    }

    /**
     * Save meta fields to database
     * Handles both flat and grouped field structures
     */
    private function saveMeta(int $contentId, array $fields): void
    {
        foreach ($fields as $key => $value) {
            // Check if this is a field group (nested object)
            if (is_array($value) && $this->isFieldGroup($key, $value)) {
                // It's a field group - save each field with composite key
                $fieldGroupKey = $key;
                foreach ($value as $fieldKey => $fieldValue) {
                    $compositeKey = "{$fieldGroupKey}.{$fieldKey}";
                    $this->saveMetaField($contentId, $fieldKey, $fieldValue, $compositeKey);
                }
            } else {
                // It's a regular field (like flexible_content)
                $this->saveMetaField($contentId, $key, $value, $key);
            }
        }
    }

    /**
     * Save a single meta field
     */
    private function saveMetaField(int $contentId, string $fieldKey, mixed $value, string $dbKey): void
    {
        $dbValue = $value;

        if (isset($this->fieldInstances[$dbKey])) {
            $dbValue = $this->fieldInstances[$dbKey]->toDatabase($value);
        } elseif (is_array($value)) {
            $dbValue = json_encode($value, JSON_THROW_ON_ERROR);
        }

        $this->db->table('content_meta')->insert([
            'content_id' => $contentId,
            'meta_key' => $dbKey,
            'meta_value' => $dbValue
        ]);
    }

    /**
     * Check if a key/value pair represents a field group
     */
    private function isFieldGroup(string $key, mixed $value): bool
    {
        // If it's not an array, it's not a field group
        if (!is_array($value)) {
            return false;
        }

        // Check if this key exists in field_groups config
        if (isset($this->config['field_groups'])) {
            foreach ($this->config['field_groups'] as $group) {
                if ($group['key'] === $key) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Retrieve meta fields from database
     * Reconstructs grouped field structure
     */
    private function getMeta(int $contentId): array
    {
        $meta = $this->db->table('content_meta')
            ->select(['meta_key', 'meta_value'])
            ->where('content_id', $contentId)
            ->get();

        $fields = [];

        foreach ($meta as $row) {
            $dbKey = $row['meta_key'];
            $value = $row['meta_value'];

            // Check if this is a composite key (field_group.field)
            if (str_contains($dbKey, '.')) {
                [$fieldGroupKey, $fieldKey] = explode('.', $dbKey, 2);

                // Initialize field group if it doesn't exist
                if (!isset($fields[$fieldGroupKey])) {
                    $fields[$fieldGroupKey] = [];
                }

                // Deserialize using field instance
                if (isset($this->fieldInstances[$dbKey])) {
                    $fieldInstance = $this->fieldInstances[$dbKey];
                    $fields[$fieldGroupKey][$fieldKey] = $fieldInstance->fromDatabase($value);
                } else {
                    $fields[$fieldGroupKey][$fieldKey] = $value;
                }
            } else {
                // Regular field (not grouped, like flexible_content)
                if (isset($this->fieldInstances[$dbKey])) {
                    $fieldInstance = $this->fieldInstances[$dbKey];
                    $fields[$dbKey] = $fieldInstance->fromDatabase($value);
                } else {
                    // Try to JSON decode if it's a string (for flexible_content, etc.)
                    if (is_string($value)) {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $fields[$dbKey] = $decoded;
                        } else {
                            $fields[$dbKey] = $value;
                        }
                    } else {
                        $fields[$dbKey] = $value;
                    }
                }
            }
        }

        return ContentHtml::clean($fields, $this->config, $this->blockRegistry);
    }

    /**
     * Retrieve meta fields for multiple content IDs in a single query (batch loading)
     * Reconstructs grouped field structure
     *
     * @param array $contentIds Array of content IDs
     * @return array Associative array with content_id as key and fields array as value
     */
    private function getMetaBatch(array $contentIds): array
    {
        if (empty($contentIds)) {
            return [];
        }

        // Build IN clause for content_ids
        $placeholders = str_repeat('?,', count($contentIds) - 1) . '?';
        $meta = $this->db->query(
            "SELECT content_id, meta_key, meta_value FROM content_meta WHERE content_id IN ($placeholders)",
            $contentIds
        );

        // Group by content_id
        $result = [];
        foreach ($meta as $row) {
            $contentId = (int)$row['content_id'];
            $dbKey = $row['meta_key'];
            $value = $row['meta_value'];

            if (!isset($result[$contentId])) {
                $result[$contentId] = [];
            }

            // Check if this is a composite key (field_group.field)
            if (str_contains($dbKey, '.')) {
                [$fieldGroupKey, $fieldKey] = explode('.', $dbKey, 2);

                // Initialize field group if it doesn't exist
                if (!isset($result[$contentId][$fieldGroupKey])) {
                    $result[$contentId][$fieldGroupKey] = [];
                }

                // Deserialize using field instance
                if (isset($this->fieldInstances[$dbKey])) {
                    $fieldInstance = $this->fieldInstances[$dbKey];
                    $result[$contentId][$fieldGroupKey][$fieldKey] = $fieldInstance->fromDatabase($value);
                } else {
                    $result[$contentId][$fieldGroupKey][$fieldKey] = $value;
                }
            } else {
                // Regular field (not grouped)
                if (isset($this->fieldInstances[$dbKey])) {
                    $fieldInstance = $this->fieldInstances[$dbKey];
                    $result[$contentId][$dbKey] = $fieldInstance->fromDatabase($value);
                } else {
                    // Try to JSON decode if it's a string (for flexible_content, etc.)
                    if (is_string($value)) {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $result[$contentId][$dbKey] = $decoded;
                        } else {
                            $result[$contentId][$dbKey] = $value;
                        }
                    } else {
                        $result[$contentId][$dbKey] = $value;
                    }
                }
            }
        }

        // Ensure all content IDs have an entry (even if no meta)
        foreach ($contentIds as $id) {
            if (!isset($result[$id])) {
                $result[$id] = [];
            }
        }

        foreach ($result as &$fields) {
            $fields = ContentHtml::clean($fields, $this->config, $this->blockRegistry);
        }
        return $result;
    }

    /**
     * Populate relationship fields with actual data
     * Works with both grouped and flat field structures
     */
    private function populateRelationships(array $content): array
    {
        if (!isset($content['fields']) || !isset($this->config['fields'])) {
            return $content;
        }

        try {
            // Check if we have field groups
            $hasFieldGroups = isset($this->config['field_groups']) && !empty($this->config['field_groups']);

            if ($hasFieldGroups) {
                // Process grouped structure
                foreach ($this->config['field_groups'] as $group) {
                    $fieldGroupKey = $group['key'];

                    if (!isset($content['fields'][$fieldGroupKey]) || !is_array($content['fields'][$fieldGroupKey])) {
                        continue;
                    }

                    foreach ($group['fields'] as $fieldConfig) {
                        if (!isset($fieldConfig['key']) || !isset($fieldConfig['type'])) {
                            continue;
                        }

                        $fieldKey = $fieldConfig['key'];
                        $fieldType = $fieldConfig['type'];

                        $content['fields'][$fieldGroupKey] = $this->populateFieldValue(
                            $content['fields'][$fieldGroupKey],
                            $fieldKey,
                            $fieldType,
                            $fieldConfig
                        );
                    }
                }
            }

            // Also handle top-level fields (like flexible_content)
            foreach ($this->config['fields'] as $fieldKey => $fieldConfig) {
                if (!isset($fieldConfig['type'])) {
                    continue;
                }

                $fieldType = $fieldConfig['type'];

                // Only process if field exists at top level (not in a group)
                if (isset($content['fields'][$fieldKey]) && !$hasFieldGroups || ($hasFieldGroups && $fieldKey === 'flexible_content')) {
                    $content['fields'] = $this->populateFieldValue(
                        $content['fields'],
                        $fieldKey,
                        $fieldType,
                        $fieldConfig
                    );
                }
            }

            // Special handling for flexible_content (populate media inside blocks)
            if (isset($content['fields']['flexible_content']) && is_array($content['fields']['flexible_content'])) {
                foreach ($content['fields']['flexible_content'] as $blockIndex => &$block) {
                    if (isset($block['fields']) && is_array($block['fields']) && isset($block['block_type'])) {
                        $block['fields'] = $this->populateFlexibleContentBlockFields($block['fields'], $block['block_type']);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("Error in populateRelationships: " . $e->getMessage());
        }

        return $content;
    }

    /**
     * Recursively populate media and relationship fields in flexible content blocks
     */
    private function populateFlexibleContentBlockFields(array $fields, string $blockType, ?array $parentFieldConfig = null): array
    {
        // Get block definition if we have BlockRegistry
        $blockDefinition = null;
        if ($this->blockRegistry) {
            $blockDefinition = $this->blockRegistry->getBlock($blockType);
        }

        // Build field config map from block definition
        $fieldConfigMap = [];
        if ($blockDefinition && isset($blockDefinition['fields'])) {
            foreach ($blockDefinition['fields'] as $fieldConfig) {
                if (isset($fieldConfig['key'])) {
                    $fieldConfigMap[$fieldConfig['key']] = $fieldConfig;
                }
            }
        }
        // If we have parent field config (for repeaters), use that instead
        if ($parentFieldConfig) {
            $fieldConfigMap = $parentFieldConfig;
        }

        foreach ($fields as $key => &$value) {
            $fieldConfig = $fieldConfigMap[$key] ?? null;
            $fieldType = $fieldConfig['type'] ?? null;

            // Handle single numeric values
            if (is_numeric($value) && $fieldType) {
                if ($fieldType === 'media') {
                    // Auto-populate media fields (always)
                    $media = $this->db->table('media')->where('id', (int)$value)->first();
                    if ($media) {
                        $value = addMediaUrl($media);
                    }
                } elseif ($fieldType === 'relationship') {
                    // Populate relationship (for admin) - just basic info
                    $related = $this->db->table('content')
                        ->select(['id', 'type', 'slug', 'status'])
                        ->where('id', (int)$value)
                        ->first();
                    if ($related) {
                        $value = $related;
                    }
                }
            }
            // Handle arrays
            elseif (is_array($value) && !empty($value)) {
                $isNumericArray = array_keys($value) === range(0, count($value) - 1);

                if ($isNumericArray) {
                    // Check if it's a repeater field
                    if ($fieldType === 'repeater' && isset($fieldConfig['config']['fields'])) {
                        // Build field map for repeater subfields
                        $repeaterFieldMap = [];
                        foreach ($fieldConfig['config']['fields'] as $subField) {
                            if (isset($subField['key'])) {
                                $repeaterFieldMap[$subField['key']] = $subField;
                            }
                        }

                        // Recursively populate each repeater item
                        foreach ($value as $index => &$item) {
                            if (is_array($item)) {
                                $value[$index] = $this->populateFlexibleContentBlockFields($item, $blockType, $repeaterFieldMap);
                            }
                        }
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Populate a single field value (media, relationship, or repeater)
     */
    private function populateFieldValue(array $fields, string $fieldKey, string $fieldType, array $fieldConfig): array
    {
        if (!isset($fields[$fieldKey])) {
            return $fields;
        }

        try {
            // Handle media fields
            if ($fieldType === 'media') {
                $fields[$fieldKey] = $this->populateMediaField($fields[$fieldKey]);
            }

            // Handle relationship fields
            if ($fieldType === 'relationship') {
                $relatedId = $fields[$fieldKey];
                $target = $fieldConfig['target'] ?? 'content';

                if ($relatedId) {
                    if ($target === 'user') {
                        $related = $this->db->table('users')
                            ->select(['id', 'name', 'email'])
                            ->where('id', $relatedId)
                            ->first();
                    } else {
                        $related = $this->db->table('content')
                            ->select(['id', 'type', 'slug'])
                            ->where('id', $relatedId)
                            ->first();
                    }

                    if ($related) {
                        $fields[$fieldKey] = $related;
                    }
                }
            }

            // Handle repeater fields - recursively populate nested media fields
            if ($fieldType === 'repeater' && is_array($fields[$fieldKey])) {
                $repeaterItems = $fields[$fieldKey];
                foreach ($repeaterItems as $index => $item) {
                    if (is_array($item) && isset($fieldConfig['config']['fields'])) {
                        foreach ($fieldConfig['config']['fields'] as $subField) {
                            if ($subField['type'] === 'media' && isset($item[$subField['key']])) {
                                $fields[$fieldKey][$index][$subField['key']] =
                                    $this->populateMediaField($item[$subField['key']]);
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("Failed to populate field {$fieldKey}: " . $e->getMessage());
        }

        return $fields;
    }

    /**
     * Populate a media field with full media data
     * Handles both single media ID and arrays of IDs
     */
    private function populateMediaField($mediaId)
    {
        if (!$mediaId) {
            return null;
        }

        // Handle array of media IDs
        if (is_array($mediaId)) {
            $mediaItems = [];
            foreach ($mediaId as $id) {
                if (!$id) {
                    continue;
                }
                $media = $this->db->table('media')->where('id', $id)->first();
                if ($media) {
                    $mediaItems[] = addMediaUrl($media);
                }
            }
            return !empty($mediaItems) ? $mediaItems : null;
        }

        // Handle single media ID
        $media = $this->db->table('media')->where('id', $mediaId)->first();
        if ($media) {
            // Use global helper function from helpers.php to add URL
            return addMediaUrl($media);
        }

        return $mediaId;
    }

    /**
     * Apply field filters to a query using EXISTS subqueries
     *
     * @param mixed $query QueryBuilder instance
     * @param array $filters Filters array containing field_filters
     * @return void
     */
    private function applyFieldFilters($query, array $filters): void
    {
        foreach ($filters['field_filters'] ?? [] as $filter) {
            [$path,$field] = ContentQuery::field($this->config, $filter['key'], !$this->resolveReferences);
            $operator = $filter['operator'];
            $value = ContentQuery::value($filter['value'], $field, $operator);
            $left = ContentQuery::expression($field, 'm.meta_value');
            $right = ContentQuery::expression($field, '?');
            if ($operator === 'LIKE') {
                $value = '%' . $value . '%';
            }
            $query->whereExists("SELECT 1 FROM content_meta m WHERE m.content_id = content.id AND m.meta_key = ? AND {$left} {$operator} {$right}", [$path, $value]);
        }
    }

    /**
     * Check if slug already exists for this content type
     */
    private function slugExists(string $slug): bool
    {
        return $this->db->table('content')
            ->where('type', $this->type)
            ->where('slug', $slug)
            ->count() > 0;
    }

    /**
     * Create a revision snapshot of current content
     */
    private function createRevision(int $contentId, array $currentContent): void
    {
        // Get current meta fields
        $currentFields = $this->getMeta($contentId);

        // Get next revision number
        $lastRevision = $this->db->query(
            "SELECT MAX(revision_number) as max_rev FROM content_revisions WHERE content_id = ?",
            [$contentId]
        );
        $nextRevisionNumber = ($lastRevision[0]['max_rev'] ?? 0) + 1;

        // Create revision record
        $this->db->table('content_revisions')->insert([
            'content_id' => $contentId,
            'revision_number' => $nextRevisionNumber,
            'slug' => $currentContent['slug'],
            'status' => $currentContent['status'],
            'fields' => json_encode($currentFields, JSON_THROW_ON_ERROR),
            'author_id' => $currentContent['author_id']
        ]);
    }

    /**
     * Cleanup old revisions - keep only last 10
     */
    private function cleanupOldRevisions(int $contentId): void
    {
        // Get count of revisions
        $count = $this->db->table('content_revisions')
            ->where('content_id', $contentId)
            ->count();

        // If more than 10, delete oldest ones
        if ($count > 10) {
            $toDelete = $count - 10;

            // Get IDs of oldest revisions to delete
            $oldRevisions = $this->db->query(
                "SELECT id FROM content_revisions WHERE content_id = ? ORDER BY revision_number ASC, id ASC LIMIT ?",
                [$contentId, $toDelete]
            );

            $idsToDelete = array_column($oldRevisions, 'id');

            if (!empty($idsToDelete)) {
                $placeholders = str_repeat('?,', count($idsToDelete) - 1) . '?';
                $this->db->execute(
                    "DELETE FROM content_revisions WHERE id IN ($placeholders)",
                    $idsToDelete
                );
            }
        }
    }

    /**
     * Get all revisions for a content item
     */
    public function getRevisions(int $contentId): array
    {
        $revisions = $this->db->query(
            "SELECT r.*, u.name as author_name
             FROM content_revisions r
             LEFT JOIN users u ON r.author_id = u.id
             WHERE r.content_id = ? AND EXISTS (SELECT 1 FROM content c WHERE c.id = r.content_id AND c.type = ?)
             ORDER BY r.revision_number DESC",
            [$contentId, $this->type]
        );

        // Decode fields JSON for each revision
        foreach ($revisions as &$revision) {
            $revision['fields'] = ContentHtml::clean(json_decode($revision['fields'], true) ?? [], $this->config, $this->blockRegistry);
        }

        return $revisions;
    }

    /**
     * Restore a revision - creates a new revision with old data
     */
    public function restoreRevision(int $contentId, int $revisionId): bool
    {
        return $this->transaction(function () use ($contentId, $revisionId): bool {
            $revision = $this->db->table('content_revisions')->where('id', $revisionId)->where('content_id', $contentId)->first();
            if (!$revision) {
                throw new \InvalidArgumentException('Revision not found');
            }
            $fields = json_decode($revision['fields'], true, 64, JSON_THROW_ON_ERROR);
            // Validate against the current schema. Incompatible old snapshots fail
            // atomically and remain available for manual recovery.
            return $this->updateRecord($contentId, [
                'slug' => $revision['slug'], 'status' => $revision['status'], 'fields' => $fields,
            ]);
        });
    }
}
