# Referral Status HTTP Debug Report

## Root cause

The browser was not posting to the referral endpoint. The form contains this
field:

```html
<input type="hidden" name="action" value="status">
```

That field shadows the form element's JavaScript `.action` property. As a
result, this expression converted the input element to a string:

```js
form.action || window.location.href
```

The actual browser request URL was therefore
`/business/[object%20HTMLInputElement]`, which the PHP built-in server returned
as an HTML `404 Not Found`. The PHP AJAX endpoint did not run, so this was not
a session, CSRF, JSON, BOM, PHP notice, warning, fatal error, include, or
authentication problem.

The client now uses `form.getAttribute('action') || window.location.href`, so a
form without an `action` attribute posts to the current referral URL.

## Captured failing browser request

- Request URL: `http://127.0.0.1:8000/business/[object%20HTMLInputElement]`
- Request method: `POST`
- Request headers set by the application:
  - `Accept: application/json`
  - `X-Requested-With: XMLHttpRequest`
  - `Content-Type: multipart/form-data; boundary=…` (added by the browser for
    `FormData`)
- Request payload:
  - `csrf_token=<authenticated-session token>`
  - `action=status`
  - `status=Processing`
- Response status: `404 Not Found`
- Response content type: `text/html; charset=UTF-8`
- Response headers:
  - `Connection: close`
  - `Content-Length: 569`
  - `Content-Type: text/html; charset=UTF-8`
  - `Date: Thu, 23 Jul 2026 17:29:08 GMT`
  - `Host: 127.0.0.1:8000`
- Raw response body:

```html
<!doctype html><html><head><title>404 Not Found</title><style> body { background-color: #fcfcfc; color: #333333; margin: 0; padding:0; } h1 { font-size: 1.5em; font-weight: normal; background-color: #9999cc; min-height:2em; line-height:2em; border-bottom: 1px inset black; margin: 0; } h1, p { padding-left: 10px; } code.url { background-color: #eeeeee; font-family:monospace; padding:0 2px;} </style> </head><body><h1>Not Found</h1><p>The requested resource <code class="url">/business/[object%20HTMLInputElement]</code> was not found on this server.</p></body></html>
```

The browser now prints the complete status code, response headers, and raw body
when a non-JSON response occurs. Session cookie values are deliberately not
recorded in this report.

## Captured successful HTTP response

- Request URL: `http://127.0.0.1:8000/business/referral_view.php?id=4`
- Request method: `POST`
- Request headers set by the application:
  - `Accept: application/json`
  - `X-Requested-With: XMLHttpRequest`
  - `Content-Type: multipart/form-data; boundary=…`
- Request payload:
  - `csrf_token=<authenticated-session token>`
  - `action=status`
  - `status=Under Review`
- Response status: `200 OK`
- Response content type: `application/json; charset=utf-8`
- Response headers:
  - `Cache-Control: no-store`
  - `Connection: close`
  - `Content-Type: application/json; charset=utf-8`
  - `Date: Thu, 23 Jul 2026 17:28:49 GMT`
  - `Expires: Thu, 19 Nov 1981 08:52:00 GMT`
  - `Host: 127.0.0.1:8000`
  - `Pragma: no-cache`
  - `X-Powered-By: PHP/8.3.6`
- Raw response body:

```json
{"success":true,"message":"Referral status updated to Under Review.","current_status":"Under Review","allowed_transitions":["Processing","Rejected"]}
```

## Files modified

- `business/referral_view.php`
- `Refer-Earn-Reward-Donate/business/referral_view.php` (the duplicate served
  tree was given the same URL fix)
- `STATUS_HTTP_DEBUG.md`

## Final browser verification

A temporary authenticated Business account and referral were created only for
this test. Firefox loaded the actual referral page, selected `Processing`, and
clicked **Update Status**. The page displayed:

```text
Referral status updated to Processing.
```

No red non-JSON error was shown. The temporary PHP error display and step
logging were removed after verification; the safer raw-response browser error
detail remains in place.
