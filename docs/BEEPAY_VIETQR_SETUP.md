# BeePay and VietQR setup

## Server environment

Configure these values in Vercel Project Settings → Environment Variables. Do not commit real values to `.env` or send secrets in chat.

```text
VIETQR_BANK_NAME=Sacombank
VIETQR_BANK_BIN=970403
VIETQR_ACCOUNT_NUMBER=<receiving account number>
VIETQR_ACCOUNT_NAME=<account holder name, uppercase without accents>
BEEPAY_PAYMENT_PREFIX=SKY
BEEPAY_WEBHOOK_SECRET=<secret generated for the BeePay webhook rule>
```

The public read-only app configuration is available at:

```text
https://sky-laundry.vercel.app/api/v1/payment-config
```

It only returns the receiving bank details and payment reference prefix. It never returns the BeePay webhook secret.

## BeePay rule

After the Vercel deployment is live, create one BeePay webhook rule with:

- URL: `https://sky-laundry.vercel.app/api/v1/webhooks/beepay`
- Event: incoming/credit transactions
- Bank: the Sacombank source connected to BeePay
- Order reference prefix: `SKY`
- Payload: standard JSON
- Signature: HMAC SHA-256

Copy the secret generated for this rule into `BEEPAY_WEBHOOK_SECRET` in Vercel, then redeploy. The QR transfer content is `SKY` followed by the eight-digit order ID, such as `SKY00000075`.

The endpoint accepts a payment only when the signature is valid, the receiving account matches configuration, the order selected bank transfer and is not cancelled, it has an unpaid invoice, and the incoming amount exactly matches that invoice. Repeated transaction notifications are idempotent. If the order is still being processed, its operational status stays unchanged while the invoice and payment are marked paid.

## Supabase realtime

Apply the app migration `20261013090000_enable_payment_realtime.sql` so the customer app receives payment, invoice, and order updates while the order detail is open.
