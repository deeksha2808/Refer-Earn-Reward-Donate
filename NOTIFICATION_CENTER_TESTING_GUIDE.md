# Notification Center Testing Guide

1. Sign in as a Business or Referrer and confirm the header bell is visible.
2. Trigger an existing notification event, such as creating an opportunity or submitting a referral.
3. Confirm the recipient receives the existing email and sees an unread in-app notification.
4. Confirm the bell badge increments and the dropdown shows the latest five notifications in newest-first order.
5. Open `notifications.php`, mark one notification read, then mark all as read; confirm the blue unread dot and badge update.
6. Delete a notification and confirm it disappears only for the signed-in account.
7. Generate at least 21 notifications to verify pagination.
8. Use temporary invalid SMTP credentials only in a safe environment; confirm the business action and in-app notification remain while the email delivery error is logged.
