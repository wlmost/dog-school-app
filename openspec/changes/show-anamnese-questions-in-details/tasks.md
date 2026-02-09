# Tasks: Show Question Text in Anamnesis Details

**Change ID**: show-anamnese-questions-in-details
**Created**: 2026-02-09
**Status**: Ready for Implementation

## 1. Backend Implementation

- [ ] 1.1 Read current AnamnesisAnswerResource implementation
- [ ] 1.2 Add questionText denormalized field using null-safe operator
- [ ] 1.3 Verify field placement in resource array (after questionId, before answerValue)
- [ ] 1.4 Ensure nested question object remains for backward compatibility

## 2. Verification

- [ ] 2.1 Verify backend controller loads answers.question relationship
- [ ] 2.2 Test AnamnesisDetailModal displays question text correctly
- [ ] 2.3 Verify no frontend changes required
- [ ] 2.4 Test edge case: answer with null question

## 3. Code Quality

- [ ] 3.1 Verify null-safe operators used correctly ($this->question?->questionText ?? null)
- [ ] 3.2 Confirm follows Laravel Resource patterns
- [ ] 3.3 Verify consistency with previous denormalization fixes (dogName pattern)
- [ ] 3.4 Add code comments if needed

## 4. Testing

- [ ] 4.1 Open existing anamnesis response in detail modal
- [ ] 4.2 Verify questions display alongside answers
- [ ] 4.3 Verify question metadata (type, required) not shown
- [ ] 4.4 Test with multiple answer types (text, checkbox, etc.)

## 5. Documentation

- [ ] 5.1 Update change artifacts if needed
- [ ] 5.2 Note API response structure change in comments
- [ ] 5.3 Verify backward compatibility maintained

## 6. Finalization

- [ ] 6.1 Review all changes
- [ ] 6.2 Commit changes with descriptive message
- [ ] 6.3 Push to remote repository
- [ ] 6.4 Archive change using OpenSpec workflow
