## Context

The auth store is a Pinia store managing authentication state. It currently uses TypeScript but has 4 `any` types that reduce type safety. The existing runtime behavior must be preserved while improving compile-time checking.

## Goals / Non-Goals

**Goals:**
- Improve compile-time type safety
- Maintain all existing runtime behavior
- Follow Axios error typing conventions

**Non-Goals:**
- Changing error handling logic
- Modifying API contracts
- Altering component behavior

## Decisions

### Decision 1: Use Axios Error Type

**Approach:** Use `unknown` in catch blocks and type guard for Axios errors

**Rationale:**
- TypeScript 4.4+ recommends `unknown` over `any` in catch blocks
- Axios provides `isAxiosError()` type guard for safe error handling
- Maintains existing `err.response?.data?.message` and `err.response?.status` patterns
- Zero runtime impact - only affects compile time

**Implementation:**
```typescript
catch (err: unknown) {
  if (axios.isAxiosError(err)) {
    // TypeScript now knows err.response exists
    error.value = err.response?.data?.message || 'Fallback message'
  }
}
```

### Decision 2: Registration Data Interface

**Approach:** Create `RegistrationData` interface matching existing User fields plus password

**Rationale:**
- Matches the fields actually used in registration
- Extends naturally from existing User interface structure
- Enables IDE autocomplete for registration calls
- No change to runtime validation (still handled by backend)

**Interface:**
```typescript
export interface RegistrationData {
  email: string
  password: string
  first_name: string
  last_name: string
  phone?: string
}
```

### Decision 3: Preserve Error Messages

**Approach:** Keep all existing fallback error messages unchanged

**Rationale:**
- Users see the same messages
- No UX changes
- Pure type improvement
