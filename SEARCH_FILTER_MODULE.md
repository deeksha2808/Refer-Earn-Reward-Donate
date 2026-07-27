# Module 9: Global Search, Filtering & Sorting

## Implemented features

- **Business referrals**: server-side search by referral ID, customer, phone, or product; filters for campaign, product, status, and submitted date range; newest/oldest/highest-commission/lowest-commission sorting; 20-row pagination.
- **Opportunities**: business campaigns search by title and filter by category, active/inactive status, and product. Referrers can search active campaign titles and filter active listings by category, product, and location.
- **Wallet transactions**: referrers can search transaction descriptions/types/campaigns/products, filter by campaign/product/date, and sort newest or oldest. Results paginate at 20 rows.
- **Reports**: existing Module 8 filters are preserved in report tabs, pagination, and all exports. The export endpoint calls the same filtered query methods as the report screen.
- **Usability**: responsive filter panels, clear-filter links, and debounced instant search on high-frequency text search inputs.

## Database queries and performance

All filters use prepared PDO statements. Equality and date-range filters are bound as parameters; sort selections use allow-listed SQL fragments. Module 9 adds non-destructive indexes for:

- `customer_referrals (business_id, status, submitted_at)`
- `customer_referrals (business_id, opportunity_id, submitted_at)`
- `customer_referrals (referrer_id, submitted_at)`
- `wallet_transactions (wallet_id, created_at)`
- `referral_opportunities (business_id, status, created_at)`

Product filtering uses indexed ownership joins/`EXISTS` conditions. Text search intentionally uses `LIKE` for partial, user-friendly matches; it remains safely parameterized.

## Testing checklist

- [x] PHP syntax validation for modified business/referrer pages.
- [x] Search, filter, sort, and pagination query construction reviewed for parameter binding and allow-listed ordering.
- [x] Module 8 export consistency retained: exports obtain rows from the same report service methods.
- [x] Applied `database/migrations/20260720_search_filter_indexes.sql` and confirmed all five indexes in the local database.
- [ ] Browser test each search field with matching/non-matching values.
- [ ] Test combined filters, each sort direction, page navigation, and Clear Filters on populated business and referrer accounts.
- [ ] Compare an on-screen filtered report to CSV, XLSX, and PDF downloads.
