# Nexus CRUD Specification

**Status:** Stable reference specification, v1.0
**Derived from:** A production CRUD/file/relation/statistics engine implementation, abstracted to remove all framework- and language-specific detail.
**Purpose:** This document defines the behavioral contract that any conforming implementation — in any language, on any framework — must satisfy. It is the source specification for ports to Laravel (PHP), NestJS (TypeScript), ASP.NET Core (C#), Spring Boot (Java), and Go.

This specification describes **what must happen and in what order**, not **how to wire it up** in any particular language. No section below assumes a specific ORM, web framework, dependency-injection mechanism, or runtime.

---

## Conformance Language

This document uses **MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**, and **MAY** in the normative sense: MUST/MUST NOT are hard requirements for conformance; SHOULD/SHOULD NOT are strong recommendations that may be deviated from with justification; MAY indicates optional, implementation-defined behavior.

## Type Notation

Because this specification targets multiple type systems, types are written generically:

| Notation | Meaning |
|---|---|
| `string`, `int`, `bool`, `float` | Primitive scalar types |
| `T[]` | An ordered list of `T` |
| `T?` | An optional/nullable `T` |
| `Map<K, V>` | A key-value structure |
| `enum { A, B, C }` | A closed set of named values |
| `Entity` | Any persisted domain object (a "row", "document", "record" — terminology varies by storage paradigm) |
| `interface X { ... }` | A capability/behavioral contract an `Entity` or component may implement |

---

## Architecture Overview

The system is organized around a small number of **ports** — abstract boundaries that a conforming implementation wires to concrete infrastructure. Nothing in the core lifecycle logic below may depend on a specific implementation of any port; all interaction happens through the port's contract.

```
                        ┌─────────────────────────────┐
                        │   Capability Registry         │
                        │  (single source of truth for  │
                        │   "what can this entity do")  │
                        └──────────────┬────────────────┘
                                       │ queried by every component below
        ┌──────────────────────────────┼──────────────────────────────┐
        │                              │                              │
        ▼                              ▼                              ▼
┌───────────────┐           ┌───────────────────┐          ┌───────────────────┐
│ CRUD Lifecycle │  uses →   │ Validation Port     │          │ Persistence Port    │
│ (Create/Update/│           │ (rule resolution +  │          │ (create/update/     │
│  Delete/Bulk)  │           │  structured errors)│          │  delete/find)        │
└───────┬────────┘           └────────────────────┘          └─────────────────────┘
        │
        ├──────────────► File Lifecycle  ───uses──► Storage Port (write/delete/url)
        │                                            Naming Strategy (pluggable)
        │                                            Path Resolution (pluggable)
        │
        ├──────────────► Relation Synchronization ──uses──► Persistence Port (per child type)
        │                  (recurses via Capability Registry)
        │
        └──────────────► Event Lifecycle ───uses──► Event Dispatch Port
                                                       Observers (logging, cache
                                                       invalidation, notifications —
                                                       all external to the core)

┌────────────────────────────┐        ┌────────────────────────────────┐
│ Response Contract            │        │ Statistics Contract               │
│ (operation result envelope,  │        │ (query spec → sparse aggregate →  │
│  message resolution)         │        │  zero-filled, cached series)      │
└──────────────────────────────┘        └────────────────────────────────┘
```

Every section below corresponds to one box in this diagram. The **Capability Contracts** section, last in this document per the requested structure, formally defines the interfaces referenced throughout.

---

## CRUD Lifecycle

The CRUD lifecycle defines four operations: **Create**, **Update**, **Delete**, and **Bulk Delete** (a specialization of Delete). All four share a common shape: validate or resolve input → mutate persisted state inside a transactional boundary → perform non-transactional side effects (file I/O, relation synchronization) after the transaction concludes → emit a lifecycle event → return a structured operation result.

### Create

```
operation Create(rawInput) -> OperationResult:

    1. validatedData := Validate(rawInput, ruleSetFor(EntityType))
       // see Validation Lifecycle. MUST raise a structured validation
       // error and MUST NOT proceed past this step on failure.

    2. validatedData := BeforePersist(validatedData)
       // optional extension point; implementations MAY allow operation-
       // specific subclasses/handlers to transform validatedData here
       // (e.g. attaching a derived field). This hook receives only the
       // validated data — it MUST NOT receive or have access to raw,
       // unvalidated input.

    3. persistableData := validatedData WITHOUT any attribute names
       declared under the File capability (see Capability Contracts).
       File-backed attributes are deliberately excluded from the initial
       write — they are handled in step 5.

    4. entity := BEGIN TRANSACTION:
           entity := Persistence.create(EntityType, persistableData)
       COMMIT
       // The entire create write MUST occur inside one atomic
       // transactional boundary. No file I/O occurs inside this
       // boundary (see File Lifecycle — Transactional Timing Rule).

    5. ApplyFileLifecycle(entity, validatedData)
       // for every File-capability attribute present in validatedData,
       // performed AFTER the transaction has committed.

    6. SynchronizeRelations(entity, validatedData, depth: 0)
       // for every relation capability the entity declares, performed
       // AFTER step 5.

    7. Emit(EntityCreated, { entity, context: { entityType: EntityType,
       attributes: validatedData } })

    8. RETURN OperationResult.success(
           data: Serialize(entity),
           messages: [defaultCreatedMessage],
           code: CREATED)
```

**Conformance requirements:**
- Steps 4–6 MUST occur in this exact order. File I/O and relation synchronization MUST NOT occur before the create transaction commits.
- Step 7 MUST occur after steps 5 and 6 complete, so that the event payload reflects fully-settled state.
- The default success message and result code are implementation-defined but MUST be overridable per entity type.

### Update

```
operation Update(targetEntity, rawInput) -> OperationResult:

    1. validatedData := Validate(rawInput, ruleSetFor(EntityType))

    2. validatedData := BeforePersist(validatedData, targetEntity)
       // same extension point as Create, additionally receiving the
       // resolved target entity.

    3. persistableData := validatedData WITHOUT File-capability attribute names

    4. BEGIN TRANSACTION:
           Persistence.update(targetEntity, persistableData)
           // partial update semantics: only attributes present in
           // persistableData are modified; attributes absent from the
           // input MUST be left unchanged.
       COMMIT

    5. ApplyFileLifecycle(targetEntity, validatedData)

    6. SynchronizeRelations(targetEntity, validatedData, depth: 0)

    7. Emit(EntityUpdated, { entity: targetEntity, context: {
           entity: targetEntity, attributes: validatedData } })

    8. refreshedEntity := Persistence.reread(targetEntity)
       // re-read is REQUIRED because steps 5–6 may have further mutated
       // entity state (e.g. a file attribute set during step 5) after
       // the step-4 write was already committed.

    9. RETURN OperationResult.success(
           data: Serialize(refreshedEntity),
           messages: [defaultUpdatedMessage],
           code: OK)
```

**Resolution of `targetEntity` is out of scope for this specification.** How an implementation identifies which entity to update (path parameter, route binding, explicit lookup) is a transport/framework concern. The lifecycle above begins only once a concrete target entity reference is available.

### Delete

```
operation Delete(targetEntities: Entity[]) -> OperationResult:

    1. IF targetEntities is empty:
           RETURN OperationResult.error(
               messages: [defaultNotFoundMessage], code: NOT_FOUND)

    2. failedIdentifiers := []

    3. FOR EACH entity IN targetEntities:
           TRY:
               IF entity declares File capability:
                   FOR EACH attributeName IN entity.fileAttributeNames():
                       IF entity[attributeName] is set:
                           DeleteFile(entity, attributeName)
                           // see File Lifecycle

               Persistence.delete(entity)
               Emit(EntityDeleted, { entity })

           CATCH error:
               failedIdentifiers.append(entity.identifier)
               Emit(EntityDeletionFailed, { entity, error })
               // MUST NOT abort the loop. Each target is independent;
               // one failure MUST NOT prevent remaining targets from
               // being processed.

    4. IF failedIdentifiers is empty:
           RETURN OperationResult.success(messages: [defaultDeletedMessage], code: OK)

       ELSE IF failedIdentifiers.length == targetEntities.length:
           RETURN OperationResult.error(
               messages: [defaultDeleteFailedMessage], code: INTERNAL_ERROR,
               metadata: { failedIdentifiers })

       ELSE:
           RETURN OperationResult.partialSuccess(
               messages: [defaultPartialDeleteMessage],
               failedIdentifiers: failedIdentifiers,
               code: MULTI_STATUS)
```

**Conformance requirements:**
- An implementation MUST NOT silently swallow a per-entity deletion failure. Every failure MUST produce a corresponding failure event carrying the originating error.
- The aggregate result MUST distinguish three outcomes: total success, total failure, and partial success — collapsing partial failure into either total-success or total-failure is non-conformant.

### Bulk Delete

A specialization of Delete that additionally defines how `targetEntities` is derived from a raw, externally-supplied identifier list.

```
operation BulkDelete(rawIdentifierInput) -> OperationResult:

    1. rawIds := rawIdentifierInput
       IF rawIds is not a list:
           rawIds := [rawIds]
           // a single scalar identifier value MUST be normalized into a
           // one-element list rather than rejected or causing a runtime
           // type error. Defensive normalization against malformed
           // client input is a hard requirement, not an optimization.

    2. validIds := FILTER rawIds WHERE value is identifier-shaped
       // (e.g. numeric, or whatever identifier format the entity type uses)

    3. targetEntities := Persistence.findManyByIdentifiers(EntityType, validIds)
       // MUST be a single bulk retrieval operation, not one retrieval
       // per identifier.

    4. RETURN Delete(targetEntities)
```

---

## Validation Lifecycle

```
operation Validate(rawInput, ruleSet) -> validatedData:

    1. result := ExecuteValidation(rawInput, ruleSet)

    2. IF result has failures:
           RAISE StructuredValidationError(
               fieldErrors: result.fieldErrors)
           // MUST be a distinguishable, structured error type exposing
           // per-field error detail — not a generic/opaque exception.

    3. RETURN result.validatedFieldsOnly
       // MUST contain ONLY the fields explicitly declared by ruleSet.
       // Fields present in rawInput but NOT declared by ruleSet MUST be
       // discarded here, even though validation as a whole succeeded.
```

**This is the single most important invariant in the entire specification:** the validation step MUST return a strict allow-list of declared fields, never the raw input. An implementation that validates input against a rule set but then forwards the original raw input — on the theory that "validation passed, so it's safe" — is non-conformant. Validation passing means the *declared* fields are well-formed; it says nothing about *undeclared* fields, which MUST NOT reach persistence regardless of their content. This closes a class of unintended-mass-assignment defect where a client-supplied field never mentioned in any rule set could otherwise flow straight into storage.

**Rule set resolution requirements:**
- The rule set used for a given operation MUST be independently resolvable per operation type (Create and Update for the same entity type MAY use different rule sets — e.g. an identifier field required nowhere on create but conditionally present on update).
- Rule set resolution MUST NOT depend on global mutable state; it must be derivable from the entity type and operation alone.

---

## File Lifecycle

The File Lifecycle governs every attribute an entity declares under the File capability (see Capability Contracts). It defines three primitive operations — **Store**, **Delete**, **Resolve URL** — and one dispatch operation, **Apply Incoming Value**, used by the CRUD lifecycle.

### Apply Incoming Value — Three-Way Dispatch

```
operation ApplyFileLifecycle(entity, validatedData) -> void:
    IF entity does not declare File capability:
        RETURN  // no-op

    FOR EACH attributeName IN entity.fileAttributeNames():
        IF attributeName not present in validatedData:
            CONTINUE

        incomingValue := validatedData[attributeName]

        IF incomingValue is a file-upload payload (binary content + metadata):
            StoreFile(entity, attributeName, incomingValue)

        ELSE IF incomingValue is explicitly null:
            DeleteFile(entity, attributeName)

        ELSE:
            // any other value (e.g. an unchanged existing filename
            // string) — no operation. This is intentional: it allows a
            // client to round-trip a previously-resolved file
            // reference without that being misinterpreted as a write.
            CONTINUE
```

### Store

```
operation StoreFile(entity, attributeName, uploadPayload) -> FileOperationResult:

    1. namingStrategy :=
           IF entity declares the Preserve-Original-Name capability marker:
               OriginalNameStrategy   // see sanitization pipeline below
           ELSE:
               GeneratedNameStrategy  // collision-resistant, opaque name

    2. fileName := namingStrategy.generateName(uploadPayload, entity)

    3. directory := ResolveStoragePath(entity)
       // by default, delegates to the entity's own declared path
       // (Capability Contracts → File capability). MUST be overridable
       // at the implementation level without modifying every entity.

    4. Storage.write(directory, fileName, uploadPayload.content)

    5. entity[attributeName] := fileName
       Persistence.save(entity)
       // the entity attribute MUST store the file name (or a relative
       // reference) — NEVER a fully resolved URL. URLs are computed on
       // demand (see Resolve URL) so that backend/domain/CDN changes do
       // not require a data migration.

    6. Emit(FileStored, { entity, attributeName, fileName,
           url: ResolveURL(entity, fileName) })

    7. RETURN FileOperationResult { type: STORED, attributeName, fileName,
           url }
```

### Delete

```
operation DeleteFile(entity, attributeName) -> FileOperationResult:

    1. currentFileName := entity[attributeName]

    2. IF currentFileName is set:
           directory := ResolveStoragePath(entity)
           Storage.delete(directory, currentFileName)

    3. entity[attributeName] := null
       Persistence.save(entity)
       // Steps 3 MUST occur unconditionally, in the same logical
       // operation as step 2, regardless of whether step 2 actually
       // found a file to remove. This is a hard requirement: physical
       // removal and reference nullification MUST be treated as one
       // atomic unit of work from the caller's perspective. An
       // implementation that removes the physical file but leaves the
       // persisted attribute pointing at a now-nonexistent file is
       // non-conformant — this produces dangling references that
       // surface as broken links to every downstream consumer.

    4. Emit(FileDeleted, { entity, attributeName, fileName: currentFileName })

    5. RETURN FileOperationResult { type: DELETED, attributeName,
           fileName: currentFileName }
```

### Resolve URL

```
operation ResolveURL(entity, fileName) -> string:
    directory := ResolveStoragePath(entity)
    RETURN Storage.urlFor(directory, fileName)
    // MUST be computed lazily, on every call — implementations MUST NOT
    // cache or persist a resolved URL as part of entity state.
```

### Original-Name Sanitization Pipeline

When an entity declares the Preserve-Original-Name marker capability, the client-supplied file name is preserved rather than replaced with a generated opaque name — but it MUST be sanitized through the following ordered pipeline before being used as a storage path component. **Every step is mandatory; skipping any step is non-conformant and constitutes a path-traversal and injection vulnerability.**

```
operation SanitizeOriginalName(clientSuppliedName) -> string:

    1. name := StripDirectoryComponents(clientSuppliedName)
       // remove any path/directory segment, regardless of the
       // operating environment's path-separator convention. Defends
       // against sequences such as "../../etc/passwd" by reducing the
       // input to its final path segment only.

    2. name := RemoveControlCharactersAndNullBytes(name)

    3. name := KeepOnlyAllowListedCharacters(name)
       // allow-list: letters, digits, space, period, underscore, hyphen.
       // Every other character MUST be removed, not escaped.

    4. name := CollapseRepeatedPeriods(name)
       // defense against residual traversal-like sequences surviving
       // step 3 (e.g. multiple consecutive periods).

    5. name := ReplaceWhitespaceWithUnderscores(name)

    6. name := TrimLeadingAndTrailingSeparators(name)
       // trims period, underscore, hyphen from both ends.

    7. IF name is empty after the above:
           RETURN GeneratedNameStrategy.generateName(...)
           // sanitization MUST NOT be allowed to produce an empty or
           // unusable file name; fall back to the opaque generated-name
           // strategy in that case.

    8. RETURN name
```

**Known limitation, acceptable by design:** because the original name is intentionally preserved, two uploads producing the same sanitized name for the same entity MAY overwrite one another. This is an inherent trade-off of "preserve the original name" semantics, not a defect. Implementations needing both human-readable names and collision safety MUST layer a disambiguating suffix (e.g. a timestamp or generated identifier) on top of this pipeline — that layering is implementation-defined and outside this specification's scope.

### Transactional Timing Rule

**All file Store and Delete operations MUST be deferred until after the enclosing CRUD operation's persistence transaction has committed.** File mutations MUST NOT be performed while a transaction is still open.

Rationale: the storage backend is not part of the relational/transactional boundary used for entity persistence. Performing file I/O inside an open transaction creates a window where a subsequent rollback leaves a physical file on disk with no corresponding persisted reference — an orphaned write with no automatic compensating action, since file-system writes cannot be transactionally rolled back the way a database write can.

---

## Relation Synchronization Lifecycle

Relation synchronization governs how nested child data submitted alongside a parent entity is reconciled against persisted child records, for three relation shapes: **one-to-many**, **one-to-one**, and **many-to-many** (see Capability Contracts for their formal declarations).

### Dispatch

```
operation SynchronizeRelations(entity, data, depth) -> void:

    1. IF depth > MaximumRecursionDepth:
           RAISE RecursionDepthExceededError
       // MaximumRecursionDepth is implementation-configurable; reference
       // value: 5. This guard is REQUIRED — unbounded or cyclic
       // recursion through nested relation graphs MUST be prevented.

    2. IF entity declares One-To-Many capability:
           FOR EACH relationName IN entity.manyRelationNames():
               IF relationName present in data:
                   SyncOneToMany(entity, relationName, data[relationName], depth)
               ELSE IF StrictCapabilityMode is enabled:
                   RAISE UnsupportedCapabilityError(relationName)
               // ELSE: silently skip (default behavior)

    3. IF entity declares One-To-One capability:
           // same present/strict/skip logic, dispatching to SyncOneToOne

    4. IF entity declares Many-To-Many capability:
           // same present/strict/skip logic, dispatching to SyncManyToMany

    5. Each successful sync in steps 2–4 emits RelationSynced
       { entity, relationName, relationType }
```

**Strict Mode** is a configurable toggle: when enabled, an entity that declares a relation capability but receives no corresponding key in the incoming data raises a structured error rather than silently skipping. Default: disabled (permissive skip), to preserve backward compatibility with partial-update semantics where omitting a relation key means "leave it untouched."

### One-To-Many Synchronization (Diff-Based)

```
operation SyncOneToMany(parent, relationName, incomingRows, depth) -> void:

    1. IF incomingRows is not a list:
           IF incomingRows is a single record-shaped value:
               incomingRows := [incomingRows]
           ELSE:
               incomingRows := []

    2. incomingIdentifiers := every identifier value present among
       incomingRows that explicitly carries one (rows without an
       identifier are treated as new records)

    3. existingByIdentifier := Persistence.bulkLoad(
           childTypeOf(relationName), parent, incomingIdentifiers)
       // MUST be a single bulk retrieval keyed by the full identifier
       // set — NOT one retrieval per incoming row. This is a hard
       // performance requirement; per-row retrieval inside this loop
       // is non-conformant.

    4. orphans := every existing child of `parent` under `relationName`
       whose identifier is NOT present in incomingIdentifiers

    5. FOR EACH orphan IN orphans:
           IF orphan declares File capability:
               FOR EACH attributeName IN orphan.fileAttributeNames():
                   DeleteFile(orphan, attributeName)
           Persistence.delete(orphan)
       // an empty incomingRows list is a valid input and results in
       // EVERY existing child being treated as an orphan and removed.
       // This is intentional, full-replacement semantics — callers
       // that want to leave the relation untouched MUST omit the
       // relation key entirely rather than submitting an empty list.

    6. FOR EACH row IN incomingRows:
           rowData := row WITHOUT its identifier field
               // the identifier, if present, is used only for matching
               // in step 7 — it is never written as a persisted field.

           IF row had an identifier AND existingByIdentifier contains it:
               child := existingByIdentifier[identifier]
               ApplyFields(child, rowData)
               Persistence.save(child)
           ELSE:
               child := Persistence.createChild(parent, relationName, rowData)

           ApplyFileLifecycle(child, rowData)
               // if `child` declares File capability

           IF child declares ANY relation capability:
               SynchronizeRelations(child, rowData, depth + 1)
               // see Recursion Correctness Invariant below — this MUST
               // re-derive the child's capabilities independently; it
               // MUST NOT assume the child supports the same relation
               // shape as the parent.
```

### One-To-One Synchronization (Update-or-Create)

```
operation SyncOneToOne(parent, relationName, incomingData, depth) -> void:

    1. IF incomingData is empty or absent:
           RETURN  // no-op — a one-to-one relation cannot be cleared
                    // through this mechanism; there is no implicit
                    // deletion path for a singular relation.

    2. data := incomingData WITHOUT any identifier field
       // the singular child's identity is structural (its association
       // with the parent), never client-supplied.

    3. existingChild := Persistence.findAssociated(parent, relationName)

    4. IF existingChild exists:
           ApplyFields(existingChild, data)
           Persistence.save(existingChild)
           child := existingChild
       ELSE:
           child := Persistence.createChild(parent, relationName, data)

    5. ApplyFileLifecycle(child, data)   // if applicable
    6. IF child declares any relation capability:
           SynchronizeRelations(child, data, depth + 1)
```

### Many-To-Many Synchronization (Full Replacement)

```
operation SyncManyToMany(entity, relationName, incomingIdentifiers) -> void:

    1. normalizedIds := FILTER incomingIdentifiers WHERE value is not
       null and not empty

    2. Persistence.replaceAssociations(entity, relationName, normalizedIds)
       // standard set-replacement semantics: associations present in
       // normalizedIds but not currently associated are added;
       // associations currently present but absent from normalizedIds
       // are removed; associations present in both are left untouched.

    3. an empty normalizedIds list clears ALL associations for that
       relation — same full-replacement principle as One-To-Many's
       empty-list behavior.
```

**Many-to-many incoming data MUST be a flat list of association identifiers, never a list of full record objects.** This is a deliberate shape distinction from one-to-many incoming data (which IS a list of record objects). An implementation that accepts record-shaped data for a many-to-many relation is non-conformant with this specification's wire contract.

### Recursion Correctness Invariant

**This is the single most important correctness rule in this section, derived directly from a defect class observed in an earlier reference implementation.**

> When relation synchronization recurses into a child entity's own declared relations, the determination of which relation capability/capabilities the child supports MUST be re-derived from the Capability Registry, queried against the child entity itself, at every level of recursion. An implementation MUST NOT assume, inherit, hardcode, or otherwise carry forward the parent's relation-handling branch when processing a child.

The defect this rule prevents: an earlier implementation's one-to-one synchronization routine, when recursing into a child's own nested relations, reused the *one-to-many* capability-lookup method unconditionally — regardless of what the child entity actually declared. A child that implemented only the one-to-one relation capability (and not the one-to-many capability) caused a hard failure during recursion, because the code path assumed every recursion target exposed the one-to-many accessor. The fix is structural, not a one-line patch: recursion must always go back through the single, generic dispatch operation (`SynchronizeRelations`), which independently re-queries the Capability Registry for whatever entity it is currently processing. No relation-sync routine may special-case "what kind of entity is this child" — that determination belongs exclusively to the Capability Registry, queried fresh, every time.

### Nested File Handling

A child entity created or updated during relation synchronization is itself eligible for full File Lifecycle processing if it declares the File capability — this is not a special case requiring separate logic; it is the same `ApplyFileLifecycle` operation defined in the File Lifecycle section, invoked with the child entity and its row data. File attributes nested arbitrarily deep in a relation graph are handled identically to a top-level entity's file attributes.

---

## Event Lifecycle

The CRUD, File, and Relation lifecycles above communicate every meaningful state transition exclusively through events. **Lifecycle operations MUST NOT perform direct side effects (logging, cache invalidation, notification dispatch, audit-trail writes) inline.** Any such behavior MUST be implemented as an event observer, external to the core lifecycle code. This separation is a hard architectural requirement: it is what allows the core lifecycle to remain free of cross-cutting concerns and fully testable in isolation.

### Event Catalog

| Event | Emitted by | Payload | Firing point |
|---|---|---|---|
| `EntityCreated` | Create | `{ entity, context: { entityType, attributes } }` | After file ops and relation sync complete |
| `EntityUpdated` | Update | `{ entity, context: { entity, attributes } }` | After file ops and relation sync complete |
| `EntityDeleted` | Delete (per target) | `{ entity }` | Immediately after that target's successful removal |
| `EntityDeletionFailed` | Delete (per target) | `{ entity, error }` | Immediately after that target's failed removal attempt; MUST carry the causing error, never discard it |
| `FileStored` | File Lifecycle — Store | `{ entity, attributeName, fileName, url }` | After the file write and attribute persistence both succeed |
| `FileDeleted` | File Lifecycle — Delete | `{ entity, attributeName, fileName }` | After physical removal and attribute nullification both succeed |
| `RelationSynced` | Relation Synchronization (per relation) | `{ entity, relationName, relationType }` | After that specific relation's sync operation completes successfully |

**Conformance requirements:**
- `EntityDeletionFailed` MUST carry the original error object/value, not a generic boolean or string summary. Discarding the underlying cause is non-conformant — it was a known defect class in an earlier implementation where failures were caught and converted to a bare `false` return with no diagnostic trail.
- A default observer SHOULD exist that performs structured logging for all seven event types, but this observer MUST be ordinary — replaceable, disable-able, and implemented using the exact same observer mechanism available to any other event subscriber. The core lifecycle MUST NOT special-case or hardcode this default observer's behavior.
- Event dispatch MUST be synchronous with respect to the lifecycle operation that triggers it, unless the implementing platform's event mechanism explicitly documents otherwise to its own subscribers. The lifecycle operation itself MUST NOT depend on event handling completing in any particular way (i.e., an observer's failure MUST NOT be allowed to corrupt or roll back an already-committed lifecycle operation).

---

## Response Contracts

Every CRUD lifecycle operation, and any other operation that produces a user-facing outcome, returns a single, uniform **Operation Result** structure.

### Operation Result

```
type OperationResult = {
    status: enum { SUCCESS, PARTIAL_SUCCESS, ERROR }
    messages: string[]
    data: Map<string, any>
    code: int
    failedIdentifiers: (string | int)[]   // populated only for partial outcomes
    metadata: Map<string, any>             // open-ended, implementation-defined extras
}
```

| Field | Required | Notes |
|---|---|---|
| `status` | yes | Exactly one of the three values. `PARTIAL_SUCCESS` is reserved for batch operations (bulk delete) where some but not all targets succeeded. |
| `messages` | yes | One or more human-displayable strings or message-resolution keys (see below). MUST NOT be empty. |
| `data` | yes (may be empty) | The operation's resulting payload — typically the serialized entity for Create/Update, empty for Delete. |
| `code` | yes | A numeric outcome code. See conventional HTTP mapping below (informative, not mandatory for non-HTTP transports). |
| `failedIdentifiers` | conditional | MUST be populated when `status == PARTIAL_SUCCESS`, and SHOULD be omitted (not merely empty) otherwise. |
| `metadata` | no | Reserved for implementation-specific supplementary context. |

### Message Resolution Contract

A message in the `messages` list is one of two kinds, distinguished structurally (the reference convention: presence of a namespace-delimiter character such as a dot):

```
operation ResolveMessage(message) -> string:
    IF message does not look like a structured reference key:
        RETURN message   // plain literal text, returned unchanged

    resolved := LocalizationLookup(message, activeLocale)

    IF resolved was not found (lookup returned the key itself, unchanged):
        RETURN message   // MUST fall back to the raw key, never raise
                          // an error and never return an empty string

    RETURN resolved
```

**Conformance requirement:** message resolution MUST be total — every input MUST produce a non-empty, displayable output string, even when the localization layer has no entry for a given key and even when the message is plain text with no localization mapping at all. A response MUST NEVER surface an empty or `null` message as a result of a resolution miss.

A conforming implementation SHOULD ship baseline message catalogs covering, at minimum, success and failure outcomes for Create, Update, and Delete, in at least two languages, and these catalogs MUST be fully overridable by the consuming application without modifying the core implementation.

### Conventional HTTP Status Mapping (Informative)

Where the transport is HTTP, the reference implementation maps `code` as follows. This mapping is **not** a hard requirement of this specification — implementations using a different transport (e.g. gRPC status codes) MAY define their own mapping — but is provided as default guidance for HTTP-based ports.

| Outcome | Conventional code |
|---|---|
| Create success | 201 (Created) |
| Update success | 200 (OK) |
| Delete success | 200 (OK) |
| Delete — no targets resolved | 404 (Not Found) |
| Delete — partial success | 207 (Multi-Status) |
| Delete — total failure | 500 (Internal Server Error) |
| Validation failure | 422 (Unprocessable Entity) |

---

## Statistics Contracts

The Statistics contract defines a time-bucketed aggregation capability: counting or summing entities grouped into day/month/year buckets across a date range, independent of which underlying query engine performs the aggregation.

### Query Specification

```
type StatisticsQuery = {
    entityType: string
    dateAttribute: string
    sumAttribute: string?        // null/absent → count rows; present → sum this attribute
    startDate: string
    endDate: string
    granularity: enum { DAY, MONTH, YEAR }
    scopes: string[]             // optional named pre-defined query constraints
    allowedFilters: string[]     // optional named ad-hoc filters, for engines that support them
}
```

### Execution Contract

```
operation ExecuteStatisticsQuery(query) -> Map<string, number>:
    // returns a SPARSE map: only buckets that actually contain matching
    // data are present. Gap-filling is explicitly NOT this operation's
    // responsibility — see Presentation Contract below.
```

**Engine portability requirement:** bucket-grouping logic MUST NOT depend on a specific underlying engine's date-formatting or date-extraction functions. An implementation MUST be able to produce bucket keys (e.g. `"2026-01-15"`, `"2026-01"`, `"2026"`) by parsing the raw date/time value at the application layer, so that aggregation behaves identically regardless of which storage engine is in use underneath. Pushing engine-specific date formatting down into the query itself is non-conformant, because it silently breaks portability between engines that do not share the same date-formatting function surface.

### Presentation Contract

```
operation GetStatistics(query) -> Map<string, number>:

    1. sparseResult := ExecuteStatisticsQuery(query)

    2. fullSeries := {}
       FOR EACH bucket IN every bucket between query.startDate and
       query.endDate at query.granularity, in order:
           fullSeries[bucket] := sparseResult[bucket] IF PRESENT ELSE 0

    3. RETURN fullSeries
       // every bucket in the requested range MUST be present in the
       // final result, including buckets with zero matching data. A
       // consumer (e.g. a charting component) MUST NEVER need to handle
       // a missing bucket.
```

### Caching Contract

A complete, zero-filled result for a given `StatisticsQuery` SHOULD be cached, keyed by the full identity of the query (entity type + date attribute + sum-attribute-or-count-mode + start + end + granularity), for a configurable duration. Different query identities MUST NOT collide in the cache. Cache duration is deployment-configurable; the reference default is 300 seconds.

---

## Capability Contracts

This is the foundational layer every other section depends on. A capability is a declared, queryable behavior an entity opts into. **No component anywhere in the system may perform direct, ad-hoc inspection of an entity to determine what it supports — every such determination MUST be delegated to a single Capability Registry.** This is a hard architectural constraint, not a style preference: a known defect class (the recursion-correctness failure described in the Relation Synchronization section) arose directly from this rule being violated — capability checks were independently duplicated in multiple places, and one of those duplicated checks was wrong, with no single point of correction.

### File Capability

```
interface FileUploadCapability {
    resolveStoragePath(): string
        // the storage directory/namespace this entity's files live
        // under. MUST be derivable from the entity alone (e.g. from its
        // type and identifier). No fixed structure is mandated — this
        // method is the entity's own declaration of where its files go.

    fileAttributeNames(): string[]
        // the set of attribute names on this entity that are
        // file-backed and therefore eligible for File Lifecycle
        // processing.
}
```

### One-To-Many Relation Capability

```
interface HasManyRelationsCapability {
    manyRelationNames(): string[]
        // names of relations on this entity that should be diff-synced
        // (see Relation Synchronization Lifecycle — One-To-Many).
}
```

### One-To-One Relation Capability

```
interface HasOneRelationCapability {
    oneRelationNames(): string[]
        // names of relations on this entity that should be
        // update-or-created (see One-To-One Synchronization).
}
```

### Many-To-Many Relation Capability

```
interface ManyToManyRelationsCapability {
    manyToManyRelationNames(): string[]
        // names of relations on this entity that should be synced via
        // full-set-replacement (see Many-To-Many Synchronization).
}
```

### Preserve-Original-Name Capability (Marker)

```
interface PreserveOriginalNameCapability {
    // no members. Presence alone is the signal: when an entity declares
    // this capability, the File Lifecycle's naming-strategy selection
    // (see File Lifecycle — Store, step 1) MUST use the sanitizing
    // original-name strategy instead of the default generated-name
    // strategy.
}
```

### Capability Registry

```
interface CapabilityRegistry {
    supportsFileUpload(entity): boolean
    supportsHasMany(entity): boolean
    supportsHasOne(entity): boolean
    supportsManyToMany(entity): boolean
    prefersOriginalFileName(entity): boolean
}
```

**Conformance requirements:**
- Every component in the system that needs to know whether an entity supports a given capability (the CRUD lifecycle, the File lifecycle, the Relation synchronization lifecycle, serialization logic) MUST query the Capability Registry — never perform its own independent type/structural check.
- The Capability Registry implementation itself MAY use any mechanism appropriate to the host language (interface/type checks, attribute/annotation inspection, structural duck-typing, a manifest file) — this specification does not mandate a mechanism, only that there be exactly one arbiter, consulted uniformly.
- A conforming implementation MUST allow the Capability Registry to be replaced/overridden by the consuming application without modifying any lifecycle code that consumes it.

---

## Conformance Checklist

A condensed, audit-style summary of the MUST-level requirements above, for implementers validating a port against this specification:

- [ ] Validation returns only explicitly declared fields; undeclared input fields never reach persistence (Validation Lifecycle)
- [ ] Create/Update persist inside one atomic transactional boundary that excludes all file I/O (CRUD Lifecycle, File Lifecycle — Transactional Timing Rule)
- [ ] File deletion always nulls/clears the persisted attribute reference in the same operation as the physical removal (File Lifecycle — Delete)
- [ ] Original-filename sanitization runs the full seven-step pipeline unconditionally, with a safe fallback on empty result (File Lifecycle — Sanitization Pipeline)
- [ ] One-to-many sync bulk-loads existing children in a single retrieval, never one retrieval per incoming row (Relation Synchronization — One-To-Many)
- [ ] Relation recursion re-queries the Capability Registry at every level; no relation-sync routine assumes a child's capabilities (Relation Synchronization — Recursion Correctness Invariant)
- [ ] Bulk delete normalizes a scalar identifier input into a single-element list rather than failing (CRUD Lifecycle — Bulk Delete)
- [ ] Every per-target deletion failure is captured and surfaces a structured error via an event; failures never abort remaining targets (CRUD Lifecycle — Delete)
- [ ] All side effects (logging, cache invalidation, notifications) are implemented as event observers, never inline in lifecycle code (Event Lifecycle)
- [ ] Message resolution is total — always returns a non-empty displayable string, falling back to the raw key on a resolution miss (Response Contracts)
- [ ] Statistics aggregation does not depend on engine-specific date-formatting functions (Statistics Contracts — Engine Portability)
- [ ] Statistics results are zero-filled across the full requested range before being returned to a caller (Statistics Contracts — Presentation Contract)
- [ ] All capability checks throughout the system go through a single Capability Registry (Capability Contracts)

---

## Appendix: Traceability to the Reference Implementation

This appendix is informative only and is the sole place in this document where a specific reference implementation is named. It exists to help an implementer cross-reference this specification's abstract terminology against the concrete Laravel package (`nexus/crud-engine`) it was extracted from.

| Specification term | Reference implementation |
|---|---|
| CRUD Lifecycle — Create / Update / Delete / Bulk Delete | `AbstractStoreService` / `AbstractUpdateService` / `AbstractDeleteService` / `AbstractBulkDeleteService` |
| Validation Port | `RequestValidatorInterface` (`LaravelRequestValidator`) |
| Persistence Port | `RepositoryInterface` (`EloquentRepository`, via `RepositoryFactory`) |
| Storage Port | `FileLifecycleServiceInterface` (`FileLifecycleService`) |
| Naming Strategy | `FileNamingStrategyInterface` (`HashedFilenameStrategy`, `OriginalFilenameStrategy`) |
| Path Resolution | `FilePathResolverInterface` (`ModelDefinedPathResolver`) |
| Relation Synchronization dispatch | `RelationSyncManagerInterface` (`RelationSyncManager`) |
| One-to-many / one-to-one / many-to-many sync | `HasManySyncStrategy` / `HasOneSyncStrategy` / `ManyToManySyncStrategy` |
| Capability Registry | `CapabilityRegistryInterface` (`CapabilityRegistry`) |
| File capability | `FileUpload` |
| One-to-many / one-to-one / many-to-many capabilities | `HasManyRelations` / `HasOneRelations` / `ManyToManyRelations` |
| Preserve-original-name marker capability | `OriginalName` |
| Event Dispatch Port | `RecordCreated`, `RecordUpdated`, `RecordDeleted`, `RecordDeletionFailed`, `FileStored`, `FileDeleted`, `RelationSynced` |
| Default event observer | `LogCrudOperationListener` |
| Operation Result | `CrudOperationResult` |
| Statistics query engine | `StatisticsQueryStrategyInterface` (`EloquentAggregateStrategy`, `SpatieQueryBuilderStrategy`) |
| Statistics caching/zero-fill orchestration | `AbstractStatisticsService` |

See `docs/API-Reference.md` in the reference implementation for the complete concrete API surface.
