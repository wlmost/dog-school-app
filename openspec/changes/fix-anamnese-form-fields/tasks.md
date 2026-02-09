## 1. Update AnamnesisResponseResource

- [x] 1.1 Open backend/app/Http/Resources/AnamnesisResponseResource.php
- [x] 1.2 Add `dogName` field using nullsafe operator: `$this->dog?->name ?? null`
- [x] 1.3 Add `customerName` field navigating relationships: `$this->dog?->customer?->user?->fullName ?? null`
- [x] 1.4 Add `templateName` field using nullsafe operator: `$this->template?->name ?? null`
- [x] 1.5 Verify fields are added to the toArray() return array
- [x] 1.6 Ensure existing fields remain unchanged (backward compatibility)

## 2. Verify Controller Eager Loading

- [x] 2.1 Open backend/app/Http/Controllers/AnamnesisResponseController.php
- [x] 2.2 Confirm index() method already loads `dog.customer`, `template` relationships
- [x] 2.3 Verify show() and update() methods also load required relationships
- [x] 2.4 No changes needed if relationships already loaded (they are)

## 3. Testing - Manual Verification

- [x] 3.1 Start backend server and ensure database is running
- [x] 3.2 Create a new Anamnese record via the frontend
- [x] 3.3 Verify API response includes `dogName` field with dog's name
- [x] 3.4 Verify API response includes `customerName` field with owner's full name
- [x] 3.5 Verify API response includes `templateName` field with template's name
- [x] 3.6 Confirm table in AnamnesisView displays all three fields correctly
- [x] 3.7 Verify no console errors in browser
- [x] 3.8 Verify no PHP errors in backend logs

## 4. Testing - Edge Cases

- [x] 4.1 Test existing Anamnese records (should show fields correctly)
- [x] 4.2 Test with different templates (field should show correct template name)
- [x] 4.3 Test with different dogs and owners (fields should show correct names)
- [x] 4.4 Verify backward compatibility: nested `dog` and `template` objects still present

## 5. Documentation and Cleanup

- [x] 5.1 Check if resource file needs PHPDoc comments for new fields - Added inline comments
- [x] 5.2 Verify code follows Laravel coding standards - Code passes standards
- [x] 5.3 Confirm no debug code or console.log statements remain - Verified clean

## 6. Git Commit and Archive

- [ ] 6.1 Stage modified AnamnesisResponseResource.php file
- [ ] 6.2 Commit with descriptive message referencing this OpenSpec change
- [ ] 6.3 Push changes to remote repository
- [ ] 6.4 Archive this OpenSpec change using /opsx:archive
