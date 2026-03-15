## ADDED Requirements

### Requirement: Resource provides dog name field
The AnamnesisResponseResource SHALL include a `dogName` field that contains the name of the associated dog for direct access in list views.

#### Scenario: Dog name included when dog relationship loaded
- **WHEN** an AnamnesisResponse is transformed to JSON via the resource
- **AND** the dog relationship is eager loaded
- **THEN** the response SHALL include a `dogName` field
- **AND** the value SHALL be the dog's name

#### Scenario: Dog name is null when relationship not loaded
- **WHEN** an AnamnesisResponse is transformed to JSON
- **AND** the dog relationship is not loaded
- **THEN** the response SHALL include a `dogName` field
- **AND** the value SHALL be null
- **AND** no error SHALL be thrown

#### Scenario: Dog name is null when dog is deleted
- **WHEN** an AnamnesisResponse references a deleted dog
- **AND** the dog relationship loads as null
- **THEN** the response SHALL include a `dogName` field with null value
- **AND** SHALL NOT throw an exception

### Requirement: Resource provides customer name field
The AnamnesisResponseResource SHALL include a `customerName` field that contains the full name of the dog's owner for direct access in list views.

#### Scenario: Customer name included when relationships loaded
- **WHEN** an AnamnesisResponse is transformed to JSON
- **AND** the dog.customer.user relationships are loaded
- **THEN** the response SHALL include a `customerName` field
- **AND** the value SHALL be the customer's user fullName (firstName + lastName)

#### Scenario: Customer name uses fullName accessor
- **WHEN** the customer relationship is loaded
- **THEN** the `customerName` field SHALL use the User model's `fullName` accessor
- **AND** SHALL NOT concatenate firstName and lastName manually

#### Scenario: Customer name is null when relationships not loaded
- **WHEN** an AnamnesisResponse is transformed to JSON
- **AND** dog or customer relationships are not loaded
- **THEN** the response SHALL include a `customerName` field
- **AND** the value SHALL be null
- **AND** no error SHALL be thrown

#### Scenario: Customer name traverses relationship chain safely
- **WHEN** accessing the customer name
- **THEN** the resource SHALL use null-safe navigation through dog → customer → user
- **AND** SHALL return null if any link in the chain is null

### Requirement: Resource provides template name field
The AnamnesisResponseResource SHALL include a `templateName` field that contains the name of the anamnesis template for direct access in list views.

#### Scenario: Template name included when template relationship loaded
- **WHEN** an AnamnesisResponse is transformed to JSON
- **AND** the template relationship is eager loaded
- **THEN** the response SHALL include a `templateName` field
- **AND** the value SHALL be the template's name

#### Scenario: Template name is null when relationship not loaded
- **WHEN** an AnamnesisResponse is transformed to JSON
- **AND** the template relationship is not loaded
- **THEN** the response SHALL include a `templateName` field
- **AND** the value SHALL be null
- **AND** no error SHALL be thrown

### Requirement: Resource maintains backward compatibility
The AnamnesisResponseResource SHALL continue to include nested relationship objects alongside the new denormalized display fields.

#### Scenario: Nested dog relationship still present
- **WHEN** an AnamnesisResponse is transformed to JSON
- **AND** the dog relationship is loaded
- **THEN** the response SHALL include both `dogName` field
- **AND** a nested `dog` object (DogResource)

#### Scenario: Nested template relationship still present
- **WHEN** an AnamnesisResponse is transformed to JSON
- **AND** the template relationship is loaded
- **THEN** the response SHALL include both `templateName` field
- **AND** a nested `template` object (AnamnesisTemplateResource)

#### Scenario: Existing fields remain unchanged
- **WHEN** the resource is updated with new fields
- **THEN** all existing fields SHALL remain present
- **AND** SHALL maintain their current data types and formats
- **AND** no existing API consumers SHALL break

### Requirement: Resource uses null-safe access patterns
The AnamnesisResponseResource SHALL use null-safe operators to prevent errors when relationships are not loaded or are null.

#### Scenario: Null-safe operator used for dog name
- **WHEN** accessing the dog's name
- **THEN** the resource SHALL use the nullsafe operator (`?->`)
- **AND** SHALL use null coalescing operator (`??`) with null as default

#### Scenario: Null-safe operator used for customer name
- **WHEN** accessing the customer name through relationship chain
- **THEN** the resource SHALL use nullsafe operators at each level
- **AND** SHALL return null if any relationship in the chain is null

#### Scenario: Null-safe operator used for template name
- **WHEN** accessing the template's name
- **THEN** the resource SHALL use the nullsafe operator (`?->`)
- **AND** SHALL use null coalescing operator (`??`) with null as default

### Requirement: Display fields are immediately available after creation
The denormalized display fields SHALL be present in the API response immediately after creating a new AnamnesisResponse, allowing the frontend to display the record without a page refresh.

#### Scenario: New response shows dog name in list
- **WHEN** a new AnamnesisResponse is created via API
- **AND** the response is returned to the frontend
- **THEN** the `dogName` field SHALL be populated with the selected dog's name
- **AND** the frontend table SHALL display the name in the Hund column

#### Scenario: New response shows customer name in list
- **WHEN** a new AnamnesisResponse is created via API
- **AND** the response is returned to the frontend
- **THEN** the `customerName` field SHALL be populated with the dog owner's full name
- **AND** the frontend table SHALL display the name in the Besitzer column

#### Scenario: New response shows template name in list
- **WHEN** a new AnamnesisResponse is created via API
- **AND** the response is returned to the frontend
- **THEN** the `templateName` field SHALL be populated with the template's name
- **AND** the frontend table SHALL display the name in the Vorlage column
