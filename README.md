# Personal Income & Expense Management System

A full-stack personal finance tracker built to master **Laravel REST API core concepts** alongside a lightweight **AlpineJS** frontend. This project practices real, production-style API authentication (Sanctum bearer tokens) rather than session-based shortcuts — the goal is to genuinely understand how token-based auth, validation, and per-user data isolation work under the hood.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend Framework | Laravel 13 (PHP 8.3+) |
| API Authentication | Laravel Sanctum 4 (Bearer Tokens) |
| Frontend | AlpineJS 3 (CDN) + Tailwind CSS 4 |
| HTTP Communication | Native `fetch()` |
| Database | SQLite (local) / any Laravel-supported DB |
| Testing | Pest 5 (feature tests) |

## Core Features

### Authentication
- API-based user registration (`POST /api/register` → 201 + verification email)
- Email verification via signed API link (`GET /api/email/verify/{id}/{hash}`)
- Login blocked until the email is verified (`POST /api/login` → 403 if unverified)
- Sanctum bearer token issued on successful login
- Logout revokes the current token (`POST /api/logout`)
- Resend verification email (`POST /api/email/verification-notification`)

### Finance Management
- Full CRUD for transactions with `type` = `income` / `expense`
- Full CRUD for categories (per-user unique names, optional `income` / `expense` type)
- Dashboard endpoint with totals, balance, per-category breakdown, recent entries
- Strict **per-user data isolation** — every query is scoped to `$request->user()`
- Filtering (type, category, date range) + pagination on the transaction list

### API Design Principles
- Proper HTTP status codes (`200`, `201`, `401`, `403`, `404`, `422`) and consistent JSON envelopes (`{ message, data }` / `{ user, token }`)
- Request validation on every endpoint with meaningful error messages
- No financial data is accessible without a valid, verified, authenticated token
- Guests are fully locked out of all finance endpoints

## Authentication Flow

This project intentionally uses **Sanctum bearer tokens** instead of cookie/session auth, to practice genuine stateless API authentication as used in real SPA/mobile-backed systems.

```
1. POST /api/register  → creates user, sends verification email (201)
2. User clicks the link in the email → GET /api/email/verify/{id}/{hash} (signed, no auth needed)
3. POST /api/login     → 403 "Please verify your email address first." if unverified
                       → 200 { message, user, token } if verified
4. Authorization: Bearer <token> → required on all protected routes
5. POST /api/logout    → deletes the current access token (200)
```

The email-verification link carries a cryptographic signature (`signed` middleware) plus an `id`/`hash` pair, so it is safe to open in a plain browser without a token. The resend endpoint accepts just `{ email }` (throttled 6/min), because an unverified user has no token yet.

## How the Bearer Token Works

1. **Issuing:** on verified login, `$user->createToken('personal-finance')->plainTextToken` creates a row in `personal_access_tokens` (token stored hashed) and returns the plain-text token exactly once.
2. **Storing:** the AlpineJS frontend saves it in `localStorage` (`pf_token`) on login and restores it in `init()` on page reload.
3. **Sending:** every `fetch()` call attaches it via `headers()`:
   ```js
   headers() {
       const h = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
       if (this.token) h['Authorization'] = 'Bearer ' + this.token;
       return h;
   }
   ```
4. **Verifying:** the `auth:sanctum` middleware hashes the presented token, looks it up in the DB, and resolves `$request->user()`. No match → `401`. The inner `verified` middleware adds → `403` for unverified users.
5. **Revoking:** logout runs `$request->user()->currentAccessToken()->delete()` — the DB row is gone, so the same token returns `401` afterwards.

## How AlpineJS Uses the Backend JSON

The whole frontend is one reactive component: `<div x-data="financeApp()" x-init="init()">` in `resources/views/app.blade.php`.

- `x-data` points at the `financeApp()` factory, which returns the single source of truth: `token`, `user`, `dashboard`, `transactions`, `categories`, `txForm`, `editingTxId`, `filters`, `pagination`, `message`, `errors`.
- `x-model` gives two-way binding (typing in an input updates the JS variable and vice versa).
- `x-show`, `x-text`, `@click`, `@submit.prevent`, and `<template x-for="...">` render UI from state — when `transactions` changes, the list re-renders with no page reload.
- A `handle(res)` helper parses every response as JSON and, on `401`, logs out locally (expired/revoked token).

**Read (GET):** `loadTransactions(page)` builds a query string from `filters` and calls `GET /api/transactions?…`. Laravel returns a paginator (`{ data: { data: [...], current_page, last_page, … } }`), so the code unwraps the double `data.data` into `this.transactions` plus pagination links.

**Create (POST → 201):** when `editingTxId` is `null`, `saveTransaction()` sends `POST /api/transactions` with `JSON.stringify(this.txForm)`. The controller validates and runs `$request->user()->transactions()->create($data)`, returning `201 { message, data }`. The frontend then resets the form and reloads the list + dashboard.

**Edit (PUT):** `editTransaction(t)` copies the row into the form and sets `editingTxId = t.id` (the button label flips to "Update"). Saving then sends `PUT /api/transactions/{id}`. Records are always loaded via `$request->user()->transactions()->findOrFail()`, so touching another user's id yields `404`.

**Delete (DELETE):** `deleteTransaction(id)` confirms, then sends `DELETE /api/transactions/{id}` and reloads the list + dashboard. Categories follow the identical pattern (`editingCatId` → `POST` vs `PUT /api/categories/{id}`).

**Validation errors:** failures return `422 { message, errors: { field: [...] } }`. `showErrors(data)` copies `errors` into state, and `<p x-show="errors.amount" x-text="errors.amount?.[0]">` displays the first message under the field.

## API Endpoints

| Method | Endpoint | Description | Auth |
|---|---|---|---|
| POST | `/api/register` | Register + send verification email | No |
| GET | `/api/email/verify/{id}/{hash}` | Verify email (signed URL) | Signed |
| POST | `/api/email/verification-notification` | Resend verification (`{email}`) | Throttled |
| POST | `/api/login` | Login, verified users only | No |
| POST | `/api/logout` | Revoke current token | Bearer |
| GET | `/api/user` | Current user | Bearer |
| GET | `/api/dashboard` | Totals, balance, by-category, recent | Bearer + verified |
| GET/POST | `/api/transactions` | List (filter + paginate) / create | Bearer + verified |
| GET/PUT/DELETE | `/api/transactions/{transaction}` | Show / update / delete own entry | Bearer + verified |
| GET/POST | `/api/categories` | List / create own category | Bearer + verified |
| GET/PUT/DELETE | `/api/categories/{category}` | Show / update / delete own category | Bearer + verified |

Transaction validation: `type ∈ {income,expense}`, `category` string ≤ 100, `amount` numeric 0.01–9999999999.99, `transaction_date` date ≤ today, `description` nullable ≤ 1000. Category validation: `name` string ≤ 100, unique per user; `type` nullable ∈ {income,expense}.

## Data Isolation & Security

- Every finance query is scoped to the authenticated user (`$request->user()->transactions()` / `->categories()`); cross-user access returns `404`, never data.
- Protected routes sit behind `auth:sanctum`, with finance routes additionally behind `verified`.
- `users` table enforces unique emails; `categories` enforces `unique(user_id, name)`; `transactions.user_id` has a cascading foreign key.
- Tests cover guest lockout (401), unverified lockout (403), per-user isolation, validation (422), and dashboard math.

## Project Structure

```
app/
├── Http/Controllers/Api/
│   ├── AuthController.php               # register, login, logout, user
│   ├── EmailVerificationController.php  # verify (signed, no auth), resend by email
│   ├── TransactionController.php        # income/expense CRUD + filters
│   ├── CategoryController.php           # category CRUD, per-user unique
│   └── DashboardController.php          # totals, balance, by-category, recent
├── Models/
│   ├── User.php                         # HasApiTokens, MustVerifyEmail
│   ├── Transaction.php                  # belongsTo User
│   └── Category.php                     # belongsTo User
database/
├── migrations/                          # users, personal_access_tokens, transactions, categories
└── factories/                           # User, Transaction, Category
routes/
├── api.php                              # all API routes
└── web.php                              # / → AlpineJS app view
resources/views/app.blade.php            # AlpineJS + fetch() frontend
tests/Feature/
├── AuthTest.php
├── TransactionTest.php
└── CategoryDashboardTest.php
```

## Getting Started

```bash
git clone https://github.com/CodeWithPranta/personal-finance.git
cd personal-finance
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build   # or npm run dev
php artisan serve
```

Configure mail in `.env` for verification emails (Mailtrap works for local dev):

```
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=...
MAIL_PASSWORD=...
```

Then open `http://localhost:8000`: register → click the email link → log in → manage transactions, categories, and dashboard totals.

## Testing

```bash
php artisan test --compact
```

14 Pest feature tests cover: guest lockout, register/verify/login/logout (including token revocation), transaction CRUD + isolation + validation, category CRUD + per-user uniqueness + isolation, and dashboard totals. Code style is enforced with `vendor/bin/pint --dirty`.

## License

Personal learning project. Feel free to fork and learn from it.
