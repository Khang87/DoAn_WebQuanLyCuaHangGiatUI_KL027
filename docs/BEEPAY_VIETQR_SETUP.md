# MB Bank transfer and BeePay setup

## Server environment

Configure these values for the Vercel Preview deployment in Project Settings → Environment Variables. Do not commit real values to `.env` or send secrets in chat.

```text
VIETQR_ACCOUNT_NUMBER=<receiving account number>
VIETQR_ACCOUNT_NAME=<account holder name, uppercase without accents>
BEEPAY_WEBHOOK_SECRET=<secret generated for the BeePay webhook rule>
```

The application fixes the receiving bank to MB Bank (BIN `970422`) and the BeePay order prefix to `DH`. Set the account number and holder to the same MB Bank account connected to Pancake POS and reported through BeePay. Remove old `VIETQR_BANK_NAME`, `VIETQR_BANK_BIN`, `VIETQR_QUICKLINK_URL`, and `BEEPAY_PAYMENT_PREFIX` variables from the deployment; they are no longer used. Never use Pancake's static shop QR for an order payment: the application generates an order QR with the invoice amount and reference.

The public read-only app configuration is available at:

```text
https://<public-preview-domain>/api/v1/payment-config
```

It only returns the receiving bank details and payment reference prefix. It never returns the BeePay webhook secret.

## BeePay rule

After the public Preview deployment is live and accessible to BeePay, configure the BeePay webhook rule with:

- URL: `https://<public-preview-domain>/api/v1/webhooks/beepay`
- Event: incoming/credit transactions
- Bank: the MB Bank source connected to BeePay
- Order reference prefix: `DH`
- Payload: standard JSON
- Signature: HMAC SHA-256

Copy the secret generated for this rule into `BEEPAY_WEBHOOK_SECRET` in the same Preview environment, then redeploy. A deployment protected by Vercel Authentication cannot receive BeePay webhooks until the endpoint is made publicly accessible. The QR transfer content is `DH` followed by the eight-digit order ID, such as `DH00000075`. This is distinct from the display order number, which may have only four padded digits.

The endpoint accepts a payment only when the signature is valid, the receiving account matches configuration, the order selected bank transfer and is not cancelled, it has an unpaid invoice, and the incoming amount exactly matches that invoice. Repeated transaction notifications are idempotent. If the order is still being processed, its operational status stays unchanged while the invoice and payment are marked paid.

## Supabase realtime

Apply the app migration `20261013090000_enable_payment_realtime.sql` so the customer app receives payment, invoice, and order updates while the order detail is open.
