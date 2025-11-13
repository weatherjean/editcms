**Version:** 1.0
**Base URL:** `/_edit/api/public`
**Authentication:** None required

This API provides read-only access to published content for frontend applications. All queries automatically filter to `status=published` content only.

---

## Table of Contents

- [Quick Start](#quick-start)
- [List Content](#list-content)
- [Get Single Item](#get-single-item)
- [Query Parameters](#query-parameters)
- [Filtering](#filtering)
- [Relationships](#relationships)
- [Response Format](#response-format)
- [Error Handling](#error-handling)
- [Rate Limits](#rate-limits)
- [Examples](#examples)

---

## Quick Start

### List Latest Posts

```bash
curl https://yoursite.com/_edit/api/public/post?limit=5
```

```javascript
const response = await fetch('/_edit/api/public/post?limit=5');
const { data, meta } = await response.json();

data.forEach(post => {
  console.log(post.fields.title);
});
```

### Get Single Post by Slug

```bash
curl https://yoursite.com/_edit/api/public/post/my-first-post
```

```javascript
const response = await fetch('/_edit/api/public/post/my-first-post');
const post = await response.json();

console.log(post.fields.title);
```

---

## List Content

**Endpoint:** `GET /_edit/api/public/{content-type}`

Returns a paginated list of published content items.

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | integer | 10 | Number of items to return (max: 100) |
| `offset` | integer | 0 | Number of items to skip |
| `order_by` | string | `created_at` | Field to sort by |
| `order_dir` | string | `DESC` | Sort direction (`ASC` or `DESC`) |
| `fields_only` | string | - | Comma-separated list of field keys to return |
| `field_groups` | string | - | Comma-separated list of field group keys to include |
| `exclude_open_fields` | boolean | false | If true, only return fields defined in field groups |
| `populate` | string | - | Comma-separated list of relationship fields to populate |

### Example Request

```http
GET /_edit/api/public/post?limit=10&offset=0&order_by=created_at&order_dir=DESC
```

### Example Response

```json
{
  "data": [
    {
      "id": 123,
      "type": "post",
      "slug": "my-first-post",
      "status": "published",
      "created_at": "2025-11-01 10:00:00",
      "updated_at": "2025-11-02 15:30:00",
      "fields": {
        "title": "My First Post",
        "excerpt": "This is a short summary of my post...",
        "content": "<p>Full post content here...</p>",
        "featured_image": {
          "id": 456,
          "filename": "hero.jpg",
          "path": "2025/11/abc123.jpg",
          "url": "/_edit/uploads/2025/11/abc123.jpg",
          "mime_type": "image/jpeg",
          "size": 245678,
          "alt_text": "Hero image"
        },
        "published_at": "2025-11-01 10:00:00"
      }
    }
  ],
  "meta": {
    "total": 42,
    "limit": 10,
    "offset": 0,
    "has_more": true
  }
}
```

---

## Get Single Item

**Endpoint:** `GET /_edit/api/public/{content-type}/{slug}`

Returns a single content item by its slug.

### Example Request

```http
GET /_edit/api/public/post/my-first-post
```

### Example Response

```json
{
  "id": 123,
  "type": "post",
  "slug": "my-first-post",
  "status": "published",
  "created_at": "2025-11-01 10:00:00",
  "updated_at": "2025-11-02 15:30:00",
  "fields": {
    "title": "My First Post",
    "excerpt": "This is a short summary...",
    "content": "<p>Full post content...</p>",
    "featured_image": {
      "id": 456,
      "url": "/_edit/uploads/2025/11/abc123.jpg",
      "alt_text": "Hero image"
    }
  }
}
```

---

## Query Parameters

### Pagination

Use `limit` and `offset` for pagination:

```javascript
// Page 1 (items 0-9)
fetch('/_edit/api/public/post?limit=10&offset=0')

// Page 2 (items 10-19)
fetch('/_edit/api/public/post?limit=10&offset=10')

// Page 3 (items 20-29)
fetch('/_edit/api/public/post?limit=10&offset=20')
```

**Pagination Helper:**

```javascript
function getPage(page, perPage = 10) {
  const offset = (page - 1) * perPage;
  return fetch(`/_edit/api/public/post?limit=${perPage}&offset=${offset}`)
    .then(r => r.json());
}

const { data, meta } = await getPage(3, 10);
console.log(`Page 3: ${data.length} items`);
console.log(`Total: ${meta.total} items`);
console.log(`Has next page: ${meta.has_more}`);
```

### Sorting

Sort by any core field or custom field:

```bash
# Newest first (default)
/_edit/api/public/post?order_by=created_at&order_dir=DESC

# Oldest first
/_edit/api/public/post?order_by=created_at&order_dir=ASC

# Alphabetical by title
/_edit/api/public/post?order_by=title&order_dir=ASC

# By custom field (e.g., publish date)
/_edit/api/public/post?order_by=published_at&order_dir=DESC
```

**Note:** When sorting by custom fields (in `fields.*`), the API will automatically handle the lookup.

### Field Selection

Return only specific fields to reduce payload size:

```bash
# Only title and excerpt
/_edit/api/public/post?fields_only=title,excerpt

# Only featured image
/_edit/api/public/post?fields_only=featured_image
```

**Response with `fields_only`:**

```json
{
  "data": [
    {
      "id": 123,
      "slug": "my-post",
      "fields": {
        "title": "My Post",
        "excerpt": "Summary..."
      }
    }
  ]
}
```

### Field Groups

Content types can have multiple field groups attached. Use `field_groups` to only return fields from specific groups:

```bash
# Only return fields from the "seo" field group
/_edit/api/public/post?field_groups=seo

# Return fields from multiple groups
/_edit/api/public/post?field_groups=seo,social_meta
```

**Example:** If a post has field groups "post_content", "seo", and "social_meta", but you only need SEO data:

```bash
GET /_edit/api/public/post/my-post?field_groups=seo
```

**Response:**

```json
{
  "id": 123,
  "slug": "my-post",
  "fields": {
    "meta_title": "My SEO Optimized Title",
    "meta_description": "SEO description here...",
    "canonical_url": "https://example.com/my-post"
  }
}
```

### Exclude Open Fields

By default, the API returns all fields stored in the database, even if they're not defined in any field group. Use `exclude_open_fields=true` to only return fields from defined field groups:

```bash
# Only return fields that are in field group schemas
/_edit/api/public/post?exclude_open_fields=true
```

This is useful for ensuring strict schema compliance and avoiding legacy/orphaned fields.

**Combine with field_groups:**

```bash
# Only SEO fields, and strictly from the schema
/_edit/api/public/post?field_groups=seo&exclude_open_fields=true
```

---

## Filtering

Filter content by custom field values using the `fields[key]` parameter syntax.

### Exact Match

```bash
# Posts with category = "technology"
/_edit/api/public/post?fields[category]=technology

# Posts marked as featured
/_edit/api/public/post?fields[featured]=true
```

### Comparison Operators

Add operator suffix to field key:

| Operator | Description | Example |
|----------|-------------|---------|
| `_gte` | Greater than or equal | `fields[views_gte]=100` |
| `_lte` | Less than or equal | `fields[views_lte]=1000` |
| `_like` | Contains (case-insensitive) | `fields[title_like]=guide` |
| `_not` | Not equal to | `fields[category_not]=draft` |

### Filter Examples

**Upcoming events:**

```bash
/_edit/api/public/event?fields[event_date_gte]=2025-11-13&order_by=event_date&order_dir=ASC
```

**Popular posts (100+ views):**

```bash
/_edit/api/public/post?fields[views_gte]=100&order_by=views&order_dir=DESC
```

**Search posts by title:**

```bash
/_edit/api/public/post?fields[title_like]=javascript
```

**Multiple filters (AND logic):**

```bash
/_edit/api/public/post?fields[category]=tutorial&fields[featured]=true&limit=5
```

### JavaScript Example

```javascript
async function getUpcomingEvents() {
  const today = new Date().toISOString().split('T')[0];
  const params = new URLSearchParams({
    'fields[event_date_gte]': today,
    'order_by': 'event_date',
    'order_dir': 'ASC',
    'limit': 10
  });

  const response = await fetch(`/_edit/api/public/event?${params}`);
  return response.json();
}
```

---

## Relationships

Relationship fields (like `related_posts`, `category`) return only IDs by default. Use the `populate` parameter to fetch full related data.

### Default Behavior (IDs Only)

```json
{
  "fields": {
    "category": 5,
    "related_posts": [12, 15, 18]
  }
}
```

### With Population

**Request:**

```bash
/_edit/api/public/post/my-post?populate=category,related_posts
```

**Response:**

```json
{
  "fields": {
    "category": {
      "id": 5,
      "type": "category",
      "slug": "tutorials",
      "fields": {
        "name": "Tutorials",
        "description": "How-to guides and tutorials"
      }
    },
    "related_posts": [
      {
        "id": 12,
        "type": "post",
        "slug": "related-post-1",
        "fields": {
          "title": "Related Post 1",
          "excerpt": "..."
        }
      },
      {
        "id": 15,
        "type": "post",
        "slug": "related-post-2",
        "fields": {
          "title": "Related Post 2",
          "excerpt": "..."
        }
      }
    ]
  }
}
```

### Populate on List Queries

```bash
# Populate category on all posts
/_edit/api/public/post?limit=10&populate=category
```

**Performance Note:** Populating relationships on large lists can be slow. Use sparingly and consider combining with `fields_only`.

```bash
# Efficient: Only get what you need
/_edit/api/public/post?limit=10&populate=category&fields_only=title,category
```

---

## Response Format

### List Response

```json
{
  "data": [ /* array of content items */ ],
  "meta": {
    "total": 42,        // Total number of published items
    "limit": 10,        // Items per page
    "offset": 0,        // Current offset
    "has_more": true    // Whether more items exist
  }
}
```

### Single Item Response

```json
{
  "id": 123,
  "type": "post",
  "slug": "my-post",
  "status": "published",
  "created_at": "2025-11-01 10:00:00",
  "updated_at": "2025-11-02 15:30:00",
  "fields": {
    /* custom fields */
  }
}
```

### Core Fields

All content items include these core fields:

- `id` - Unique identifier
- `type` - Content type key (e.g., "post", "page")
- `slug` - URL-friendly identifier
- `status` - Always "published" in public API
- `created_at` - ISO 8601 timestamp
- `updated_at` - ISO 8601 timestamp

**Note:** The `author_id` field is for internal use only and is never exposed in the public API.

### Custom Fields

All custom fields defined in your field groups are returned in the `fields` object.

---

## Error Handling

### Error Response Format

```json
{
  "error": "Error message here",
  "code": 404
}
```

### Common Errors

**404 - Content Type Not Found:**

```bash
GET /_edit/api/public/invalid_type
```

```json
{
  "error": "Content type 'invalid_type' not found",
  "code": 404
}
```

**404 - Slug Not Found:**

```bash
GET /_edit/api/public/post/nonexistent-slug
```

```json
{
  "error": "Content not found",
  "code": 404
}
```

**400 - Invalid Parameters:**

```bash
GET /_edit/api/public/post?limit=abc
```

```json
{
  "error": "Invalid parameter 'limit': must be an integer",
  "code": 400
}
```

**429 - Rate Limit Exceeded:**

```json
{
  "error": "Rate limit exceeded. Try again in 5 minutes.",
  "code": 429
}
```

---

## Rate Limits

The public API is rate-limited to prevent abuse:

- **100 requests per minute** per IP address
- Exceeded limits return `429 Too Many Requests`
- `Retry-After` header indicates when to retry (in seconds)

### Handling Rate Limits

```javascript
async function fetchWithRetry(url) {
  const response = await fetch(url);

  if (response.status === 429) {
    const retryAfter = response.headers.get('Retry-After') || 60;
    console.log(`Rate limited. Retrying in ${retryAfter} seconds...`);
    await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
    return fetchWithRetry(url);
  }

  return response.json();
}
```

---

## Examples

### Blog Homepage

```javascript
// Fetch 5 latest posts with featured images
async function getLatestPosts() {
  const params = new URLSearchParams({
    limit: 5,
    order_by: 'published_at',
    order_dir: 'DESC',
    fields_only: 'title,excerpt,featured_image,published_at'
  });

  const response = await fetch(`/_edit/api/public/post?${params}`);
  const { data } = await response.json();

  return data;
}
```

### Blog Post Page (Next.js)

```javascript
// pages/blog/[slug].js
export async function getStaticProps({ params }) {
  const response = await fetch(
    `${process.env.NEXT_PUBLIC_API_URL}/_edit/api/public/post/${params.slug}`
  );

  if (!response.ok) {
    return { notFound: true };
  }

  const post = await response.json();
  return {
    props: { post },
    revalidate: 60 // ISR: revalidate every 60 seconds
  };
}

export async function getStaticPaths() {
  const response = await fetch(
    `${process.env.NEXT_PUBLIC_API_URL}/_edit/api/public/post?limit=100`
  );
  const { data } = await response.json();

  const paths = data.map(post => ({
    params: { slug: post.slug }
  }));

  return { paths, fallback: 'blocking' };
}
```

### Events Calendar

```javascript
// Get upcoming events
async function getUpcomingEvents(limit = 10) {
  const today = new Date().toISOString().split('T')[0];

  const params = new URLSearchParams({
    'fields[event_date_gte]': today,
    'order_by': 'event_date',
    'order_dir': 'ASC',
    limit: limit
  });

  const response = await fetch(`/_edit/api/public/event?${params}`);
  const { data } = await response.json();

  return data;
}
```

### Search Functionality

```javascript
// Simple search by title
async function searchPosts(query, page = 1, perPage = 10) {
  const offset = (page - 1) * perPage;

  const params = new URLSearchParams({
    'fields[title_like]': query,
    limit: perPage,
    offset: offset,
    fields_only: 'title,excerpt,featured_image'
  });

  const response = await fetch(`/_edit/api/public/post?${params}`);
  return response.json();
}

// Usage
const results = await searchPosts('javascript', 1, 10);
console.log(`Found ${results.meta.total} posts`);
```

### Category Archive

```javascript
// Get posts by category with pagination
async function getPostsByCategory(category, page = 1) {
  const perPage = 12;
  const offset = (page - 1) * perPage;

  const params = new URLSearchParams({
    'fields[category]': category,
    limit: perPage,
    offset: offset,
    order_by: 'published_at',
    order_dir: 'DESC'
  });

  const response = await fetch(`/_edit/api/public/post?${params}`);
  const { data, meta } = await response.json();

  return {
    posts: data,
    currentPage: page,
    totalPages: Math.ceil(meta.total / perPage),
    hasMore: meta.has_more
  };
}
```

### Infinite Scroll

```javascript
class InfinitePostLoader {
  constructor(contentType, perPage = 10) {
    this.contentType = contentType;
    this.perPage = perPage;
    this.offset = 0;
    this.hasMore = true;
    this.loading = false;
  }

  async loadMore() {
    if (!this.hasMore || this.loading) return [];

    this.loading = true;

    const params = new URLSearchParams({
      limit: this.perPage,
      offset: this.offset
    });

    const response = await fetch(
      `/_edit/api/public/${this.contentType}?${params}`
    );
    const { data, meta } = await response.json();

    this.offset += this.perPage;
    this.hasMore = meta.has_more;
    this.loading = false;

    return data;
  }
}

// Usage
const loader = new InfinitePostLoader('post', 10);

window.addEventListener('scroll', async () => {
  if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) {
    const posts = await loader.loadMore();
    appendPostsToDOM(posts);
  }
});
```

---

## Best Practices

### 1. Use Field Selection

Only request fields you need to reduce payload size:

```javascript
// ❌ Bad: Fetch all fields
fetch('/_edit/api/public/post?limit=100')

// ✅ Good: Only fetch what you need
fetch('/_edit/api/public/post?limit=100&fields_only=title,excerpt')
```

### 2. Populate Relationships Sparingly

Only populate when you need the related data:

```javascript
// ❌ Bad: Always populate everything
fetch('/_edit/api/public/post?populate=related_posts,category,tags')

// ✅ Good: Only populate when needed
fetch('/_edit/api/public/post/my-post?populate=related_posts')
```

### 3. Cache Responses

The API returns cache headers - use them:

```javascript
// Browser automatically caches based on Cache-Control headers
const response = await fetch('/_edit/api/public/post');

// Or implement your own caching
const cache = new Map();

async function getCachedPosts() {
  const cacheKey = 'posts-homepage';

  if (cache.has(cacheKey)) {
    return cache.get(cacheKey);
  }

  const response = await fetch('/_edit/api/public/post?limit=5');
  const data = await response.json();

  cache.set(cacheKey, data);
  setTimeout(() => cache.delete(cacheKey), 5 * 60 * 1000); // 5 min TTL

  return data;
}
```

### 4. Handle Errors Gracefully

```javascript
async function fetchPost(slug) {
  try {
    const response = await fetch(`/_edit/api/public/post/${slug}`);

    if (response.status === 404) {
      // Show 404 page
      return null;
    }

    if (response.status === 429) {
      // Rate limited - show friendly message
      throw new Error('Too many requests. Please try again later.');
    }

    if (!response.ok) {
      throw new Error('Failed to fetch post');
    }

    return await response.json();
  } catch (error) {
    console.error('Error fetching post:', error);
    // Show error UI
    return null;
  }
}
```

### 5. Build URLs Safely

Always use `URLSearchParams` to avoid encoding issues:

```javascript
// ✅ Good
const params = new URLSearchParams({
  'fields[title_like]': 'Hello & Welcome',
  limit: 10
});
fetch(`/_edit/api/public/post?${params}`);

// ❌ Bad (breaks with special characters)
fetch(`/_edit/api/public/post?fields[title_like]=Hello & Welcome&limit=10`);
```

---

## Support

For issues, questions, or feature requests, please refer to the _edit CMS documentation or contact your system administrator.

**API Version:** 1.0
**Last Updated:** 2025-11-13
