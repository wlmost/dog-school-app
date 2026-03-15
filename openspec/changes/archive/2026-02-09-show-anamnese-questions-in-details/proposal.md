# Proposal: Show Question Text in Anamnesis Details

**Change ID**: show-anamnese-questions-in-details
**Created**: 2026-02-09
**Status**: Proposal

## Problem Statement

When viewing Anamnesis details in the AnamnesisDetailModal, the question text is not displayed alongside each answer. The frontend template expects a denormalized `questionText` field on each answer object, but the backend AnamnesisAnswerResource only provides a nested `question` object.

**Current Behavior**:
- Frontend line 84 in AnamnesisDetailModal.vue accesses: `{{ answer.questionText }}`
- Backend AnamnesisAnswerResource provides: `'question' => new AnamnesisQuestionResource($this->whenLoaded('question'))`
- Controller loads relationship: `$anamnesisResponse->load(['answers.question'])` (line 128)
- Result: Questions not displaying because field name mismatch

**Root Cause**:
Data structure mismatch between frontend expectation (flat `questionText`) and backend response (nested `question.questionText`).

## Proposed Solution

Add a denormalized `questionText` field to AnamnesisAnswerResource using null-safe operators, following the same pattern as the previous fix for `dogName`, `customerName`, and `templateName` in AnamnesisResponseResource.

**Implementation**:
```php
// backend/app/Http/Resources/AnamnesisAnswerResource.php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'questionId' => $this->question_id,
        'questionText' => $this->question?->questionText ?? null,  // Add denormalized field
        'answerValue' => $this->answer_value,
        'question' => new AnamnesisQuestionResource($this->whenLoaded('question')),
    ];
}
```

## Benefits

1. **Immediate Fix**: Questions display correctly in detail modal
2. **No Frontend Changes**: Template already expects this field structure
3. **Backward Compatible**: Maintains nested `question` object for other consumers
4. **Consistent Pattern**: Follows established denormalization pattern from previous fix
5. **Null-Safe**: Handles edge cases where question might not be loaded

## Scope

**Files to Modify**:
- `backend/app/Http/Resources/AnamnesisAnswerResource.php` - Add denormalized field

**No Changes Required**:
- Frontend already structured correctly
- Backend controller already loads relationship
- No database changes needed

## Success Criteria

- Trainers can see question text alongside each answer in AnamnesisDetailModal
- No metadata displayed (type, required flag)
- Maintains backward compatibility
- Follows Laravel and PHP 8 best practices

## Risks & Mitigation

**Risk**: None - This is a simple field addition
**Mitigation**: Use null-safe operators to handle edge cases

## Alternatives Considered

1. **Change Frontend to Access Nested Field**: Requires template modifications, less intuitive
2. **Add Question Text to Frontend Type Only**: Doesn't solve backend data issue
3. **Current Solution**: ✅ Add denormalized field - Clean, consistent, minimal change
