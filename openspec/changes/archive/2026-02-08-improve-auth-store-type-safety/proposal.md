## Why

The auth store currently uses `any` types in error handling and the registration function parameter. This reduces type safety, masks potential bugs, and makes the code harder to maintain. Proper TypeScript types will catch errors at compile time and improve IDE autocomplete.

## What Changes

- Replace `any` types with properly typed interfaces
- Add `ApiError` interface for consistent error handling across catch blocks
- Add `RegistrationData` interface for the register function parameter
- Improve type safety in all error handling scenarios
- **No runtime behavior changes - only compile-time type improvements**

## Capabilities

### Modified Capabilities
- `auth-store-type-safety`: Enhanced type definitions for authentication operations with proper error and registration data types

## Goals / Non-Goals

**Goals:**
- Improve compile-time type safety
- Maintain all existing runtime behavior

**Non-Goals:**
- Changing any error handling logic
- Modifying API contracts or data structures
- Altering component behavior

## Impact

- `frontend/src/stores/auth.ts`: Add new type interfaces, replace 4 instances of `any` with proper types (type-only changes)
