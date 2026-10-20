# Startup CRM — Implementation Plan

A private CRM for a one-person software company: track clients, the products they use, the rate each client negotiated, renewals, and invoices (with printing and PDF). It is built so a mobile app can be added later without rewriting the business logic.

---

## 1. Starting point and stack

The project is a fresh **Laravel 13 + Inertia v3 + React 19 + Tailwind 4** starter kit with Fortify auth (2FA and passkeys), Wayfinder, Pest and Larastan. We keep all of it.

| Concern            | Decision                                                                                                                                                                                                                                  |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Database           | **MySQL 8** in production (already installed). SQLite stays for tests.                                                                                                                                                                    |
| Money              | Store amounts as **integers in paise** (`unsignedBigInteger`). Never use floats. Use a `Money` cast and helper for formatting (₹1,25,000.00, Indian grouping via `intl`).                                                                 |
| Enums              | PHP backed enums for every status or type (`SubscriptionStatus`, `BillingCycle`, `InvoiceStatus`, `PaymentMethod`, …).                                                                                                                    |
| Business logic     | Put it in **Action classes** (`app/Actions/...`), never in controllers. Inertia controllers and the future mobile API both call the same Actions.                                                                                         |
| PDF                | `barryvdh/laravel-dompdf` (pure PHP, no Chromium needed on the server). The same Blade template also works as a browser print view. ✅ approved                                                                                           |
| UPI QR on invoice  | `bacon/bacon-qr-code`, which is already installed (Fortify uses it for 2FA), so no new dependency.                                                                                                                                        |
| Reminders          | Laravel **Scheduler** + **Notifications** (`database` + `mail` + `whatsapp` channels). A push channel can be added later for mobile.                                                                                                      |
| WhatsApp           | **Primary channel for clients.** Stage 1: click-to-chat (`wa.me`) links with a prefilled message and a signed PDF link. Stage 2: WhatsApp Business **Cloud API** (Meta) as a custom notification channel for automated messages. See §4a. |
| Email              | **SendGrid** through Laravel's built-in SMTP mailer (`smtp.sendgrid.net`, username `apikey`), so no extra package. It is the secondary channel and the one for your own daily digest.                                                     |
| Backups            | `spatie/laravel-backup` (DB + uploaded files, off-server). ✅ approved                                                                                                                                                                    |
| Mobile API (later) | `laravel/sanctum` tokens, `/api/v1/*`, API Resources. ✅ approved, installed in Phase 7                                                                                                                                                   |

**Security, first thing:** this is a single-owner app, so turn off `Features::registration()` in `config/fortify.php` once your account exists, and keep 2FA on.

---

## 2. Domain model

```
Client 1──* Contact
Client 1──* Subscription *──1 Product
Subscription 1──* RateRevision        (negotiation history)
Subscription 1──* SubscriptionPeriod  (renewal ledger, linked to Invoice)
Client 1──* Invoice 1──* InvoiceItem  (item may link to a Subscription)
Invoice 1──* Payment
Client 1──* Activity                  (calls, meetings, notes — sales timeline)
Client 1──* Message                   (WhatsApp / email log)
Reminder  (polymorphic: Subscription | Invoice | Client | none)
Expense   (optional budget module)
Setting   (company profile, invoice config)
```

### Tables

**clients**

- `id`, `name`, `type` (school, pharmacy, clinic, retail, other)
- `status` (lead, active, inactive, churned), `source` (referral, cold call, …)
- `email`, `phone`, `website`
- `billing_address`, `city`, `state`, `state_code`, `pincode`
- `gstin` (nullable), `pan` (nullable)
- `notes`, timestamps, soft deletes

**contacts**

- `client_id`, `name`, `designation` (Principal, Owner, Accountant…), `email`, `phone`, `is_primary`
- `whatsapp_number` (E.164, e.g. `+919876543210`, defaults to `phone`), `whatsapp_opt_in` (bool) + `whatsapp_opt_in_at`. Opt-in is required before sending automated messages through the Cloud API.
- `receives_invoices` (bool) marks which contacts invoices and reminders go to (e.g. the school accountant, not the principal).

**products**

- `id`, `name` (School ERP, Pharmacy Management…), `code` (SERP, PHM)
- `description`, `sac_code` (e.g. 998314), `default_tax_rate` (e.g. 18.00)
- `list_price` (nullable, only an indicative starting point, since there is no fixed price)
- `default_billing_cycle`, `unit_label` (nullable: "per student", "per branch", "per user")
- `is_active`

**subscriptions**: the core record of "client X uses product Y at rate Z"

- `client_id`, `product_id`
- `status` (trial, active, suspended, expired, cancelled)
- `billing_cycle` (one_time, monthly, quarterly, half_yearly, yearly)
- `pricing_model`: **`flat`** (one price for the whole cycle; today's promotional default) or **`per_unit`** (rate × units, e.g. per student; for later)
- `rate` (negotiated price per cycle, or per unit per cycle when `per_unit`, in paise)
- `quantity` (always 1 when `flat`; unit count when `per_unit`)
- `unit_count` (nullable, informational even on flat deals: record the student count now so you have the data when you move to per-student pricing)
- `is_promotional` (bool), `promo_ends_on` (nullable), `promo_note` ("Launch offer, flat ₹X till Mar 2027")
- `setup_fee` (one-time implementation or onboarding charge, nullable)
- `start_date`, `current_period_end` (= next renewal date), `grace_days` (default 15)
- `auto_renew` (bool), `renewal_notice_days` (default 30)
- `notes` (deal terms, e.g. "free customisation till March")

**subscription_periods**: renewal ledger, one row per billed period

- `subscription_id`, `period_start`, `period_end`, `invoice_id` (nullable)
- `status` (pending_payment, paid, overridden, waived)
- `override_reason` (nullable), `overridden_by`, `overridden_at`
- This is what moves the renewal date forward (see rule 5), and it gives a full history of every period.

**rate_revisions**: an audit trail of every negotiated rate

- `subscription_id`, `old_rate`, `new_rate`, `old_quantity`, `new_quantity`
- `effective_from`, `reason` (initial deal, renewal hike, discount, upsell), `created_at`
- Written automatically by the `ChangeSubscriptionRate` action. This answers "what did I offer them last year?".

**invoices**

- `number` (unique, e.g. `INV/2026-27/0001`, reset each Indian financial year, Apr–Mar)
- `client_id`, `status` (draft, sent, partially_paid, paid, overdue, cancelled)
- `issue_date`, `due_date`
- `client_snapshot` (JSON: name, address, GSTIN, state at issue time, so editing a client never changes past invoices)
- `place_of_supply_state_code`, `tax_type` (intra: CGST+SGST / inter: IGST / none)
- `subtotal`, `discount_total`, `taxable_total`, `cgst`, `sgst`, `igst`, `round_off`, `total`, `amount_paid`, `balance_due` (all paise)
- `notes`, `terms`, `sent_at`, `paid_at`

**invoice_items**

- `invoice_id`, `subscription_id` (nullable), `product_id` (nullable)
- `description`, `sac_code`, `period_start`, `period_end` (nullable)
- `quantity`, `unit_price`, `discount`, `tax_rate`, `taxable_amount`, `tax_amount`, `line_total`, `sort_order`

**payments**

- `invoice_id`, `client_id`, `amount`, `paid_on`
- `method` (bank_transfer, upi, cheque, cash, card), `reference` (UTR or cheque number), `tds_deducted` (nullable), `notes`

**activities**

- `client_id`, `type` (call, meeting, email, demo, note), `summary`, `body`, `happened_at`, `follow_up_on` (nullable; creates a Reminder)

**reminders**

- `remindable_type`/`remindable_id` (nullable morph), `type` (renewal, payment_followup, follow_up, general)
- `title`, `due_on`, `status` (pending, done, snoozed, dismissed), `completed_at`
- Unique index on (`remindable_type`, `remindable_id`, `type`, `due_on`) so the scheduler can safely run again without duplicating reminders.

**expenses** _(optional phase)_

- `category` (hosting, domains, software, marketing, travel, salary, other), `vendor`, `amount`, `spent_on`, `is_recurring`, `notes`, `receipt_path`

**messages**: communication log (every WhatsApp and email sent)

- `client_id`, `contact_id`, `channel` (whatsapp_link, whatsapp_api, email)
- `messageable_type`/`messageable_id` (Invoice, Subscription, Reminder…), `template` (invoice_issued, renewal_due, payment_reminder, payment_received)
- `to`, `body`, `status` (queued, sent, delivered, read, failed), `provider_message_id`, `error`, `sent_at`
- Click-to-chat sends are logged as `whatsapp_link` with status `sent` when you click, so the client timeline shows "Invoice sent on WhatsApp".

**company_profile** (single row, **all fields editable from the CRM UI**, nothing hard-coded in `.env` or config)

- Legal name, trade name, logo, address, state + state code, email, phone, website
- **GST:** `gstin`, `gst_registration_date`, `gst_state_code` (derived from the GSTIN), `pan` (derived from GSTIN characters 3–12 and cross-checked)
- **Udyam:** `udyam_number` (`UDYAM-XX-00-0000000`), `msme_category` (micro/small/medium), `msme_category_effective_from` (the category can change on annual reclassification), `udyam_certificate_path` (optional PDF upload), `show_msme_clause` (bool)
- Bank details, UPI ID (QR code on invoice), invoice prefix, default payment terms (days, ≤ 45; see rule 8), default notes and terms, authorised signatory name, signature image
- WhatsApp: WABA ID, phone-number ID, display number, system-user access token (**`encrypted` cast**, shown masked in the UI), webhook verify token, app secret (for signature checks), template names
- SendGrid from-address and from-name

**company_profile_changes**: audit log

- `field`, `old_value`, `new_value`, `changed_at`, `changed_by`
- Covers GSTIN, Udyam, PAN, bank and legal-name changes, so you can always show what was in effect on a given date.

**Seller snapshot on invoices:** the `invoices` table also gets `seller_snapshot` (JSON: legal name, address, GSTIN, Udyam no., MSME category, bank, UPI at finalise time). Editing your GST or Udyam details later never alters an already-issued invoice, the same way `client_snapshot` protects client details.

---

## 3. Key business rules

1. **Negotiated pricing.** `Product.list_price` only pre-fills the form. The real price lives on the `Subscription`. Any rate change goes through `ChangeSubscriptionRate`, which writes a `RateRevision`.
2. **Line amount.** `flat`: line = `rate` (quantity 1), and the description can still say "(up to 500 students)" from `unit_count`. `per_unit`: line = `rate × quantity`. Pro-rating is out of scope for v1. Edit the draft invoice line by hand if needed. Switching a client from flat to per-unit later is just a `ChangeSubscriptionRate` with reason "Promo ended", so the history stays intact.
3. **GST (registered).** Every invoice is a **Tax Invoice**. Seller state = place of supply → CGST + SGST (9% + 9%); otherwise IGST 18%. Print GSTIN of both parties, SAC per line, and the tax breakup. If the client has a GSTIN, it is B2B and the state code comes from the GSTIN's first two digits (validated). Without one, it is B2C and uses the client's state. All calculation lives in one `InvoiceCalculator` service, which gets thorough unit tests. Note: GST e-invoicing (IRN/QR) only applies above ₹5 crore turnover, so it is out of scope. The `invoices` table gets nullable `irn` and `ack_no` columns so it can be added later.
4. **Invoice numbering** is gapless and sequential per financial year. It is assigned only when a draft is **finalised** (inside a DB transaction with `lockForUpdate` on a counter row). Drafts have no number. A finalised invoice can't be edited, only cancelled; corrections are made by cancelling and re-issuing (credit notes can come later).
5. **Renewal advances on payment.**
    - `GenerateRenewalInvoice` creates a `SubscriptionPeriod` (`pending_payment`) for the next cycle, linked to a new draft invoice.
    - When the linked invoice becomes **fully paid**, the period becomes `paid` and `current_period_end` moves to `period_end`. A partial payment does **not** advance it.
    - **Manual override** (`OverrideRenewal` action, "Mark renewed" button): advances the period without full payment, for trusted clients, cheques in transit, or goodwill. It requires a reason and is logged with a timestamp. The period status becomes `overridden` (or `waived` for a free extension, with no invoice at all). Overrides can be **reverted** while the period is still unpaid.
    - Between `current_period_end` and `+ grace_days` the subscription shows as **Renewal pending**, not expired. After grace it becomes `expired` (unless overridden). The dashboard highlights clients "in grace", since these are your chasing list.
    - **Promo watch:** 30 days before `promo_ends_on` (or before the renewal of any `is_promotional` subscription), create a "Review pricing before renewal" reminder so the promotional rate doesn't silently roll over.
6. **Invoice status** is derived from payments: recording a payment updates `amount_paid` and `balance_due`, and sets the status to partially_paid or paid. Unpaid invoices past `due_date` become `overdue` (scheduled job). TDS deducted by the client counts towards settling the invoice. It is tracked separately so you can reconcile it with Form 26AS.
7. **Amount in words** uses Indian numbering ("One Lakh Twenty-Five Thousand Rupees Only"). Write a small dedicated helper with tests rather than relying on ICU spell-out.
8. **MSME / Udyam.** Print the Udyam number and this clause on every invoice: _"Supplier is registered under MSMED Act, 2006 (Udyam Reg. No. UDYAM-XX-00-0000000). Payment beyond 45 days attracts interest under Section 16 of the Act."_ Default payment terms are capped at 45 days. This matters to your buyers too: under Income Tax Section 43B(h), a business buyer can only deduct your bill as an expense once it is paid within the MSME time limit, which gives you useful leverage when chasing payment. The receivables ageing report flags invoices that have crossed 45 days.

---

## 4. Scheduled jobs (`routes/console.php`)

| Command                        | Frequency   | Does                                                                                                                                                                                                                                                      |
| ------------------------------ | ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `crm:renewal-reminders`        | daily 08:00 | Subscriptions where `current_period_end − renewal_notice_days ≤ today` → create a `renewal` Reminder for you, and (optionally, per client) generate the draft renewal invoice. Repeats at 7 days and on the day. Also handles the promo-review reminders. |
| `crm:mark-overdue`             | daily       | Invoices past due with a balance → `overdue`, plus a `payment_followup` reminder.                                                                                                                                                                         |
| `crm:expire-subscriptions`     | daily       | Past `current_period_end + grace_days`, period not paid or overridden → `expired`.                                                                                                                                                                        |
| `crm:daily-digest`             | daily 08:00 | Your morning summary (email via SendGrid; WhatsApp to your own number once Stage 2 is live): today's reminders, renewals due in 30 days, clients in grace, overdue invoices, follow-ups to send.                                                          |
| `crm:client-payment-reminders` | daily 10:00 | **Stage 2 only, off by default.** Automated WhatsApp templates to opted-in client contacts: renewal due in 15 days, invoice overdue. Turned on per client.                                                                                                |
| Backup                         | daily       | `spatie/laravel-backup`: DB + `storage/app` to off-server storage (Google Drive, S3 or Backblaze), with failure emails. Invoices are legal records, so this is not optional.                                                                              |

Server cron: `* * * * * cd /var/www/html/crm && php artisan schedule:run >> /dev/null 2>&1`

---

## 4a. WhatsApp & email delivery

Clients in India read WhatsApp. They don't reliably read email. So WhatsApp is the primary channel, built in two stages so you get value on day one without waiting for Meta's approval.

**Stage 1: Click-to-chat (Phase 3, no API, free)**

- On the invoice, subscription and reminder pages, a **"Send on WhatsApp"** button opens `https://wa.me/<number>?text=<prefilled message>` on your phone or WhatsApp Web, sent from your own business number.
- The message includes a **signed, expiring PDF link**: `URL::temporarySignedRoute('invoices.public-pdf', now()->addDays(30), …)`. The client taps it and downloads the invoice, with no login needed.
- Editable message templates live in settings (invoice issued, renewal due, payment reminder, payment received/thank-you), with placeholders such as `{client}`, `{invoice_no}`, `{amount}`, `{due_date}`, `{link}`, `{upi_link}`.
- Include a `upi://pay?pa=…&am=…&tn=INV…` link so the client can pay in one tap.
- Each click is logged in `messages` and shows on the client timeline.

**Stage 2: WhatsApp Business Cloud API (Phase 4b)**

_Revised 2026-10-21 after reviewing Meta's terms: use a **separate Meta app** for the CRM, owned by your business, as a "Direct Developer"._

- **Why not reuse the Tech Provider app:** Meta Platform Terms §5.b says a Tech Provider uses the Platform and Platform Data "on behalf of and at the direction of your Client … and not for your own purposes". Your production app is a Tech Provider app (its primary purpose is serving clients), so sending your own business's invoices and reminders through it is at best a grey area. §3.a.vii also requires re-submitting for review when an app's core functionality or data processing changes.
- **A separate app is easy:** Meta's app review docs say "if you are using the API for yourself as a Direct Developer, you do not need Advanced access or app review". Business apps get Standard access automatically for assets your business owns.
- **Other benefits:** its own webhook URL (no overrides needed, and template/account webhooks arrive too, which overrides cannot carry); a separate annual Data Use Checkup scope; and a restriction or token rotation on one app cannot stop the other.
- **Setup:** create a Business-type app in the same Business Portfolio, add the WhatsApp product, put the CRM's number on its own WABA, create a System User with access only to that WABA, generate a permanent token with `whatsapp_business_messaging` + `whatsapp_business_management`, and set the app's webhook to `https://<crm-domain>/api/webhooks/whatsapp`.
- Message templates are submitted under the CRM's WABA (utility category).
- The code sits behind a `WhatsAppGateway` interface (`MetaCloudGateway` implementation), with app ID, WABA ID, phone-number ID and token all coming from the editable `company_profile`.
- A custom `WhatsAppChannel` for Laravel Notifications lets the same `InvoiceIssued` / `RenewalDue` / `InvoiceOverdue` notifications go out via `['whatsapp', 'mail', 'database']`.
- Business-initiated messages need **Meta-approved templates** (utility category). The invoice PDF goes as a document attachment.
- Templates are created under the CRM's WABA (utility: `invoice_issued`, `renewal_due`, `payment_reminder`, `payment_received`). Utility messages are cheap in India; check current per-message pricing.
- A **webhook** endpoint (`/webhooks/whatsapp`, CSRF-exempt, verifies the `X-Hub-Signature-256` with your app secret) updates `messages.status` (delivered, read, failed), so you can see "Invoice read by client". Client replies are logged as inbound messages on the client timeline.
- Only sends to contacts with `whatsapp_opt_in = true`.

**Email (SendGrid)**

- Configured via `.env` only: `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.sendgrid.net`, `MAIL_PORT=587`, `MAIL_USERNAME=apikey`, `MAIL_PASSWORD=<api key>`. Verify your sending domain (SPF/DKIM) in SendGrid so mail doesn't land in spam.
- Used for: your daily digest, backup alerts, and an **"Email invoice"** button (PDF attached) for clients who want email, such as school accounts departments that need a formal copy.
- All outbound mail and WhatsApp API calls are **queued** (`database` queue driver; a supervisor or cron `queue:work` on the server).

---

## 5. Screens (Inertia pages under `resources/js/pages/`)

- **Dashboard**: KPI tiles (MRR/ARR from active subscriptions, outstanding receivables, collected this month and this FY), renewals due in the next 30/60/90 days, **clients in grace period**, promos ending soon, overdue invoices (with the >45-day MSME flag), today's reminders, recent activity.
- **Clients**: list with search and filters (type, status, product), create/edit form. Detail page with tabs: Overview · Subscriptions · Invoices · Payments · Activity timeline · Contacts. Header shows lifetime value and outstanding balance.
- **Products**: list and form. Detail page shows client count, revenue, and the range of rates negotiated (min/avg/max). This helps you settle on pricing later.
- **Subscriptions**: list filterable by product, status and renewal window. Form for adding a product to a client. Flat or per-unit toggle with a promo badge. "Change rate" dialog with a reason field. Rate history panel. **Periods ledger** (paid, pending, overridden). "Generate renewal invoice" button. **"Mark renewed (override)"** dialog with a required reason, and a revert option.
- **Invoices**: list (status chips, totals, filters by FY or month). Builder: pick a client, add lines from their subscriptions or free-text lines, with live totals. Actions: Finalise · Print · Download PDF · **Send on WhatsApp** · Email · Record payment · Cancel · Duplicate. A message log panel shows when and where the invoice was sent.
- **Payments**: list and record form (also available from the invoice page).
- **Reminders**: today, upcoming, and overdue lists. Mark done, snooze 1/3/7 days, add a manual reminder.
- **Expenses** _(optional)_: list and form, plus a monthly income vs expense summary.
- **Business Profile** (top-level sidebar item, plus a dashboard card showing GSTIN, Udyam no. and MSME category with an **Edit** button). Sections:
    - _Company_: legal and trade name, address, logo, signatory and signature.
    - _GST_: GSTIN with live validation (format + checksum). State and PAN auto-fill from the GSTIN. Registration date.
    - _Udyam / MSME_: Udyam number (format check), category, effective date, certificate upload, toggle for the 45-day clause on invoices.
    - _Bank & UPI_: account details, UPI ID with a live QR preview.
    - _Invoicing_: number prefix, payment terms, default notes and terms.
    - A live **invoice header preview** shows how the details will print. A change-history panel lists the audit log.
    - Changing GSTIN or Udyam shows a confirmation: "Applies to invoices finalised from now on; already-issued invoices keep their original details."
- **Settings → Messaging**: WhatsApp message templates (Stage 1 text), Cloud API credentials (Stage 2: WABA ID, phone-number ID, masked token), "Sync templates" button, "Set webhook override" button (calls `subscribed_apps` for you), SendGrid sender, test-send button.

Reuse the existing `ui/` components (card, dialog, select, badge, sheet, sidebar). Small additions needed: a data table, a date picker, a currency input, and a textarea.

---

## 5a. Approved UI design

The approved screen designs are on the design canvas (claude.ai artifact _CRM UI Design_). Key patterns to keep when building each phase:

- **Today inbox** on the dashboard: one row per thing that needs attention, with its action button inline, plus snooze and done.
- **⌘K command palette** and keyboard shortcuts (`N I` new invoice, `N C` new client, `P` record payment).
- **Slide-over sheets** for quick actions (record payment, change rate, mark renewed) instead of full page loads.
- **Client page**: header with contacts, subscription cards with period progress and rate history, activity timeline with a quick note box.
- Indigo accent, Geist font, tabular numbers; status colours (red, amber, green, blue) always paired with a text label.

## 6. Code layout

```
app/
  Enums/                ClientType, ClientStatus, SubscriptionStatus, BillingCycle,
                        InvoiceStatus, PaymentMethod, ReminderType, PricingModel,
                        PeriodStatus, MessageChannel, …
  Models/               Client, Contact, Product, Subscription, RateRevision,
                        SubscriptionPeriod, Invoice, InvoiceItem, Payment,
                        Activity, Reminder, Message, Expense, CompanyProfile,
                        CompanyProfileChange
  Casts/                MoneyCast
  Support/              Money, FinancialYear, AmountInWords, Gstin (validate + checksum + state code + PAN), UdyamNumber, UpiLink
  Services/             InvoiceCalculator, InvoiceNumberGenerator, InvoicePdf
  Actions/
    Subscriptions/      CreateSubscription, ChangeSubscriptionRate, AdvanceSubscriptionPeriod,
                        OverrideRenewal, RevertRenewalOverride
    Invoices/           CreateDraftInvoice, GenerateRenewalInvoice, FinaliseInvoice,
                        CancelInvoice, RecordPayment, SendInvoiceEmail
    Messaging/          BuildWhatsAppLink, RenderMessageTemplate, LogMessage
    Reminders/          CreateReminder, CompleteReminder, SnoozeReminder
  Http/
    Controllers/        ClientController, ProductController, SubscriptionController,
                        InvoiceController, InvoicePdfController, PaymentController,
                        ReminderController, DashboardController, PublicInvoicePdfController (signed),
                        WhatsAppWebhookController, BusinessProfileController,
                        Settings/MessagingController
    Requests/           Store/Update FormRequests per resource (shared later by the API)
    Resources/          JSON resources (used by Inertia props now, by the API later)
  Notifications/        RenewalDue, InvoiceIssued, InvoiceOverdue, PaymentReceived, DailyDigest
    Channels/           WhatsAppChannel (Stage 2)
  Services/WhatsApp/    WhatsAppGateway (interface), MetaCloudGateway
  Console/Commands/     SendRenewalReminders, MarkOverdueInvoices, ExpireSubscriptions,
                        SendDailyDigest, SendClientPaymentReminders
  Policies/             one per model (simple now, ready if you hire someone)
resources/views/pdf/    invoice.blade.php
```

---

## 7. Phased delivery

Each phase ships with Pest feature tests and passes `composer test` (Pint, Larastan, tests).

**Phase 0: Foundation** ✅ done (2026-10-20)

- MySQL (`crm`), `APP_NAME=CRM`, timezone `Asia/Kolkata`, Faker `en_IN`.
- Public registration removed; create accounts with `php artisan app:create-user`.
- `IndianState` (GST state codes) and `MsmeCategory` enums; `Gstin` (format + mod-36 checksum, state and PAN derivation); `ValidGstin` and `ValidUdyamNumber` rules.
- `company_profiles` + `company_profile_changes` (audit), Business Profile page (GST, Udyam, bank and UPI, invoicing defaults, logo and certificate uploads, live print preview, change history), dashboard "Get set up" card.
- Indigo/Geist theme from the approved design; sidebar shows the company name and logo.
- Deferred to the phase that first uses them: `Money`/`MoneyCast` and `FinancialYear` (Phase 2/3).

**Phase 1: Clients & Products** ✅ done (2026-10-20)

- `products`, `clients` (soft deletes), `contacts`, `activities` tables; `ClientType`, `ClientStatus`, `ActivityType`, `BillingCycle` enums; `Money` (rupees ↔ paise) and `PhoneNumber` (E.164, India by default) helpers.
- Products page: cards plus a slide-over form (optional starting price, usual billing, per-unit label, SAC and GST rate, "currently selling").
- Clients list: search (name, city, GSTIN, email, contact name or phone), status tabs with counts, type filter, sort by name or last contact, one-tap WhatsApp and call, pagination.
- Add client in a slide-over with an optional main contact. A valid GSTIN sets the state (B2B); no GSTIN means B2C.
- Client page: contacts (main contact, gets invoices, WhatsApp opt-in with consent date), details, archive, and an activity timeline with quick type chips, Ctrl+Enter to save and one-click follow-up dates.
- `ProductSeeder` seeds School ERP and Pharmacy Management (no price, since prices are negotiated).
- Not yet: follow-up dates are stored but only turn into reminders in Phase 4.

**Phase 2: Subscriptions & negotiated rates** ✅ done (2026-10-20)

- `subscriptions` (flat or per-unit, quantity, students on record, setup fee, promo flag/end date/note, start date, current period, renewal notice and grace days, auto-renew flag) and `rate_revisions`.
- Current period is worked out from the original start date (enter existing clients as-is) or set explicitly; month-end safe (31 Jan monthly → 28 Feb).
- `CreateSubscription` records the initial deal and turns a lead into an active client; `ChangeSubscriptionRate` records every change with a reason, rejects no-op changes and supports moving flat → per-unit.
- Client page: "Products in use" cards (amount + GST, ≈ per student, period progress, renewal countdown coloured by urgency, rate history chips), add/edit terms slide-over, change-rate dialog with % change preview and reason chips.
- Clients list: yearly value and next renewal columns. Products: client count, yearly total and deal range.
- Dashboard: yearly recurring revenue, renewals in the next 30 days, and a "Coming up" list of renewals and promo end dates.
- `subscription_periods` (the renewal ledger) arrives with invoicing in Phase 3/4.

**Phase 3: Invoicing** ✅ done (2026-10-20)

- `invoices`, `invoice_items`, `payments`, `invoice_sequences`, `messages`; dompdf installed.
- `InvoiceCalculator`: integer paise, GST per line rounded half-up, CGST+SGST within the state or IGST across states (place of supply from the client's GSTIN/state), total rounded to the rupee.
- Drafts (no number) → **Issue** assigns `INV/2026-27/0001` (gapless per Indian financial year, row-locked counter, restarts every April) and freezes seller and client snapshots. Issued invoices can't be edited or deleted, only cancelled (number stays used).
- Invoice editor: lines suggested from the client's subscriptions (the next period when a renewal is due, already-invoiced periods skipped, setup fees once), products or custom lines, live GST totals, due-date chips with an MSME 45-day warning.
- Invoice page: the real PDF as the preview, Issue, Record payment (TDS 10%/2% shortcuts, part payments, paid/partly paid status), Cancel with reason, payments and sent-message history.
- PDF: tax invoice with logo, GSTIN, Udyam + MSME clause, SAC tax summary, amount in words (Indian numbering), bank details and a UPI QR for the balance due; fonts subset (≈50 KB).
- Sending: WhatsApp click-to-chat with a 30-day signed PDF link (opens in a new tab, logged), and email with the PDF attached (queued; needs SendGrid in `.env`).
- Client page shows invoices and amount outstanding; subscription cards have "Create (renewal) invoice"; dashboard shows Outstanding and overdue invoices (flagging those past 45 days).
- Deferred to Phase 4: full payment advancing the subscription period (renewal ledger) and the manual override.

**Phase 4: Renewals & reminders** ✅ done (2026-10-20)

- `subscription_periods` renewal ledger: issuing an invoice registers its periods as awaiting payment; **full payment** advances the subscription (and reactivates an expired one); removing the payment or cancelling the invoice reverses it. Part payments never renew.
- **Mark renewed…** override with a required reason (or a free extension), linked to any later invoice for that period, undoable while it is the latest period.
- Grace period: after the period ends the subscription shows "renewal unpaid · grace until …"; `crm:daily-checks` expires it after the grace days.
- `reminders` (renewal, promo review, payment follow-up, follow-up, general) with unique keys so the daily sweep never duplicates; activity follow-up dates become reminders; renewal/payment reminders complete themselves when the renewal or payment happens.
- `crm:daily-checks` (07:00): renewal reminders in the notice window, draft renewal invoices for auto-renew subscriptions, promo reminders 30 days ahead, weekly reminders for overdue invoices, expiry after grace.
- `crm:daily-digest` (08:00): morning email (to-dos, clients in grace, overdue invoices, renewals in 30 days); skipped on quiet days.
- Reminders page (due / upcoming / done, quick add, snooze, done), red count on the header bell and sidebar, "Today" inbox on the dashboard, grace clients in "Coming up", invoice page says what paying renews.
- Backups: `spatie/laravel-backup` (database + `storage/app`), cleanup 01:30, backup 02:00, monitor 03:00; failures only are emailed; destination disks from `BACKUP_DISKS`.
- Requires the server cron: `* * * * * cd /var/www/html/crm && php artisan schedule:run >> /dev/null 2>&1`.

**Phase 4b: WhatsApp Cloud API (when ready, 2–3 days, plus Meta approval lead time)**

- Separate Direct Developer app in your Business Portfolio (no app review needed), its own WABA and number, a scoped System User token, and the app's webhook pointed at the CRM.
- Submit utility templates (approval usually takes minutes to hours).
- `WhatsAppGateway` + `WhatsAppChannel`, document (PDF) messages, delivery and read webhook, opt-in enforcement, per-client automation toggle.

**Phase 5: Reports** ✅ done (2026-10-21)

- Reports page per financial year (April–March, switchable): revenue (taxable value invoiced), GST charged, collected (payments + TDS), expenses (after claimable GST), profit.
- Monthly revenue vs expenses chart with a table view; revenue by product (custom lines separate); money owed by age since invoice date (0–30, 31–45, 46–90, 90+) with invoices past the MSME 45-day limit listed; TDS by client for Form 26AS matching; pricing insight (average/range per year, promos, effective per-student price from recorded counts).
- CSV downloads for any date range: invoice register, GSTR-1 B2B, B2CL (inter-state B2C over ₹1 lakh), B2CS (by state and rate), HSN/SAC summary (split B2B/B2C), payments with TDS, expenses with input GST. Ask your CA to confirm the columns against the current GST offline tool before uploading.

**Phase 6: Expenses** ✅ done (2026-10-21)

- Expenses with category, vendor, amount paid, claimable input GST (with 18/12/5% "included" helpers), method, reference, private receipt upload, notes.
- Month-by-month list with category filter, financial-year cost by category, and one-click re-adding of last month's monthly expenses.
- Feeds the profit figures and the expenses CSV.

**Phase 4b: WhatsApp Cloud API** ✅ done (2026-10-21)
- Settings › WhatsApp: WABA ID, phone number ID, number, System User token and app secret (encrypted, never sent to the browser), on/off switch, webhook URL + verify token to copy, template names per message type with the exact text to create in WhatsApp Manager, "Sync from Meta" (approval statuses, auto-fills matching names), "Test connection", and a `hello_world` test send.
- `WhatsAppGateway` → `MetaCloudGateway` (Graph API `v26.0`, override with `WHATSAPP_GRAPH_VERSION`); `WhatsAppMessenger` builds templates (invoice PDF attached as a document via the signed link) and only messages contacts who opted in; sends are queued (`SendWhatsAppMessage`), logged with status, and Meta rejections are recorded as failed with Meta's reason.
- Templates: `invoice_issued`, `payment_reminder` (both with the PDF), `renewal_due`, `payment_received`.
- Invoice page (and `POST /api/v1/invoices/{id}/whatsapp-send`): "Send invoice/reminder to … automatically", with sent / delivered / read / failed shown in the Sent list. Click-to-chat still works.
- Per-client "Automatic WhatsApp messages": renewal notice when the renewal reminder is created, weekly overdue reminders, and a thank-you when a payment is recorded, all to the billing contact if opted in.
- Webhook `GET/POST /api/webhooks/whatsapp`: verify-token handshake, `X-Hub-Signature-256` check with the app secret, processed in the background; delivery statuses (never move backwards), client replies logged on the timeline with a "Reply on WhatsApp" reminder (unknown numbers become a reminder too), template approval updates.

**Phase 7: Mobile app API** ✅ done (2026-10-21)

- Laravel Sanctum bearer tokens (one per device, 90-day expiry via `SANCTUM_TOKEN_EXPIRATION_MINUTES`, expired tokens pruned daily); two-factor accounts get a 5-minute challenge and sign in with an authenticator or recovery code; logins limited to 5/min per email+IP, API to 120/min per user.
- The API reuses the web app's FormRequests, Actions, Resources and the shared `DashboardData`, `ClientList`, `InvoiceList`, `SaveExpense` classes, so rules and numbers are identical on both.
- Push notifications: devices can register FCM tokens; sending needs a Firebase project (not set up yet).

API reference (`/api/v1`, JSON, `Authorization: Bearer <token>`; amounts in responses are integer paise, amounts in requests are rupee strings like `"48000.50"`; lists are paginated with `data`, `links`, `meta`; validation errors are 422 with `errors`):

| Area          | Endpoints                                                                                                                                                                                                                                                                         |
| ------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Auth          | `POST auth/token` {email, password, device_name} → token, or {two_factor, challenge}; `POST auth/two-factor` {challenge, code \| recovery_code, device_name}; `DELETE auth/token`; `GET me`                                                                                       |
| Today         | `GET dashboard`; `GET reports?year=2026-27`; `GET products`                                                                                                                                                                                                                       |
| Clients       | `GET/POST clients` (search, status, type, sort=recent); `GET/PATCH/DELETE clients/{id}` (show includes subscriptions, activities, invoices, outstanding); `POST clients/{id}/contacts`, `PATCH/DELETE contacts/{id}`; `POST clients/{id}/activities`, `DELETE activities/{id}`    |
| Subscriptions | `POST clients/{id}/subscriptions`; `PATCH/DELETE subscriptions/{id}`; `POST subscriptions/{id}/rate-changes`; `POST subscriptions/{id}/renewals` {reason, waive}; `DELETE renewals/{period}`                                                                                      |
| Invoices      | `GET clients/{id}/invoice-suggestions`; `GET/POST invoices` (filter, search, client_id); `GET/PATCH/DELETE invoices/{id}`; `POST invoices/{id}/finalise`, `/cancel`, `/whatsapp` {contact_id} → wa.me url, `/email`, `/payments`; `GET invoices/{id}/pdf`; `DELETE payments/{id}` |
| Reminders     | `GET reminders?view=due\|upcoming\|done`; `POST reminders`; `PATCH reminders/{id}` {done} or {snooze_days}; `DELETE reminders/{id}`                                                                                                                                               |
| Expenses      | `GET expenses?month=2026-10`; `POST expenses` (multipart for a receipt); `PATCH/DELETE expenses/{id}`; `GET expenses/{id}/receipt`                                                                                                                                                |
| Devices       | `POST devices` {token, platform: android\|ios}; `DELETE devices` {token}                                                                                                                                                                                                          |

**Later ideas:** Quotations or proposals (same item structure as invoices, then "convert to invoice"). Razorpay payment links on invoices (auto-marks paid, which then auto-renews). Credit notes. Auto-generate recurring invoices. A simple client portal.

---

## 8. Testing focus

- **Unit:** `InvoiceCalculator` (intra/inter-state, B2B/B2C, discounts, rounding), `Gstin` checksum + PAN derivation, `UdyamNumber` format, `UpiLink`, WhatsApp link builder, `InvoiceNumberGenerator` (FY rollover, no gaps or duplicates), `AmountInWords`, billing-cycle date advancement (including 31 Jan + 1 month).
- **Feature:** CRUD + validation + auth for each resource; finalised invoices can't be edited; payments update status; **full payment advances the period, partial does not; override advances it and requires a reason; revert works**; grace then expiry; signed PDF link rejects tampered or expired URLs; editing GSTIN or Udyam writes an audit row and doesn't change finalised invoices (seller snapshot); WhatsApp webhook rejects bad signatures; renewal command is idempotent (running twice creates no duplicate reminders); overdue marking.
- **PDF:** the endpoint returns `application/pdf` for a finalised invoice.

---

## 9. Decisions (confirmed 2026-10-20)

1. **GST:** registered, so always a Tax Invoice with CGST+SGST or IGST. Also **Udyam** registered, so the Udyam number and MSME 45-day clause appear on invoices.
2. **Pricing:** flat promotional rates now; the per-student (`per_unit`) model is built in for later. The student count is recorded now anyway.
3. **Renewal:** advances only on **full payment**, with a manual override (reason required, revertible).
4. **Messaging:** WhatsApp is primary (click-to-chat first, then Cloud API through a **separate Direct Developer app**, see §4a, revised 2026-10-21); email goes through SendGrid.
5. **Dependencies approved:** `barryvdh/laravel-dompdf`, `spatie/laravel-backup`, `laravel/sanctum`.
6. **GST, Udyam and all company details** are editable in the CRM (Business Profile page + dashboard card), with an audit log and snapshots on each invoice.

**Still open (not blocking, needed by Phase 3/4):**

- Your SAC code and default GST rate per product (typically 998314 / 18% for software services, but confirm with your CA).
- Invoice number format (default `INV/2026-27/0001`) and whether numbering needs to continue from invoices you've already issued.
- SendGrid account and verified sending domain.
- WhatsApp Stage 2: which number to use for the CRM, and whether to put it on your existing WABA or a new WABA in your portfolio (recommended: new WABA, same app).
