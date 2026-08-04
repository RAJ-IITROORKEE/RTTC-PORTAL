# Razorpay Test and Live Setup

## Repository Endpoints

The webhook URL currently configured in the Razorpay dashboard is supported:

```text
https://rttcadmission.in/webhook/payment.php
```

It forwards to the payment webhook handler. The canonical repository endpoint is also available at:

```text
https://rttcadmission.in/api/payment-webhook.php
```

Use one URL consistently. Do not create a second active webhook for the same mode unless a separate environment needs it.

## Environment Variables

Put these values in the local or server `.env` file. Never commit `.env` or put the API secret in JavaScript.

```env
RAZORPAY_KEY_ID=rzp_test_xxxxxxxxxxxxx
RAZORPAY_KEY_SECRET=xxxxxxxxxxxxxxxx
RAZORPAY_WEBHOOK_SECRET=use-a-separate-random-webhook-secret
RAZORPAY_AMOUNT=50000
```

`RAZORPAY_KEY_ID` and `RAZORPAY_KEY_SECRET` come from Razorpay API Keys. `RAZORPAY_WEBHOOK_SECRET` is the secret entered while creating the webhook; it is not the API key secret.

The local `.env` in this repository is ignored by Git. Add the test webhook secret there before testing the webhook endpoint.

## Test Mode Setup

1. In the Razorpay Dashboard, switch to **Test Mode**.
2. Open **Account & Settings -> API Keys** and generate or copy the Test Key ID and Key Secret.
3. Set `RAZORPAY_KEY_ID` and `RAZORPAY_KEY_SECRET` in the local `.env`.
4. Open **Account & Settings -> Webhooks**.
5. Keep one active Test webhook for `https://rttcadmission.in/webhook/payment.php`. Disable or delete the duplicate inactive entry shown in the dashboard.
6. Edit the active webhook and set a strong webhook secret. Copy that exact value to `RAZORPAY_WEBHOOK_SECRET` on the server receiving the test event.
7. Enable these payment events:
   - `payment.authorized` (optional for monitoring)
   - `payment.captured` (required for successful application completion)
   - `payment.failed` (required for failure status)
8. Set an alert email and save the webhook.

The webhook URL must be public HTTPS. Razorpay cannot deliver directly to an XAMPP `localhost` URL. For local-only webhook testing, expose the local server through a currently accepted HTTPS tunnel, or use a public staging deployment. Razorpay's current localhost testing documentation recommends zrok and notes that several common tunnel domains are blocked.

## Test Flow

1. Confirm the application database has the `payment` table with `pending`, `success`, and `failed` statuses.
2. Log in as a test applicant and complete the document step.
3. Open the payment page. The server creates a Razorpay order and stores it as `pending` before Checkout opens.
4. Complete a Test Mode payment. For UPI simulation, Razorpay documents `success@razorpay` and `failure@razorpay` test IDs.
   If Razorpay convenience fees are configured as customer-borne, the Checkout total can be higher than the application fee. The integration verifies that the captured total minus Razorpay's signed `fee` equals the original order amount.
5. Confirm the browser callback reaches `/api/payment-process.php` and redirects to confirmation.
6. In the database, confirm the order changed from `pending` to `success`, the payment ID is stored, and `registration_progress.current_step` is `4`.
7. In Razorpay Dashboard -> Webhooks -> View Logs, confirm the event received HTTP `200`.
8. Repeat with a failed test payment and confirm the order becomes `failed` without moving registration progress to step 4.
9. Send the same captured event more than once and confirm it remains successful and does not create another payment row.

The webhook must receive the original raw request body for HMAC verification. The handler rejects invalid signatures and never marks an unknown order successful.

## Test Payment Reset

The Admin -> Payments page has a trash button beside each successful payment. It deletes the local payment row, writes an immutable snapshot to `payment_deletion_log`, and resets the applicant to registration step 3 when no other successful payment remains. This is for test cleanup only; it does not refund or cancel the Razorpay transaction.

Before using the button on an existing deployment, apply `database/add_payment_deletion_log.sql` to that deployment's database. The phpMyAdmin database must be the same database configured by the deployed server `.env`; an empty local `payment` table will not change records displayed by the production admin dashboard.

## Live Cutover

1. Finish Test Mode success, failure, duplicate-event, and webhook-log checks.
2. Back up the production database and `.env` securely.
3. In the Razorpay Dashboard, switch to **Live Mode** and generate a new Live Key ID and Key Secret. Test and Live keys are different.
4. In Live Mode -> Webhooks, create or edit the Live webhook at the same HTTPS URL. Use a separate Live webhook secret and put that value in the production `.env`.
5. Replace only the production values:

   ```env
   APP_ENV=production
   APP_URL=https://rttcadmission.in/
   RAZORPAY_KEY_ID=rzp_live_xxxxxxxxxxxxx
   RAZORPAY_KEY_SECRET=xxxxxxxxxxxxxxxx
   RAZORPAY_WEBHOOK_SECRET=live-webhook-secret
   RAZORPAY_AMOUNT=50000
   ```

6. Confirm Razorpay payment capture is automatic, or implement capture before accepting applications.
7. Deploy the code, confirm the production `.env` is outside Git, and restart PHP/FPM or clear any opcode cache if the host requires it.
8. Make one controlled real payment only after verifying the amount and recipient account. Confirm the payment is captured, the webhook log is `200`, and the application confirmation is generated.

Never paste API keys or webhook secrets into Git, browser code, support tickets, or chat.
