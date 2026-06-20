# Nested Relation Synchronization

Complete guide to automatic relation syncing during Crud store/update operations.

---

## Architecture

```
AbstractStoreService::store() / AbstractUpdateService::update()
        │
        ▼ (AFTER the transaction commits, AFTER files are handled)
RelationSyncManagerInterface::syncAll($model, $data, depth: 0)
        │
        ├─ CapabilityRegistry::supportsHasMany($model)?
        │       └─ for each name in $model->getHasManyRelations():
        │               if present in $data → HasManySyncStrategy::sync()
        │
        ├─ CapabilityRegistry::supportsHasOne($model)?
        │       └─ for each name in $model->getHasOneRelations():
        │               if present in $data → HasOneSyncStrategy::sync()
        │
        └─ CapabilityRegistry::supportsManyToMany($model)?
                └─ for each name in $model->getManyToManyRelations():
                        if present in $data → ManyToManySyncStrategy::sync()

Each strategy dispatches `RelationSynced` after a successful sync,
and may recurse into the child model's own relations (depth + 1)
via RelationSyncManagerInterface::syncAll() again.
```

---

## HasMany Relations

### Setup

```php
use Nexus\CrudEngine\Contracts\Capabilities\HasManyRelations;

class Post extends Model implements HasManyRelations
{
    public function getHasManyRelations(): array
    {
        return ['comments'];
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
```

### Request Payload

```json
{
  "title": "My Post",
  "comments": [
    { "id": 5, "body": "Updated existing comment" },
    { "body": "Brand new comment, no id" }
  ]
}
```

### Sync Behavior (Diff-Based)

| Incoming row | Action |
|---|---|
| Has `id` matching an existing related row | **Update** that row |
| Has no `id`, or `id` not found among existing rows | **Create** a new row |
| Existing related row's `id` absent from the payload entirely | **Delete** that row |

```php
// Before: comments = [ {id: 5, body: 'Old'}, {id: 6, body: 'Will be removed'} ]
// Payload: comments = [ {id: 5, body: 'Updated'}, {body: 'New comment'} ]
// After:  comments = [ {id: 5, body: 'Updated'}, {id: 7, body: 'New comment'} ]
//         (id: 6 was deleted because it was absent from the payload)
```

### Sending an Empty Array

```json
{ "comments": [] }
```

This **deletes all existing related rows** — the diff algorithm has zero incoming IDs, so every existing row is treated as an orphan.

### Performance

Existing related rows are bulk-loaded once via `whereIn`, not one `find()` call per incoming row — this fixes the N+1 query pattern present in the original `HandleRelationHasMany`.

---

## HasOne Relations

### Setup

```php
use Nexus\CrudEngine\Contracts\Capabilities\HasOneRelations;

class Post extends Model implements HasOneRelations
{
    public function getHasOneRelations(): array
    {
        return ['meta'];
    }

    public function meta(): HasOne
    {
        return $this->hasOne(PostMeta::class);
    }
}
```

### Request Payload

```json
{
  "title": "My Post",
  "meta": {
    "seo_title": "My Post | My Blog",
    "seo_description": "A great post about things."
  }
}
```

### Sync Behavior (Update-or-Create)

- If a related row already exists → `fill()` + `save()`
- If no related row exists → `create()`
- Sending `{}` or omitting the key entirely → no-op (the relation is untouched)

Note: unlike `HasMany`, you cannot delete a `HasOne` relation by sending an empty payload — `HasOneSyncStrategy::sync()` returns early if the incoming data normalizes to an empty array.

---

## Many-to-Many Relations

### Setup

```php
use Nexus\CrudEngine\Contracts\Capabilities\ManyToManyRelations;

class Post extends Model implements ManyToManyRelations
{
    public function getManyToManyRelations(): array
    {
        return ['tags'];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
```

### Request Payload — Array of IDs, Not Row Objects

```json
{
  "title": "My Post",
  "tags": [1, 3, 7]
}
```

### Sync Behavior

Directly calls `$model->tags()->sync([1, 3, 7])`. Existing tag associations not in the list are detached; new ones are attached; unchanged ones are left alone — standard Eloquent `sync()` semantics.

### Detaching All Tags

```json
{ "tags": [] }
```

---

## Nested File Uploads

A relation's child model can also implement `FileUpload` — both `HasManySyncStrategy` and `HasOneSyncStrategy` automatically apply file handling on every child row.

```php
class Comment extends Model implements FileUpload
{
    public function documentFullPathStore(): string
    {
        return 'comments/'.$this->getKey();
    }

    public function requestKeysForFile(): array
    {
        return ['attachment'];
    }
}
```

```json
{
  "comments": [
    { "body": "See attached", "attachment": "<UploadedFile instance>" }
  ]
}
```

The `attachment` field on the new `Comment` row is stored automatically, in the same pass as the relation sync — no extra code needed on your part.

---

## Deeply Nested Relations (Multi-Level Recursion)

A child model synced via `HasMany` or `HasOne` can itself declare further relations — the manager recurses automatically.

```php
class Post extends Model implements HasOneRelations { /* meta relation */ }
class PostMeta extends Model implements HasOneRelations { /* settings relation */ }
class PostMetaSettings extends Model { /* leaf, no further relations */ }
```

```json
{
  "title": "My Post",
  "meta": {
    "seo_title": "SEO Title",
    "settings": { "theme": "dark" }
  }
}
```

Recursion depth is tracked via `RelationSyncContext::$depth` and capped by `crud-engine.relations.max_recursion_depth` (default `5`). Exceeding the cap throws `RelationSyncException::maxRecursionDepthExceeded()`.

> **This is the exact scenario the original codebase's Bug 4.3 broke.** The original `HandleRelationHasOne` recursed by calling `getHasManyRelations()` regardless of what the child model actually implemented — a model that, like `PostMeta` above, implemented only `HasOneRelations` would throw a fatal "call to undefined method" error. In this package, recursion is delegated entirely to `RelationSyncManager::syncAll()`, which asks `CapabilityRegistry` what the child model actually supports, so this scenario now works correctly. See the regression test `HasOneSyncStrategyTest::test_syncing_a_has_one_relation_recurses_correctly_into_a_model_that_only_implements_has_one_relations` for the exact assertion.

---

## Combining All Three Relation Types on One Model

```php
class Post extends Model implements
    HasManyRelations,
    HasOneRelations,
    ManyToManyRelations
{
    public function getHasManyRelations(): array   { return ['comments']; }
    public function getHasOneRelations(): array    { return ['meta']; }
    public function getManyToManyRelations(): array { return ['tags']; }

    public function comments(): HasMany       { return $this->hasMany(Comment::class); }
    public function meta(): HasOne            { return $this->hasOne(PostMeta::class); }
    public function tags(): BelongsToMany     { return $this->belongsToMany(Tag::class); }
}
```

```json
{
  "title": "My Post",
  "comments": [{ "body": "First!" }],
  "meta": { "seo_title": "SEO" },
  "tags": [1, 2]
}
```

A single `store()`/`update()` call syncs all three relation types in one pass — `RelationSyncManager::syncAll()` checks all three capability types unconditionally and dispatches each one independently.

---

## Strict Capability Mode

By default (`crud-engine.strict_capabilities = false`), a declared relation absent from the payload is silently skipped. Enable strict mode to catch payload/contract drift early:

```php
// config/crud-engine.php
'strict_capabilities' => true,
```

With strict mode on, omitting `comments` entirely from the payload for a model that declares `getHasManyRelations() === ['comments']` throws `UnsupportedCapabilityException`. Recommended for development/staging environments; use with care in production since it changes a previously-silent no-op into a hard failure.

---

## Common Mistakes

**Sending row objects for a many-to-many relation.**

`tags: [{"id": 1}, {"id": 2}]` does not work — `ManyToManySyncStrategy` expects a flat array of IDs: `tags: [1, 2]`.

**Sending an `id` inside a HasOne payload expecting it to control which record is updated.**

`HasOneSyncStrategy` strips `id` from the incoming row before applying it (`unset($row['id'])`) — the relevant related record is always resolved via `$relation->first()`, not via the submitted `id`. There is exactly one related record per parent for a `hasOne` relation; the `id` field, if sent, is ignored.

**Forgetting that an empty array deletes all HasMany rows.**

If your frontend always sends `comments: []` when the comments section is untouched (rather than omitting the key), every comment will be deleted on every update. Omit the key entirely, or send the full current list, if you don't intend to modify the relation.

**Expecting relation sync to run inside the same DB transaction as the parent create/update.**

It does not — relation syncing happens after the transaction commits, consistent with the file-handling timing fix (Bug 4.6). If the relation sync itself fails partway through, the parent record and any already-processed relations are *not* rolled back automatically.
