## Why

After creating a new Anamnese record, the Hund (dog), Besitzer (owner), and Vorlage (template) fields are not displayed in the Anamnese list view table above the "Anamnese Vorlagen" card. While the data is stored correctly in the database and the relationships are loaded, the frontend expects denormalized field names (`dogName`, `customerName`, `templateName`) that the backend resource is not providing. This creates a poor user experience where newly created records appear with empty cells in critical columns.

## What Changes

- Add denormalized fields to `AnamnesisResponseResource` for direct frontend access
- Include `dogName`, `customerName`, and `templateName` in API response
- Ensure these fields are populated from the loaded relationships
- Maintain backward compatibility with existing nested relationship data

## Capabilities

### New Capabilities
- `anamnesis-response-display-fields`: Backend resource enhancement to provide display-friendly field names for dog, customer, and template information in list views

### Modified Capabilities
_None - this is a data presentation fix, not a requirement change to existing specs_

## Impact

**Affected Code:**
- `backend/app/Http/Resources/AnamnesisResponseResource.php` - Add denormalized fields

**Dependencies:**
- Requires relationships to be eagerly loaded (already in place via controller)
- Frontend expects these fields in `frontend/src/views/anamnesis/AnamnesisView.vue`

**Data Flow:**
- Controller loads: `dog.customer`, `template` relationships
- Resource adds: `dogName: $this->dog?->name`, `customerName: $this->dog?->customer?->user?->fullName`, `templateName: $this->template?->name`
- Frontend displays: `response.dogName`, `response.customerName`, `response.templateName`
