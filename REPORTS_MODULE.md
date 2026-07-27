# Module 8: Reports & Export System

## Features implemented

Business users with a completed profile can open **Reports** from the dashboard or site navigation. The module provides four views:

- **Referral Report**: referral/customer/product/campaign/status/referrer, sale, commission rate and amount, submitted date, and completed date. It supports server-side search, column sorting, and 20-row pagination.
- **Commission Report**: completed referral commissions by referrer, product, campaign, sale amount, commission rate/earned amount, and completion date.
- **Campaign Performance Report**: total, accepted, rejected, completed, conversion rate, revenue, and commission paid per campaign.
- **Earnings Summary Report**: total revenue, commission paid, active campaigns, completed referrals, and pending referrals.

All reports share filters for submitted date range, campaign, product, referral status, referrer, and keyword search. The same filtered data drives the KPI cards and four responsive Chart.js charts:

1. Monthly referral trend
2. Monthly commission paid
3. Campaign performance
4. Referral status distribution

## Routes and service API

| Route/function | Purpose |
| --- | --- |
| `business/reports.php` | Authenticated business reporting dashboard. |
| `business/report_export.php` | Downloads the selected report as CSV, XLSX, or PDF. |
| `report_filters()` | Normalizes and validates selected filters. |
| `report_referrals()` / `report_referral_count()` | Filtered referral rows and pagination count. |
| `report_commissions()` | Filtered completed-commission rows. |
| `report_campaign_performance()` | Campaign aggregates and conversion rates. |
| `report_earnings_summary()` | KPI aggregates. |
| `report_chart_data()` | Datasets for the four charts. |

Exports accept the current report query string, for example:

`business/report_export.php?type=commissions&campaign_id=12&format=xlsx`

The export endpoint requires the `BUSINESS` role and a completed business profile. Every query constrains data with `customer_referrals.business_id = ?`; a business cannot report on another business's data.

## Queries used

The report service uses parameterized PDO statements. Its shared base query joins:

```sql
customer_referrals r
JOIN referral_opportunities o ON o.id = r.opportunity_id
JOIN users u ON u.id = r.referrer_id
WHERE r.business_id = ?
```

Optional parameterized predicates are added for `DATE(r.submitted_at)`, campaign ID, product snapshot, status, referrer ID, and keyword search. Campaign performance aggregates with `COUNT`, conditional `SUM`, `SUM(sale_amount)`, and `SUM(calculated_commission)`. Earnings KPIs use the same filtered relation. Charts group by month, status, or campaign.

The commission values use immutable referral snapshots (`commission_percentage`, `sale_amount`, and `calculated_commission`), not a campaign's mutable current product setup.

## Export functionality

- **CSV**: native `fputcsv` stream with the report's visible columns.
- **Excel**: a dependency-free, valid Office Open XML `.xlsx` workbook containing the exact report headers and rows.
- **PDF**: lightweight printable PDF report with title, generation timestamp, headers, and paginated rows.

All three formats call `report_export_rows()`, which uses exactly the same query methods as the on-screen report. This prevents a filtered screen and its exported file from diverging.

## Testing checklist

- [x] PHP syntax validation: `includes/reports.php`, report pages, shared header, and business dashboard.
- [x] Static whitespace/error check: `git diff --check`.
- [x] All four report query paths and all four chart datasets executed against the local database.
- [x] CSV fixture export checked for matching header and row content.
- [x] XLSX fixture export checked with `unzip -t`; recognized as Microsoft Excel 2007+.
- [x] PDF fixture export checked as a valid PDF 1.4 document.
- [ ] Browser smoke test while signed in as a business account with referral data.
- [ ] Verify non-empty filtered screen data against each downloaded export in a production-like dataset.

The local completed business used during the database query test currently has no referral rows, so the functional query paths returned empty report/chart datasets. This is expected data behavior, not a query failure; the export formats were additionally validated with a deterministic sample row.
