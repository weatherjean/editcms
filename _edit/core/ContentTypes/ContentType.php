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

    public function __construct(Database $db, string $type, array $config)
    {
        $this->db = $db;
        $this->type = $type;
        $this->config = $config;
        $this->initializeFields();
    }

    /**
     * Initialize field type instances
     */
    private function initializeFields(): void
    {
        foreach ($this->config['fields'] as $fieldKey => $fieldConfig) {
            $fieldType = $fieldConfig['type'];
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
            $status = $data['status'] ?? 'draft';
            $authorId = $data['author_id'] ?? null;

            // Slug is required and must be URL-safe
            if (empty($slug)) {
                throw new \RuntimeException("Slug is required");
            }
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                throw new \RuntimeException("Slug must contain only lowercase letters, numbers, and hyphens (no spaces or special characters)");
            }

            // Ensure slug uniqueness for this content type
            if ($this->slugExists($slug)) {
                throw new \RuntimeException("Slug already exists for this content type");
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
        } catch (\Exception $e) {
            try {
                $this->db->rollback();
            } catch (\Exception $rollbackEx) {
                // Log rollback failure but don't mask original error
                error_log("Rollback failed: " . $rollbackEx->getMessage());
            }
            throw new \RuntimeException("Failed to create content: {$e->getMessage()}");
        }
    }

    /**
     * Update existing content
     */
    public function update(int $id, array $data): bool
    {
        $this->db->beginTransaction();

        try {
            // Get current content before updating (for revision snapshot)
            $currentContent = $this->db->table('content')
                ->where('id', $id)
                ->where('type', $this->type)
                ->first();

            if (!$currentContent) {
                throw new \RuntimeException("Content not found");
            }

            // Build update data for core fields
            $updateData = [];

            if (isset($data['slug'])) {
                // Slug is required and cannot be empty
                if (empty($data['slug'])) {
                    throw new \RuntimeException("Slug is required and cannot be empty");
                }
                // Validate slug format
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['slug'])) {
                    throw new \RuntimeException("Slug must contain only lowercase letters, numbers, and hyphens (no spaces or special characters)");
                }

                // Check slug uniqueness (excluding current item)
                $count = $this->db->table('content')
                    ->where('type', $this->type)
                    ->where('slug', $data['slug'])
                    ->where('id', '!=', $id)
                    ->count();

                if ($count > 0) {
                    throw new \RuntimeException("Slug already exists for this content type");
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

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            try {
                $this->db->rollback();
            } catch (\Exception $rollbackEx) {
                // Log rollback failure but don't mask original error
                error_log("Rollback failed: " . $rollbackEx->getMessage());
            }
            throw new \RuntimeException("Failed to update content: {$e->getMessage()}");
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
        } catch (\Exception $e) {
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
        $result = $this->populateRelationships($result);

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
        $query->orderBy($orderBy, $orderDir);

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
                $item = $this->populateRelationships($item);
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
     */
    private function saveMeta(int $contentId, array $fields): void
    {
        foreach ($fields as $key => $value) {
            $dbValue = $value;

            // If field has a Field class instance, use it for validation/sanitization
            if (isset($this->fieldInstances[$key]) && isset($this->config['fields'][$key])) {
                $fieldInstance = $this->fieldInstances[$key];
                $fieldConfig = $this->config['fields'][$key];

                // Validate
                if (!$fieldInstance->validate($value, $fieldConfig)) {
                    error_log("Validation failed for field '{$key}': " . json_encode([
                        'value' => $value,
                        'type' => gettype($value),
                        'config' => $fieldConfig
                    ]));
                    throw new \RuntimeException("Validation failed for field '{$key}'");
                }

                // Sanitize
                $sanitized = $fieldInstance->sanitize($value, $fieldConfig);

                // Convert to database format
                $dbValue = $fieldInstance->toDatabase($sanitized);
            } else {
                // For fields without Field classes, just JSON encode arrays/objects
                if (is_array($value) || is_object($value)) {
                    $dbValue = json_encode($value);
                }
            }

            $this->db->table('content_meta')->insert([
                'content_id' => $contentId,
                'meta_key' => $key,
                'meta_value' => $dbValue
            ]);
        }
    }

    /**
     * Retrieve meta fields from database
     */
    private function getMeta(int $contentId): array
    {
        $meta = $this->db->table('content_meta')
            ->select(['meta_key', 'meta_value'])
            ->where('content_id', $contentId)
            ->get();

        $fields = [];

        foreach ($meta as $row) {
            $key = $row['meta_key'];
            $value = $row['meta_value'];

            if (isset($this->fieldInstances[$key])) {
                $fieldInstance = $this->fieldInstances[$key];
                $fields[$key] = $fieldInstance->fromDatabase($value);
            } else {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    /**
     * Retrieve meta fields for multiple content IDs in a single query (batch loading)
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
            $key = $row['meta_key'];
            $value = $row['meta_value'];

            if (!isset($result[$contentId])) {
                $result[$contentId] = [];
            }

            if (isset($this->fieldInstances[$key])) {
                $fieldInstance = $this->fieldInstances[$key];
                $result[$contentId][$key] = $fieldInstance->fromDatabase($value);
            } else {
                $result[$contentId][$key] = $value;
            }
        }

        // Ensure all content IDs have an entry (even if no meta)
        foreach ($contentIds as $id) {
            if (!isset($result[$id])) {
                $result[$id] = [];
            }
        }

        return $result;
    }

    /**
     * Populate relationship fields with actual data
     */
    private function populateRelationships(array $content): array
    {
        if (!isset($content['fields']) || !isset($this->config['fields'])) {
            return $content;
        }

        try {
            foreach ($this->config['fields'] as $fieldKey => $fieldConfig) {
                if (!isset($fieldConfig['type'])) {
                    continue;
                }

                $fieldType = $fieldConfig['type'];

                // Handle media fields
                if ($fieldType === 'media' && isset($content['fields'][$fieldKey])) {
                    try {
                        $content['fields'][$fieldKey] = $this->populateMediaField($content['fields'][$fieldKey]);
                    } catch (\Exception $e) {
                        error_log("Failed to populate media field {$fieldKey}: " . $e->getMessage());
                    }
                }

                // Handle relationship fields
                if ($fieldType === 'relationship' && isset($content['fields'][$fieldKey])) {
                    try {
                        $relatedId = $content['fields'][$fieldKey];
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
                                $content['fields'][$fieldKey] = $related;
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("Failed to populate relationship field {$fieldKey}: " . $e->getMessage());
                    }
                }

                // Handle repeater fields - recursively populate nested media fields
                if ($fieldType === 'repeater' && isset($content['fields'][$fieldKey])) {
                    try {
                        $repeaterItems = $content['fields'][$fieldKey];
                        if (is_array($repeaterItems)) {
                            foreach ($repeaterItems as $index => $item) {
                                if (is_array($item) && isset($fieldConfig['config']['fields'])) {
                                    foreach ($fieldConfig['config']['fields'] as $subField) {
                                        if ($subField['type'] === 'media' && isset($item[$subField['key']])) {
                                            $content['fields'][$fieldKey][$index][$subField['key']] =
                                                $this->populateMediaField($item[$subField['key']]);
                                        }
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("Failed to populate repeater field {$fieldKey}: " . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error in populateRelationships: " . $e->getMessage());
        }

        return $content;
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
        if (isset($filters['field_filters']) && !empty($filters['field_filters'])) {
            foreach ($filters['field_filters'] as $index => $filter) {
                $alias = "m{$index}";

                // Build subquery for EXISTS clause
                if ($filter['operator'] === 'LIKE') {
                    $subquery = "SELECT 1 FROM content_meta {$alias} " .
                               "WHERE {$alias}.content_id = content.id " .
                               "AND {$alias}.meta_key = ? " .
                               "AND {$alias}.meta_value LIKE ?";
                    $bindings = [$filter['key'], '%' . $filter['value'] . '%'];
                } else {
                    $subquery = "SELECT 1 FROM content_meta {$alias} " .
                               "WHERE {$alias}.content_id = content.id " .
                               "AND {$alias}.meta_key = ? " .
                               "AND {$alias}.meta_value {$filter['operator']} ?";
                    $bindings = [$filter['key'], $filter['value']];
                }

                $query->whereExists($subquery, $bindings);
            }
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
            'fields' => json_encode($currentFields),
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
                "SELECT id FROM content_revisions WHERE content_id = ? ORDER BY created_at ASC LIMIT ?",
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
             WHERE r.content_id = ?
             ORDER BY r.revision_number DESC",
            [$contentId]
        );

        // Decode fields JSON for each revision
        foreach ($revisions as &$revision) {
            $revision['fields'] = json_decode($revision['fields'], true) ?? [];
        }

        return $revisions;
    }

    /**
     * Restore a revision - creates a new revision with old data
     */
    public function restoreRevision(int $contentId, int $revisionId): bool
    {
        $this->db->beginTransaction();

        try {
            // Get the revision to restore
            $revision = $this->db->table('content_revisions')
                ->where('id', $revisionId)
                ->where('content_id', $contentId)
                ->first();

            if (!$revision) {
                throw new \RuntimeException("Revision not found");
            }

            // Decode fields
            $fields = json_decode($revision['fields'], true) ?? [];

            // Update content with revision data
            $this->update($contentId, [
                'slug' => $revision['slug'],
                'status' => $revision['status'],
                'fields' => $fields
            ]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            try {
                $this->db->rollback();
            } catch (\Exception $rollbackEx) {
                error_log("Rollback failed: " . $rollbackEx->getMessage());
            }
            throw new \RuntimeException("Failed to restore revision: {$e->getMessage()}");
        }
    }
}
