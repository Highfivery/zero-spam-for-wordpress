# WooCommerce card-testing defence: design

Status: **approved and built** (Ben, 2026-11-22: "EVERYTHING should be done and included in 6.0.2"). Section 9 lists where the build differs from this draft.
Issue: [#396](https://github.com/Highfivery/zero-spam-for-wordpress/issues/396).
Target: 6.0.2 (Ben, 2026-11-22).

## 1. The problem

Card testers run stolen card numbers through a store's checkout in small
orders, to learn which cards work. The store pays a fee for every declined
charge, can be flagged or dropped by its payment processor, and its order list
fills with failed orders. WooCommerce now tells stores to add a CAPTCHA. The
person who opened #396 wants protection without one.

What Zero Spam does today, checked in the code on 2026-11-22:

- "Protect checkout" (`verify_woocommerce_checkout`, recommended on) hooks the
  classic, shortcode checkout only: `woocommerce_checkout_process` adds the
  honeypot field, checks the blocked email domains and runs the David Walsh
  filter.
- **The Checkout block and the Store API are not protected at all.** The block
  has been WooCommerce's default checkout since 8.3, and it goes through `POST
  /wc/store/v1/checkout`, which runs none of the classic hooks. Card-testing
  bots post straight to that route, also on stores that use the classic
  checkout, because the Store API is part of WooCommerce core either way.
- Visitor checks (blocked IPs and locations, Zero Spam and Stop Forum Spam
  reputation) run on `template_redirect` and the login, and never on a REST
  request, so the Store API skips them as well.
- A blocked classic checkout logs the whole `$_POST` as `submission_data`.
  Payment gateways that post card fields to the store, instead of turning them
  into a token in the browser, would put card numbers into the site's own
  database. What Zero Spam shares with zerospam.org is limited to the type, IP,
  email and names, so the shared reports carry no card data.

## 2. Who it is for, and why it matters for the business

- **Every WooCommerce store using the free plugin.** Card testing costs a store
  real money, and stopping it is a concrete reason to install Zero Spam and to
  leave a review.
- **Enhanced Protection** (value-led, AGENTS.md / project rules). The defence works
  without a licence. With Enhanced Protection, the Zero Spam network's reputation
  of the address is also checked at checkout, so known card-testing IPs seen on
  other stores are stopped before their first attempt. The WooCommerce card shows
  that benefit once, in context ("N checkout attempts this month came from
  addresses the Zero Spam network had already flagged"), with one upgrade link.
  No banner, no nagging.

## 3. Design

Five layers, each useful on its own. All of them run only when "Protect checkout"
is on, so one setting still means "Zero Spam guards my checkout".

### 3.1 Cover the Checkout block and the Store API

- Validate on `woocommerce_store_api_checkout_update_order_from_request`, which
  runs on the Store API's `POST /checkout` before payment (WC 7.2+). A refusal
  throws `RouteException` (`zerospam_checkout_refused`, 403) with the
  "Checkout flagged message", which the block shows like any checkout error.
- The classic hook stays. The two paths share one
  `Checkout_Guard::check( array $order_fields, string $path ): ?string` that
  returns the failed reason or `null`, so both checkouts apply the same checks.
- Run the visitor checks that `Access` runs on page views on these requests too:
  blocked IPs and locations, network-wide blocks, and the reputation checks that
  are switched on (Zero Spam with Enhanced Protection, Stop Forum Spam on the
  billing email and IP). This reuses the `zerospam_access_checks` filter chain,
  through `Access::intercept_termination()`, without new lookups.

### 3.2 A browser token for the Checkout block

The honeypot and David Walsh key cannot reach the block, which renders in React.
A small script, enqueued only where the Checkout block is, sets
`extensions['zero-spam']` with `setExtensionData` from `wc/store/checkout`. It
holds a short-lived token issued for that page view: an HMAC of the session, the
time and `wp_salt()`, valid for two hours. The Store API extension schema is
registered with `woocommerce_store_api_register_endpoint_data`.

- **A missing or wrong token on its own does not refuse an order.** Express
  payment buttons (Apple Pay or Google Pay through Stripe, PayPal) can place
  orders through the Store API from product and cart pages, where the checkout
  script never ran. Refusing every request without a token would turn away real
  buyers.
- Instead, a checkout without a valid token is counted as **unverified**. Section
  3.3 counts unverified attempts against a tighter limit, and while a store-wide
  surge is under way (section 3.4) unverified checkouts are refused.

### 3.3 Limit failed payments per visitor

This is the defence that actually stops card testing: a tester has to fail
many times to learn anything.

- Every failed payment is counted:
  - `woocommerce_order_status_failed` (every gateway that marks the order failed);
  - `woocommerce_store_api_checkout_order_processed` followed by a payment
    result of `failure`;
  - the classic checkout's failed `process_payment` result.

  Each failure is recorded against the visitor's IP (`User::get_ip()`, which
  trusts proxies only as configured) and against the billing email.
- **The limit:** 3 failed payments in 60 minutes from one IP or one billing
  email (2 when the attempts are unverified). After that, further checkouts from
  that IP are refused for 60 minutes.
- **Refusals are a real block** in `wpzerospam_blocked`, with an end time and the
  reason "Card testing: 3 failed payments in 60 minutes". It shows in Rules →
  Blocked IPs, where an administrator can lift it at once. While it lasts, the
  address is blocked at checkout only, not across the whole site: the block
  record carries `key_type` `checkout`. That needs a DB version bump and a
  migration, and `Access::check_blocked()` ignores `checkout` blocks.
- **Who is exempt:**
  - logged-in customers with at least one paid order;
  - addresses on the IP Allowlist or the no-lockout list.

  A real customer whose card is declined three times in an hour is rare. The
  refusal message tells them to contact the store, and the block ends by itself.
- **Counters** live in one non-autoloaded option per site, pruned on write. They
  hold a hashed IP or email, the count and the first time, for 60 minutes. That
  makes no queries on page views, and nothing is stored in clear.

### 3.4 Store-wide surge

Testers rotate IPs, so a per-IP limit alone lets a distributed attack through.

- **The trigger:** 10 failed payments in 10 minutes across the whole store
  (adjustable).
- **What happens next:** surge mode turns on for 30 minutes.
  - Unverified checkouts (section 3.2) are refused.
  - WooCommerce's own Store API checkout rate limit is switched on through
    `woocommerce_store_api_rate_limit_options`, at 3 requests per 60 seconds per
    visitor (only for those 30 minutes, and only for `POST /checkout`). Zero
    Spam's IP is used through the fingerprint filter of WC 9.8+.
  - The site administrator gets one email through `Includes\Email`, with the
    numbers and what was switched on, and a link to Activity.
- Real buyers on the checkout page carry the token, so they are not affected.

### 3.5 Log without card data

- Checkout detections log an allowlist of fields only:
  - billing email, first and last name, and country;
  - the payment method's ID;
  - the order total;
  - the failure reason and the checkout path (`classic`, `store_api`).
- Nothing else from the request is stored: no `$_POST`, no payment fields. This
  also replaces the classic checkout's whole-`$_POST` log, which fixes the card
  data issue in section 1.
- A migration cannot clean card numbers that are already in the log, because
  they can't be told apart reliably. The 6.0.2 changelog says so, and the
  readme's FAQ explains how to clear the log.

## 4. Settings

These go in the existing WooCommerce card (Protection → Integrations). Each gets
a plain-language title and description and a `recommended` value. Nothing here
sends data to an outside service by itself: the reputation checks keep their
own switches and consent (guideline 7).

| Key | Title | Default / recommended |
|---|---|---|
| `woocommerce_card_testing` | Stop card testing | enabled / enabled |
| `woocommerce_card_testing_failures` | Failed payments before an address is paused | 3 |
| `woocommerce_card_testing_minutes` | Pause checkout for (minutes) | 60 |
| `woocommerce_card_testing_surge` | Failed payments across the store that start a surge (per 10 minutes) | 10 |
| `log_blocked_woocommerce_checkouts` | (existing) | |

The three numbers live under "Fine-tune" (progressive disclosure). The card
shows a status line: card-testing attempts stopped in the last 30 days, and
"surge mode on until 14:32" while one is under way.

**Existing sites:** an upgraded site has `verify_woocommerce_checkout` as it
was, and `woocommerce_card_testing` is a default-on switch (`'default' =>
'enabled'`, AGENTS.md §2). So the defence starts on every store that already
protects its checkout, and turning it off stores `disabled`.

## 5. zerospam.org

- Reports go out with module `woocommerce_checkout` and failed reason
  `card_testing`, only when Usage Data Sharing is on. **To check in the store
  plugin:** does `v6/report` accept any module and failed value, or only a list?
- No other change is needed on the store side for 6.0.2. Using the network's
  card-testing reports to score addresses is later work.

## 6. Tests and measurement

- **PHPUnit**, failing first:
  - the Store API route with a refused order (blocked IP, honeypot equivalent,
    blocked domain, reputation);
  - the counting and the limit, with time moved;
  - the exemptions;
  - the `checkout` block type;
  - surge mode on and off;
  - the log allowlist (a request carrying a fake card field never reaches
    `submission_data`);
  - multisite: counters and blocks per site.
- **e2e (wp-env with WooCommerce):**
  - a Checkout block order with the "Check payments" gateway;
  - a direct `POST /wc/store/v1/checkout` without a token during a surge (refused);
  - three failed payments, then a fourth refused, then lifting the block in
    Rules;
  - accessibility of the card;
  - visual baselines for the WooCommerce card.

  The e2e mu-plugin needs a test gateway that fails on demand.
- **How to measure it:**
  - card-testing refusals per site in Activity (a new detection type, `card_testing`);
  - the Overview's "stopped" count;
  - support topics about card testing after release;
  - #396 confirmed by its reporter.

## 7. Effort and risk

- **Effort:** large for a patch release. That's 3 new classes (`Checkout_Guard`,
  `Card_Testing_Limiter`, the Store API extension), a small checkout-block
  script, the settings and their card, a DB version bump with a migration, and
  the tests and e2e above.
- **Main risk:** turning away real buyers. Mitigations:
  - a token is only one signal;
  - blocks are temporary, checkout-only and visible in Rules, with exemptions;
  - surge mode alerts the administrator;
  - everything is on the classic and block paths that the e2e suite covers.
- **Second risk:** the gateways. Failure signals differ between Stripe, PayPal,
  Square and WooPayments. `woocommerce_order_status_failed` covers the gateways
  that mark orders failed. Gateways that leave a failed attempt as `pending`
  aren't counted by that hook; the e2e test gateway covers both.

## 8. Open questions for Ben (one at a time)

1. The security fix (#500) and the lookup fix (#501) are merged and ready. Ship
   them now as 6.0.2, and this as 6.0.3 (or 6.1.0, since it adds settings)?
   **Recommendation: yes.** A security fix shouldn't wait for a large feature.
2. Should blocks during a pause apply at checkout only (recommended), or across
   the whole site?
3. Should the WooCommerce card show the Enhanced Protection line as described in
   section 2?

## 9. As built (2026-11-22)

- **Pauses are kept in the `zerospam_card_testing` option**, not as Rules blocks with a new `checkout` key type. A new key type would change `wpzerospam_blocked` (DB version, migration) and every reader of it, including `DB::blocked()`, which `Access` uses for site-wide blocks. In the option, a pause can only ever affect checkout. Pauses end by themselves. An administrator lets a customer in at once by adding their address to the IP Allowlist, and every refusal is in Activity with its IP address.
- **No WooCommerce rate limit during a surge.** `woocommerce_store_api_rate_limit_options` applies to every Store API POST, including add-to-cart, so it would slow down real shoppers. Refusing unverified orders already stops bots that post straight to the API.
- **The Enhanced Protection line** is the "Stop card testing" description, with one link to the Enhanced Protection card. That needs no admin build, and there is no count (none is kept yet).
- **Logged-in customers** were never checked at checkout (`Access::process()` skips them). Failed payments only count against customers without a paid order.
- **The questions in section 8** were settled by Ben: everything ships in 6.0.2, and the recommendations stand (pause at checkout only, the Enhanced Protection line).
- **After review (security-reviewer, silent-failure-hunter):**
  - logged-in customers are checked;
  - the pay-for-order page and its Store API route are checked;
  - a retry of a failed order counts;
  - pauses are per-key transients and counters are written under a lock;
  - private, reserved and shared IPs don't count;
  - an email-only pause refuses only unverified orders, and a paid email never counts;
  - there is no fallback to WooCommerce's customer IP;
  - the surge email goes through cron;
  - card-testing refusals are always logged and never share an email;
  - the token script sets the token again when it is lost.
- **Checked on WooCommerce 11.3.1** in wp-env with a gateway that declines every payment:
  - a card tester retrying in one cart is refused from the third attempt;
  - the Checkout block's token reaches the order (`_zerospam_unverified` empty);
  - surge mode refuses a direct API order and lets a checkout-page order through;
  - the log holds only the allowlisted fields.
