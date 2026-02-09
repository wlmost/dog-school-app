## Context

**Current State:**
The `AnamnesisResponseResource` returns nested relationship objects (`dog`, `template`) but does not provide denormalized display fields. The frontend `AnamnesisView.vue` table expects `dogName`, `customerName`, and `templateName` to be present directly on the response object for efficient rendering.

**Problem:**
When a new Anamnese is created, the table row shows the response but the Hund, Besitzer, and Vorlage columns are empty because these denormalized fields are missing from the API response.

**Current Architecture:**
- Controller: Eager loads `dog.customer`, `template` relationships
- Resource: Returns nested objects (`dog: DogResource`, `template: AnamnesisTemplateResource`)
- Frontend: Attempts to access `response.dogName`, `response.customerName`, `response.templateName`

**Stakeholders:**
- Trainers/Admins viewing Anamnese list (primary users)
- Frontend Vue component rendering the table
- Backend API resource transformation

## Goals / Non-Goals

**Goals:**
- Provide `dogName`, `customerName`, `templateName` fields in `AnamnesisResponseResource`
- Fix empty table cells in Anamnese list view immediately after creation
- Maintain backward compatibility with nested relationship objects
- Use null-safe access to prevent errors when relationships aren't loaded

**Non-Goals:**
- Changing the database schema or model relationships
- Modifying the frontend component to navigate nested objects
- Refactoring other resources to follow this pattern (isolated fix)
- Adding new relationships or changing eager loading strategy

## Decisions

### Decision 1: Add Denormalized Fields to Resource (not change frontend)

**Chosen Approach:** Add `dogName`, `customerName`, `templateName` to `AnamnesisResponseResource`

**Rationale:**
- Frontend already expects these fields - changing frontend would be more invasive
- Multiple views may depend on this data structure
- Resources are the correct place for data transformation/denormalization
- Follows API versioning principle: backend adapts to client needs

**Alternative Considered:** Change frontend to access `response.dog?.name`
- **Rejected:** Would require changes across multiple frontend components
- **Rejected:** Less efficient for list views (requires nested access in templates)
- **Rejected:** Frontend is already coded and tested with current structure

### Decision 2: Use Null-Safe Access with Null Coalescing

**Chosen Approach:** Use PHP 8 nullsafe operator (`?->`) and null coalescing (`??`)

```php
'dogName' => $this->dog?->name ?? null,
'customerName' => $this->dog?->customer?->user?->fullName ?? null,
'templateName' => $this->template?->name ?? null,
```

**Rationale:**
- Safely handles cases where relationships aren't loaded
- Returns `null` instead of throwing errors
- Graceful degradation if controller forgets to eager load
- Frontend can handle null values (shows empty cell vs breaking)

**Alternative Considered:** Assert relationships are always loaded
- **Rejected:** Creates fragile coupling between controller and resource
- **Rejected:** Would throw errors instead of degrading gracefully

### Decision 3: Traverse to customer.user.fullName for Owner Name

**Chosen Approach:** Access `$this->dog?->customer?->user?->fullName`

**Rationale:**
- `fullName` is a computed accessor on User model (firstName + lastName)
- Matches the pattern used elsewhere in the application
- Controller already loads `dog.customer` relationship
- Provides properly formatted name (not just "customer_id")

**Alternative Considered:** Add `user` to eager load: `dog.customer.user`
- **Not needed:** Laravel automatically loads when accessed via nullsafe operator
- **Current:** Controller uses `dog.customer`, which is sufficient

### Decision 4: Keep Nested Objects for Backward Compatibility

**Chosen Approach:** Add denormalized fields alongside existing nested objects

**Rationale:**
- Other parts of the frontend may use nested `dog`, `template` objects
- Detail views may need full object data (not just names)
- Zero risk of breaking existing functionality
- Minimal overhead (names already in memory from loaded relationships)

**Alternative Considered:** Remove nested objects, only provide denormalized fields
- **Rejected:** High risk of breaking detail modals or other views
- **Rejected:** Would require full frontend audit and testing

## Risks / Trade-offs

**Risk:** Relationship not eagerly loaded → fields return null  
**Mitigation:** Controller already loads required relationships; nullsafe operators prevent errors

**Risk:** Increased response payload size  
**Trade-off:** Minimal (3 extra string fields); acceptable for improved UX and frontend simplicity

**Risk:** Data duplication in JSON response  
**Trade-off:** Standard pattern for read-optimized APIs; improves rendering performance in list views

**Risk:** Field names diverge from relationship structure  
**Mitigation:** Match frontend expectations (documented in proposal); consistent with existing patterns

## Migration Plan

**Deployment Steps:**
1. Deploy backend change to production (safe - additive only)
2. No frontend changes needed (already expects these fields)
3. Verify table displays names correctly after new Anamnese creation

**Rollback Strategy:**
- Removing these fields would break the frontend
- If issues arise, fix forward (adjust field logic), don't rollback
- Zero-downtime deployment (existing functionality unaffected)

**Validation:**
- Check API response includes `dogName`, `customerName`, `templateName`
- Verify list view table shows values in all three columns
- Test with null relationships (edge case: deleted dog/template)

## Open Questions

None - straightforward additive change with clear precedent in codebase.
