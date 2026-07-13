<?php

namespace Edit\Core\ContentTypes;

/**
 * BlockRegistry - Loads and manages content blocks
 *
 * Blocks are reusable content building blocks for flexible page layouts.
 * They are stored as individual JSON files in _edit/config/blocks/
 */
class BlockRegistry
{
    private string $blocksPath;
    private array $blocks = [];
    private bool $loaded = false;

    public function __construct(string $configPath)
    {
        $this->blocksPath = \Edit\Core\Configuration\Store::resolve($configPath) . '/blocks';
    }

    /**
     * Load all block definitions from the blocks directory
     */
    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->blocks = [];

        if (!is_dir($this->blocksPath)) {
            $this->loaded = true;
            return;
        }

        $files = glob($this->blocksPath . '/*.json');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $block = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Failed to parse block file: {$file} - " . json_last_error_msg());
                continue;
            }

            if (!$this->validateBlock($block)) {
                error_log("Invalid block structure in file: {$file}");
                continue;
            }

            $this->blocks[$block['key']] = $block;
        }

        $this->loaded = true;
    }

    /**
     * Get all registered blocks
     */
    public function getBlocks(): array
    {
        if (!$this->loaded) {
            $this->load();
        }

        return array_values($this->blocks);
    }

    /**
     * Get a specific block by key
     */
    public function getBlock(string $key): ?array
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->blocks[$key] ?? null;
    }

    /**
     * Check if a block exists
     */
    public function hasBlock(string $key): bool
    {
        if (!$this->loaded) {
            $this->load();
        }

        return isset($this->blocks[$key]);
    }

    /**
     * Validate block structure
     */
    private function validateBlock(array $block): bool
    {
        $required = ['key', 'label', 'fields'];

        foreach ($required as $field) {
            if (!isset($block[$field])) {
                return false;
            }
        }

        if (!is_array($block['fields']) || empty($block['fields'])) {
            return false;
        }

        return true;
    }

    /**
     * Reload blocks from disk (useful after adding/updating blocks)
     */
    public function reload(): void
    {
        $this->loaded = false;
        $this->load();
    }
}
