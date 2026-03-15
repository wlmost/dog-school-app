## 1. Add Type Definitions

- [x] 1.1 Import `axios` and `AxiosError` type from axios package
- [x] 1.2 Add `RegistrationData` interface after existing interfaces

## 2. Update Error Handling

- [x] 2.1 Replace `err: any` with `err: unknown` in login function
- [x] 2.2 Replace `err: any` with `err: unknown` in logout function  
- [x] 2.3 Replace `err: any` with `err: unknown` in register function
- [x] 2.4 Add type guards using `axios.isAxiosError()` where needed

## 3. Update Register Function

- [x] 3.1 Replace `userData: any` parameter with `userData: RegistrationData`

## 4. Verify

- [x] 4.1 Check TypeScript compilation has no errors
- [x] 4.2 Verify no runtime behavior changes
