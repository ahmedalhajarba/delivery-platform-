# ICTCRM Integration Guide

This document describes the call-center integration between this Laravel platform and ICTCRM.

## 1) Configuration

Set these environment variables in your runtime environment:

- `ICTCRM_ENABLED=true`
- `ICTCRM_BASE_URL=https://your-ictcrm-host`
- `ICTCRM_API_KEY=your_api_key`
- `ICTCRM_WEBHOOK_TOKEN=your_secure_webhook_token`
- `ICTCRM_REQUIRE_WEBHOOK_TOKEN=true`
- `ICTCRM_TIMEOUT=15`
- `ICTCRM_ENDPOINT_DIAL=/api/v1/calls/dial`
- `ICTCRM_ENDPOINT_TRANSFER=/api/v1/calls/transfer`

Configuration source file:

- `config/ictcrm.php`

## 2) Main Flows

### A) Incoming call screen-pop

ICTCRM sends webhook to:

- `POST /api/integrations/ictcrm/incoming-call`

Expected payload fields:

- `phone` or `caller_phone`
- `call_id` (optional)
- `direction` (`inbound` or `outbound`, optional)

Security:

- Send token in `X-ICTCRM-TOKEN` header (or `token` payload field).
- If `ICTCRM_REQUIRE_WEBHOOK_TOKEN=true` and token is not configured, endpoint returns `503`.
- If token is wrong, endpoint returns `401`.

Response includes:

- `customer_found`
- `customer` (if found)
- `ticket_create_url` for opening prefilled ticket form

### B) Admin screen-pop URL

Your call-center desktop can open:

- `GET /admin/call-center/screen-pop?phone=...&call_id=...&direction=inbound`

Behavior:

- If customer is found, it preselects customer and pre-fills ticket fields.
- Redirects to admin support ticket create page.

### C) Agent lookup API

- `POST /admin/call-center/lookup`

Payload:

- `phone` (required)
- `call_id` (optional)
- `direction` (optional)

Returns customer profile URL and prefilled ticket URL.

### D) Create ticket directly from call-center

- `POST /admin/call-center/create-ticket`

Payload:

- `description` (required)
- `user_id` or `phone`
- optional: `call_id`, `direction`, `subject`, `category`, `priority`, `assigned_to`

Returns:

- `ticket_id`, `ticket_number`, `ticket_url`, `customer_profile_url`

### E) Telephony controls from platform

Dial:

- `POST /admin/call-center/dial`
- payload: `extension`, `phone`

Transfer:

- `POST /admin/call-center/transfer`
- payload: `call_id`, `to_extension`

Both endpoints proxy to ICTCRM using `App\Services\CallCenter\IctcrmClient`.

## 3) Matching logic for caller phone

Caller matching is handled in `CallerLookupService` and is restricted to customer users only:

- `users.user_type = customer` OR user has role title `Customer`
- phone comparison supports exact and suffix match to handle local/international formats

## 4) Notes

- Admin auth + admin middleware protect all `/admin/call-center/*` endpoints.
- Ticket form receives hidden call metadata (`phone`, `call_id`, `direction`) and appends them to ticket notes.

## 5) Postman Ready Files

Added in `docs/`:

- `ictcrm.postman_collection.json`
- `ictcrm.postman_environment.json`

### Import Steps

1. Open Postman.
2. Import collection file: `docs/ictcrm.postman_collection.json`.
3. Import environment file: `docs/ictcrm.postman_environment.json`.
4. Select environment `ICTCRM Integration - Local`.
5. Fill variables:

- `baseUrl`
- `webhookToken`
- `callerPhone`
- `callId`
- `agentExtension`
- `dialTargetPhone`
- `transferToExtension`
- `adminCookie`
- `csrfToken`

### How to get adminCookie + csrfToken

Because admin call-center endpoints are web routes protected by `auth` and CSRF:

1. Login to admin panel from browser.
2. Open browser devtools storage/cookies.
3. Copy `laravel_session` and `XSRF-TOKEN` into `adminCookie` in this format:

`laravel_session=...; XSRF-TOKEN=...`

4. Put decoded CSRF token value in `csrfToken`.

### Suggested Test Order

1. `Webhook - Incoming Call`
2. `Admin - Screen Pop (JSON)`
3. `Admin - Lookup Caller`
4. `Admin - Create Ticket From Call`
5. `Admin - Dial`
6. `Admin - Transfer`

If `Dial` and `Transfer` fail, verify ICTCRM URL/API key/endpoints in environment config first.

---

# Customer Technical API (Orders, Tracking, Platform Services, Subscriptions)

This section documents the new professional customer integration API and admin technical-control panel.

## 1) Authentication Model

Customer technical API uses per-client API keys managed by admin.

- Header required on every request: `X-CLIENT-KEY: <plain-client-key>`
- Key is hashed and validated against `integration_api_clients`.
- Client must be `active` and linked to a customer user account.

Middleware chain:

- `integration.client` (auth + customer binding)
- `integration.log` (request/response audit logging)

## 2) API Base

All customer endpoints are under:

- `POST/GET /api/customer/v1/*`

## 3) Endpoints

### A) Orders

- `GET /api/customer/v1/orders`
	- List customer orders (paginated)
- `POST /api/customer/v1/orders`
	- Create order for the linked customer account
	- Supports `order_type=subscription` eligibility check

### B) Shipment Tracking

- `GET /api/customer/v1/tracking/{waybill}`
	- Track one waybill/reference/order-id for the same customer
- `POST /api/customer/v1/tracking/batch`
	- Track up to 20 waybills in one request

### C) Platform Services + Subscriptions Sales

- `GET /api/customer/v1/platform-services/catalog`
	- Returns available subscription plans, delivery speeds, and extra service settings
- `POST /api/customer/v1/platform-services/purchase`
	- Creates `service_purchases` record (reviewing status)
	- Supports subscription purchase and single-order service purchase
- `GET /api/customer/v1/platform-services/purchases`
	- Lists service purchases for the linked customer

### D) Customer Subscriptions

- `GET /api/customer/v1/subscriptions`
	- List user subscriptions (paginated)
- `GET /api/customer/v1/subscriptions/{subscription}`
	- Show one subscription details for linked customer

## 4) Admin Technical Control Panel

New admin area added:

- `GET /admin/technical-integrations`

Capabilities:

- Create API client and link it to a customer
- Generate/regenerate client keys
- Activate/deactivate clients
- Delete clients
- Monitor full API logs with filters:
	- by client
	- by HTTP status
	- by path

Related data tables:

- `integration_api_clients`
- `integration_api_logs`

## 5) Security and Operational Notes

- Client key is shown once on creation/regeneration and stored hashed only.
- API logs redact sensitive request fields (`password`, `token`, `api_key`, bank account details).
- Response body in logs is truncated to safe length for storage and UI readability.
- All logs include latency (`duration_ms`) and source IP for technical incident tracking.
