# Status Update JSON Response Fix

## Root cause

The browser-side request expected JSON, but the PHP endpoint identified AJAX only by the `Accept: application/json` header. If that header was absent or altered, the same POST was handled as a normal form submission and returned a redirect instead of JSON.

The captured non-AJAX response was:

- Status: `302 Found`
- `Location: /business/referral_view.php?id=3`
- `Content-type: text/html; charset=UTF-8`
- Body: empty (a browser following the redirect receives the HTML page)

That is why the frontend showed the exact error: `The status service returned an invalid response. Please sign in again and retry.`

## Files modified

- `business/referral_view.php`
- `STATUS_UPDATE_FIX.md`

## Fix applied

- The JavaScript request now sends both `Accept: application/json` and `X-Requested-With: XMLHttpRequest`.
- The endpoint accepts either marker when deciding to return JSON.
- Existing output buffering remains in place to discard warnings/notices before the JSON response.
- A fatal-error shutdown fallback now returns a `500` JSON error for an identified AJAX request instead of leaking HTML.
- Authentication/session failures continue returning JSON: `401` with `{"success":false,"message":"Your session has expired. Please sign in again."}`.

## Browser-network equivalent verification

An authenticated Update Status request was captured against the running local server.

- Status: `200 OK`
- Headers: `Content-Type: application/json; charset=utf-8`, `Cache-Control: no-store`
- Actual body:

```json
{
  "success": true,
  "message": "Referral status updated to Under Review.",
  "current_status": "Under Review",
  "allowed_transitions": ["Processing", "Rejected"]
}
```

The database value was verified as `Under Review`. Temporary trace data was removed after testing.
