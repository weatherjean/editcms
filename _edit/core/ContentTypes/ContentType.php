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

            // Slug is required
            if (empty($slug)) {
                throw new \RuntimeException("Slug is required");
            }

            // Ensure slug uniqueness for this content type
            if ($this->slugExists($slug)) {
                throw new \RuntimeException("Slug already exists for this content type");
            }

            // Insert into content table
            $this->db->execute(
                "INSERT INTO content (type, slug, status, author_id) VALUES (?, ?, ?, ?)",
                [$this->type, $slug, $status, $authorId]
            );

            $contentId = $this->db->lastInsertId();

            // Save meta fields
            if (isset($data['fields'])) {
                $this->saveMeta($contentId, $data['fields']);
            }

            $this->db->commit();
            return $contentId;
        } catch (\Exception $e) {
            $this->db->rollback();
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
            // Build update query for core fields
            $updates = [];
            $params = [];

            if (isset($data['slug'])) {
                // Check slug uniqueness (excluding current item)
                $existing = $this->db->query(
                    "SELECT id FROM content WHERE type = ? AND slug = ? AND id != ?",
                    [$this->type, $data['slug'], $id]
                );
                if (!empty($existing)) {
                    throw new \RuntimeException("Slug already exists for this content type");
                }
                $updates[] = 'slug = ?';
                $params[] = $data['slug'];
            }

            if (isset($data['status'])) {
                $updates[] = 'status = ?';
                $params[] = $data['status'];
            }

            if (isset($data['author_id'])) {
                $updates[] = 'author_id = ?';
                $params[] = $data['author_id'];
            }

            $updates[] = 'updated_at = CURRENT_TIMESTAMP';
            $params[] = $id;

            // Update content table
            if (!empty($updates)) {
                $sql = "UPDATE content SET " . implode(', ', $updates) . " WHERE id = ?";
                $this->db->execute($sql, $params);
            }

            // Update meta fields
            if (isset($data['fields'])) {
                // Delete existing meta
                $this->db->execute("DELETE FROM content_meta WHERE content_id = ?", [$id]);
                // Insert new meta
                $this->saveMeta($id, $data['fields']);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
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
            $this->db->execute("DELETE FROM content WHERE id = ? AND type = ?", [$id, $this->type]);
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
        $content = $this->db->query(
            "SELECT * FROM content WHERE id = ? AND type = ?",
            [$id, $this->type]
        );

        if (empty($content)) {
            return null;
        }

        $result = $content[0];

        // Get meta fields
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
        $sql = "SELECT * FROM content WHERE type = ?";
        $params = [$this->type];

        // Apply filters
        if (isset($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['author_id'])) {
            $sql .= " AND author_id = ?";
            $params[] = $filters['author_id'];
        }

        // Ordering
        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDir = $filters['order_dir'] ?? 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        // Pagination
        if (isset($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int) $filters['limit'];

            if (isset($filters['offset'])) {
                $sql .= " OFFSET ?";
                $params[] = (int) $filters['offset'];
            }
        }

        $results = $this->db->query($sql, $params);

        // Populate meta and relationships for each item
        foreach ($results as &$item) {
            $item['fields'] = $this->getMeta((int) $item['id']);
            $item = $this->populateRelationships($item);
        }

        return $results;
    }

    /**
     * Save meta fields to database
     */
    private function saveMeta(int $contentId, array $fields): void
    {
        foreach ($fields as $key => $value) {
            if (!isset($this->fieldInstances[$key])) {
                continue;
            }

            $fieldInstance = $this->fieldInstances[$key];
            $fieldConfig = $this->config['fields'][$key];

            // Validate
            if (!$fieldInstance->validate($value, $fieldConfig)) {
                throw new \RuntimeException("Validation failed for field '{$key}'");
            }

            // Sanitize
            $sanitized = $fieldInstance->sanitize($value, $fieldConfig);

            // Convert to database format
            $dbValue = $fieldInstance->toDatabase($sanitized);

            // Insert meta
            $this->db->execute(
                "INSERT INTO content_meta (content_id, meta_key, meta_value) VALUES (?, ?, ?)",
                [$contentId, $key, $dbValue]
            );
        }
    }

    /**
     * Retrieve meta fields from database
     */
    private function getMeta(int $contentId): array
    {
        $meta = $this->db->query(
            "SELECT meta_key, meta_value FROM content_meta WHERE content_id = ?",
            [$contentId]
        );

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
     * Populate relationship fields with actual data
     */
    private function populateRelationships(array $content): array
    {
        if (!isset($content['fields'])) {
            return $content;
        }

        foreach ($this->config['fields'] as $fieldKey => $fieldConfig) {
            $fieldType = $fieldConfig['type'];

            // Handle media fields
            if ($fieldType === 'media' && isset($content['fields'][$fieldKey])) {
                $mediaId = $content['fields'][$fieldKey];
                if ($mediaId) {
                    $media = $this->db->query("SELECT * FROM media WHERE id = ?", [$mediaId]);
                    if (!empty($media)) {
                        $mediaData = $media[0];
                        $mediaData['url'] = '/_edit/uploads/' . $mediaData['path'];
                        $content['fields'][$fieldKey] = $mediaData;
                    }
                }
            }

            // Handle relationship fields
            if ($fieldType === 'relationship' && isset($content['fields'][$fieldKey])) {
                $relatedId = $content['fields'][$fieldKey];
                $target = $fieldConfig['target'] ?? 'content';

                if ($relatedId) {
                    if ($target === 'user') {
                        $related = $this->db->query("SELECT id, name, email FROM users WHERE id = ?", [$relatedId]);
                    } else {
                        $related = $this->db->query("SELECT id, type, slug FROM content WHERE id = ?", [$relatedId]);
                    }

                    if (!empty($related)) {
                        $content['fields'][$fieldKey] = $related[0];
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Check if slug already exists for this content type
     */
    private function slugExists(string $slug): bool
    {
        $result = $this->db->query(
            "SELECT COUNT(*) as count FROM content WHERE type = ? AND slug = ?",
            [$this->type, $slug]
        );

        return $result[0]['count'] > 0;
    }
}
