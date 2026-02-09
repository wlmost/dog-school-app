# Design: Show Question Text in Anamnesis Details

**Change ID**: show-anamnese-questions-in-details
**Created**: 2026-02-09
**Status**: Design

## Context

The application displays Anamnesis responses in AnamnesisDetailModal (frontend). Each response contains multiple answers, and each answer should display its associated question text.

**Current State**:
- Backend: AnamnesisResponseController loads `answers.question` relationship (line 128)
- Backend: AnamnesisAnswerResource returns nested `question` object with `whenLoaded()`
- Frontend: AnamnesisDetailModal.vue template expects flat `answer.questionText` field (line 84)
- Result: Questions don't display due to data structure mismatch

**Fixed Previously**: Similar issue with `dogName`, `customerName`, `templateName` in AnamnesisResponseResource - resolved by adding denormalized fields with null-safe operators.

**Constraints**:
- Must maintain backward compatibility with nested `question` object
- Must use PHP 8 null-safe operators
- Must follow Laravel Resource patterns
- No frontend changes required (template already correct)

## Goals / Non-Goals

**Goals:**
- Display question text alongside each answer in detail modal
- Maintain consistent denormalization pattern across resources
- Use null-safe operators for edge case handling
- Zero frontend changes

**Non-Goals:**
- Displaying question metadata (type, required flag)
- Changing frontend template structure
- Modifying database schema or migrations
- Changing API contracts for nested `question` object

## Decisions

### Decision 1: Denormalize questionText in AnamnesisAnswerResource

**Chosen Approach**: Add flat `questionText` field alongside existing nested `question` object

**Rationale**:
- Frontend template already expects this structure
- Consistent with previous fix (dogName, customerName, templateName pattern)
- Maintains backward compatibility
- Minimal code change

**Alternatives Considered**:
1. **Change Frontend to Access Nested Field** (`answer.question.questionText`)
   - ❌ Rejected: Requires frontend changes, less intuitive API
   - ❌ Rejected: Breaks established denormalization pattern
   
2. **Add Computed Property in Frontend**
   - ❌ Rejected: Duplicates logic, frontend already correct
   - ❌ Rejected: Should fix at source (backend)

3. **Current Solution** ✅
   - Fixes at data source
   - No frontend changes
   - Follows established pattern

### Decision 2: Use Null-Safe Operators

**Chosen Approach**: `$this->question?->questionText ?? null`

**Rationale**:
- PHP 8 best practice
- Handles edge cases (question not loaded, null relationship)
- Consistent with previous implementation

**Alternatives Considered**:
1. **Traditional null checks** (`isset($this->question) ? $this->question->questionText : null`)
   - ❌ Rejected: Verbose, legacy syntax
   
2. **Current Solution** ✅
   - Modern PHP 8 syntax
   - Concise and safe

### Decision 3: Field Placement in Resource Array

**Chosen Approach**: Place `questionText` after `questionId`, before `answerValue`

**Rationale**:
- Logical grouping (question-related fields together)
- Maintains readability
- Consistent ordering pattern

## Risks / Trade-offs

### Risk 1: Relationship Not Loaded
**Risk**: If controller doesn't load `answers.question`, field will be null
**Mitigation**: 
- Controller already loads relationship at line 128
- Null-safe operators handle edge case gracefully
- Verified in all controller methods (show, update, destroy)

### Risk 2: Performance Impact
**Risk**: Denormalization adds minimal overhead
**Mitigation**:
- Negligible impact (simple field access)
- Relationship already loaded, no additional queries
- Trade-off accepted for improved API usability

### Risk 3: Data Consistency
**Risk**: Denormalized field could become stale
**Mitigation**:
- Field computed at response time, always fresh
- Not stored, derived from relationship
- No consistency issues possible

## Migration Plan

**Deployment**:
1. Deploy backend change (AnamnesisAnswerResource.php)
2. No frontend deployment needed (already compatible)
3. No database migrations required
4. Zero downtime deployment

**Rollback**:
- Simple: Remove added line from resource
- Frontend continues working (graceful degradation)
- No data changes to revert

**Testing**:
- Verify questions display in AnamnesisDetailModal
- Test with existing anamnesis responses
- Confirm nested `question` object still present

## Open Questions

None - straightforward implementation following established pattern.
