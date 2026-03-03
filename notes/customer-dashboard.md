# Customer Dashboard – Build Plan (ISP Resell)

## Goal
Create a logged-in customer dashboard that shows:
- Active package information after signup
- Billing allocation (recurring + once-off)
- Amount due now and next billing date

## Scope (Phase 1)
1. Add a custom plugin `starcast-customer-dashboard`
2. Add shortcode: `[starcast_customer_dashboard]`
3. Add admin allocation UI on user profile:
   - Assigned package ID
   - Package label
   - Provider
   - Monthly amount
   - Once-off amount
   - Outstanding balance
   - Next billing date
   - Account status
4. Show dashboard data to logged-in customer
5. Basic styling for clean cards

## Data model (phase 1)
Store per-user billing/package in user meta:
- `scd_package_post_id`
- `scd_package_label`
- `scd_provider`
- `scd_monthly_amount`
- `scd_once_off_amount`
- `scd_outstanding_amount`
- `scd_next_billing_date`
- `scd_account_status`

## Next phases
- Pull package data automatically from signup flow
- Create invoice records (custom table)
- Payment allocation engine (monthly cycle + prorata)
- Customer billing history + downloadable invoices
- Integrate payment gateway reconciliation

## Done now
- [x] Plan documented
- [x] Plugin scaffold
- [x] Profile allocation fields
- [x] Frontend dashboard shortcode
- [x] Basic dashboard styling

## Usage
1. Activate plugin: **Starcast Customer Dashboard**
2. Edit a user profile in wp-admin and allocate billing/package fields
3. Create page “Customer Dashboard” with shortcode:
   - `[starcast_customer_dashboard]`
4. Customer logs in and sees package + billing summary

## Test run (2026-03-03)
- Created test customer user:
  - username: `dashboardtest`
  - user id: `20`
- Allocated package + billing test values to user meta.
- Verified dashboard render through plugin runtime call (`dashboard_content()`):
  - Result: `OK` (dashboard HTML rendered)
  - Confirmed billing section contains "Current Balance".

### Notes
- Existing production plugin `wp-content/plugins/starcast-customer-dashboard.php` is active and drives WooCommerce My Account dashboard endpoints.
- New scaffold plugin folder exists but is currently inactive to avoid conflict.
