Stripe: URL https://YOUR-API/api/v1/billing/webhooks/stripe, events checkout.session.completed, invoice.paid, invoice.payment_failed, customer.subscription.updated, customer.subscription.deleted.
PayPal: URL https://YOUR-API/api/v1/billing/webhooks/paypal, events BILLING.SUBSCRIPTION.ACTIVATED, .CANCELLED, .SUSPENDED, .EXPIRED, .PAYMENT.FAILED and PAYMENT.SALE.COMPLETED. Put the webhook id it gives you in PAYPAL_WEBHOOK_ID.
Local testing: for Stripe use stripe listen --forward-to localhost:8000/api/v1/billing/webhooks/stripe. PayPal webhooks need a public URL, but the return-to-page step activates the plan without them.

The server cron needs php artisan schedule:run every minute (Laragon: run php artisan schedule:work while testing).