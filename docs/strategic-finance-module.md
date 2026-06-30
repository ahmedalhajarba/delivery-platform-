# Strategic Finance Module (Phase 1)

## Goal
An independent admin module to manage:
1. Legacy obligations/debts and settlement scheduling.
2. Investor management and 3-year investment rounds planning.
3. Legal case management: lawyers, case assignments, case progress tracking, and closure.

This module is integrated with current login/admin panel, while using a dedicated database connection.

## Dedicated Database Connection
Add these environment variables in your `.env`:

```env
STRATEGIC_FINANCE_DB_HOST=127.0.0.1
STRATEGIC_FINANCE_DB_PORT=3306
STRATEGIC_FINANCE_DB_DATABASE=newp2_strategic_finance
STRATEGIC_FINANCE_DB_USERNAME=root
STRATEGIC_FINANCE_DB_PASSWORD=
STRATEGIC_FINANCE_DB_SOCKET=
```

Optional single URL form:

```env
STRATEGIC_FINANCE_DATABASE_URL=mysql://user:pass@127.0.0.1:3306/newp2_strategic_finance
```

## Migration
Run the strategic module tables migration:

```bash
php artisan migrate --database=strategic_finance --path=database/migrations/2026_04_15_120000_create_strategic_finance_foundation_tables.php
php artisan migrate --database=strategic_finance --path=database/migrations/2026_04_15_123000_create_strategic_finance_legal_tables.php
php artisan migrate --database=strategic_finance --path=database/migrations/2026_04_15_124000_create_strategic_finance_legal_documents_table.php
php artisan db:seed --class=StrategicFinanceObligationsSeeder
```

## Login & Access Links
After login as admin user:

- Login page: `/login`
- Strategic Finance Dashboard: `/admin/strategic-finance`
- Legal Cases & Lawyers: `/admin/strategic-finance/legal`

## New Admin Entry
- Route: `/admin/strategic-finance`
- Name: `admin.strategic-finance.index`
- Sidebar menu: `Obligations & Investment Hub`

## Legal Module Entry
- Route: `/admin/strategic-finance/legal`
- Name: `admin.strategic-finance.legal.index`
- Sidebar menu: `Legal Cases & Lawyers`

## Initial Data Model
### Obligations domain
- `sf_counterparties`: suppliers, employees, clients, partners, government entities.
- `sf_obligations`: each obligation record with remaining balance and schedule.
- `sf_settlements`: payment/waiver/reconciliation records with partial/full closure.

### Investment domain
- `sf_investment_rounds`: valuation, offered equity, target raise, timeline.
- `sf_investment_commitments`: investors, contract type, committed and received capital.

### Legal domain
- `sf_lawyers`: lawyers and their key contact/license details.
- `sf_legal_cases`: legal cases raised against the partner with priority and lifecycle status.
- `sf_legal_case_assignments`: lawyer assignment history per case.
- `sf_legal_case_updates`: chronological updates, stages, and next actions.
- `sf_legal_case_documents`: legal attachments for case-level and update-level archiving.

## Next Build Steps (Phase 2)
1. CRUD screens for obligations, counterparties, and settlements.
2. Automatic outstanding balance recalculation after every settlement.
3. Smart prioritization engine for obligations (aging, category, risk, legal urgency).
4. Investment scenario simulator (SAR/USD, dilution, exits, LP/GP allocation).
5. Investor-ready 3-year projection report (PDF + dashboard charts).
6. Court calendar reminders and SLA alerts for legal next-action dates.
7. Legal document attachments per case and per update.
