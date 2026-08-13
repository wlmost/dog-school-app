# Security Fix: Broken Access Control (IDOR) on the Payment endpoints

This document covers two related Broken Access Control (IDOR) fixes for the
`Payment` API — the same root cause (trainer-scoping was missing/incomplete
across `PaymentPolicy`/`PaymentController`), fixed in two passes on the
`fix/payment-index-missing-authorization-scope` branch:

1. [Part 1 — `GET /api/v1/payments` (list)](#part-1--get-apiv1payments-list)
2. [Part 2 — `GET`/`PUT /api/v1/payments/{id}` (single-record)](#part-2--get-put-apiv1paymentsid-single-record)

## Part 1 — `GET /api/v1/payments` (list)

### Problem

`PaymentController::index()`
(`backend/app/Http/Controllers/Api/PaymentController.php`) built its query
purely from optional request filters (`invoiceId`, `paymentMethod`,
`status`, `completedOnly`, `startDate`/`endDate`) and never scoped the
result set to the authenticated user (`$request->user()`).

Consequence: **any** authenticated user — including customers — could call
`GET /api/v1/payments` and see payment records (amounts, dates, payment
method) belonging to **other** customers, either in the unfiltered list or
by guessing/enumerating a foreign `invoiceId` via the `invoiceId` query
parameter. This is a classic Insecure Direct Object Reference / Broken
Access Control issue (OWASP API1:2023 / A01:2021).

`PaymentPolicy::viewAny()` returned `true` unconditionally and was not used
to enforce any row-level scoping — the policy only gated `store`, `update`,
`delete` and the single-record `show()` (`PaymentPolicy::view()`). At the
time of this fix, `view()`'s customer branch was correctly scoped to the
payment's invoice's customer, but its trainer branch was not — see
[Part 2](#part-2--get-put-apiv1paymentsid-single-record) below, which closes
that gap.

### Fix

`PaymentController::index()` now applies the same role-based query scoping
that `InvoiceController::index()` already uses for invoices, adapted to the
`Payment` → `Invoice` → `Customer` relationship chain:

- **Trainer**: only sees payments whose invoice belongs to a customer with
  `trainer_id === $user->id` (`whereHas('invoice.customer', ...)`).
- **Customer**: only sees payments whose invoice belongs to their own
  `Customer` record (resolved via `Customer::where('user_id', $user->id)`).
  If no `Customer` record exists for the user, the query is forced to
  return zero rows (`whereRaw('1 = 0')`), mirroring the equivalent
  `InvoiceController` behavior — no exception is thrown.
- **Admin**: no additional filter (sees everything), unchanged.

The existing optional query filters (`invoiceId`, `paymentMethod`,
`status`, `completedOnly`, `startDate`, `endDate`) are applied **in
addition to** the role scope, so a customer/trainer can no longer bypass
the scope by passing a foreign `invoiceId` — the `whereHas` scope and the
`invoiceId` filter are combined with `AND`, and a non-matching `invoiceId`
simply yields an empty result instead of exposing foreign data.

`PaymentPolicy::viewAny()` was left as `return true;` (every authenticated
user may call the endpoint at all — mirroring `InvoiceController::index()`,
which also has no explicit `viewAny` policy check), since the actual
row-level authorization now happens in the query itself, not in the
policy.

### Files changed

- `backend/app/Http/Controllers/Api/PaymentController.php` — added
  role-based query scoping in `index()`, added `use App\Models\Customer;`.
- `backend/tests/Feature/PaymentApiTest.php` — added regression tests.

### Tests added (`backend/tests/Feature/PaymentApiTest.php`)

- `customer cannot see other customers payments in unfiltered list` —
  asserts a customer's unfiltered `GET /api/v1/payments` only returns
  payments for their own invoice.
- `customer cannot access other customers payments by manipulating
  invoiceId filter` — asserts passing a foreign `invoiceId` returns an
  empty list instead of the foreign customer's payments.
- `customer without customer record sees no payments` — asserts a
  customer-role user without an associated `Customer` record gets an empty
  list (no exception).
- `trainer only sees payments for their assigned customers` — asserts a
  trainer only sees payments for invoices of customers with matching
  `trainer_id`, not payments belonging to another trainer's customers.
- `admin can still list all payments regardless of customer or trainer` —
  regression guard that the admin's unrestricted view still works after
  adding the scope.

The pre-existing test `customer can list payments for their invoices` was
kept unchanged; it covers the positive filter case but not isolation
against foreign payments, which is why the new tests above were added
alongside it.

### Verification

- `composer lint`, `composer stan`, `composer compat-check` — all green.
- `vendor/bin/pest --no-coverage --filter=PaymentApiTest` — 28 passed.
- Full suite `composer test` — 838 passed (2617 assertions), no
  regressions in other feature tests (e.g. `InvoiceApiTest`, PayPal flows).

## Part 2 — `GET`/`PUT /api/v1/payments/{id}` (single-record)

### Problem

`PaymentPolicy::view()` and `PaymentPolicy::update()` only checked
`$user->isAdminOrTrainer()` for the trainer role, without verifying that the
payment's customer was actually assigned to *that* trainer
(`Customer::trainer_id === $user->id`). This is the same Broken Access
Control class as Part 1, just on the single-record endpoints instead of the
list endpoint.

Consequence: Trainer A could call `GET /api/v1/payments/{id}` and
`PUT /api/v1/payments/{id}` for a payment belonging to a customer assigned
to Trainer B, and both view and modify (e.g. change `status`, mark
completed) that payment — a foreign-trainer IDOR that Part 1's list-endpoint
fix did not close, because `show()`/`update()` operate on a single record
resolved via route-model-binding rather than a filtered query.

`PaymentPolicy::delete()` was already admin-only (`$user->isAdmin()`);
trainers have no delete permission at all, so there is no trainer-scoping
question there and it was left unchanged.

### Fix

`PaymentPolicy::view()` and `PaymentPolicy::update()` now apply the same
`Payment` → `Invoice` → `Customer` trainer-scoping rule as the `index()`
fix from Part 1:

- **Admin**: unchanged, may view/update any payment.
- **Trainer**: may only view/update a payment if
  `$payment->invoice->customer->trainer_id === $user->id`.
- **Customer**: `view()` unchanged (own invoice's payments only, via
  `user_id`); `update()` unchanged — customers were never allowed to update
  payments and still are not (that policy question was explicitly out of
  scope for this fix).

### Files changed

- `backend/app/Policies/PaymentPolicy.php` — added trainer-scoping to
  `view()` and `update()`.
- `backend/tests/Feature/PaymentApiTest.php` — added regression tests;
  adjusted pre-existing trainer tests (`show`, `update`,
  `mark-completed`) that previously relied on unscoped trainer access to
  explicitly assign the trainer to the payment's customer, since they would
  otherwise now fail with 403.

### Tests added/adjusted (`backend/tests/Feature/PaymentApiTest.php`)

- `trainer can view payment for their assigned customer` (renamed from
  `trainer can view any payment`) — scopes the customer to the trainer and
  asserts the existing positive case still works.
- `trainer cannot view payment for other trainers customer` — new,
  asserts 403 for a payment belonging to another trainer's customer.
- `trainer can update payment for their assigned customer` (renamed from
  `trainer can update payment`) — same scoping adjustment.
- `trainer cannot update payment for other trainers customer` — new,
  asserts 403 and that the payment's `status` was not changed.
- `trainer can mark payment as completed`, `cannot mark already completed
  payment`, `marking payment completed updates invoice status if fully
  paid` — adjusted to scope the customer to the trainer, since
  `markAsCompleted()` also authorizes via `update`.
- `trainer cannot mark payment as completed for other trainers customer` —
  new, asserts 403 for a foreign trainer's customer.

### Verification

- `composer lint`, `composer stan`, `composer compat-check` — all green.
- `vendor/bin/pest --no-coverage --filter=PaymentApiTest` — 31 passed.
- Full suite `composer test` — 841 passed (2621 assertions), no
  regressions in other feature tests.
