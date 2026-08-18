=== WindCodex SwitchUser – WordPress User Switching & Audit Log for WooCommerce ===
Contributors: windcodex
Tags: user switching, login as user, login as customer, switch user, woocommerce
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Switch between WordPress user accounts in one click – no passwords needed. Secure sessions, role protection, and instant switch-back.

== Description ==

**WindCodex SwitchUser** is a secure, free user switching plugin for WordPress and WooCommerce. Switch into any lower-privilege user account in one click – no passwords, no account resets, no security risk.

Whether you are a **support agent** reproducing a customer bug, a **WooCommerce store owner** checking an order as the buyer, or a **developer** testing member-only content – SwitchUser gives you instant access to any user account and brings you straight back when you are done.

**Security-first design.** Every switch is nonce-verified, role-hierarchy-enforced, and recorded in a signed session cookie. You cannot accidentally switch into an equal-or-higher privilege account.

= Everything in the Free Version =

**One-Click User Switching**
* Switch from the **Users list** with a single click – no page reloads
* Switch from any **user profile edit screen**
* Switch from **WooCommerce order screens** – jump straight into the customer's account to reproduce checkout or order issues
* **Admin bar quick search** – find any user by name or email and switch instantly from any page

**Access Control**
* Switching is **enabled by default** – you opt out deliberately when you are ready
* Restrict switching to specific **WordPress roles** (e.g., only Shop Managers or Editors)
* Automatically blocks switching into **administrator accounts**
* Equal-or-higher privilege accounts are **always blocked** – no configuration needed
* Configurable **session duration** from 1 to 168 hours (default 48 hours)

**Security & Audit**
* Every switch action protected by **WordPress nonces** – full CSRF protection
* Switch sessions stored in a **signed, HTTPOnly cookie** – tamper-proof and replay-resistant
* Every switch action is recorded – who switched, when, from which IP, and for how long. A full **audit log dashboard** is available in SwitchUser Pro
* No passwords stored, logged, or transmitted – ever
* Full **WordPress multisite** support

**Switch Back**
* Prominent **Switch Back** button always visible in the admin bar during any active switch session
* **Switch Off** to end the session permanently and return to your original account
* Session expires automatically when the configured cookie TTL is reached

= Who Uses SwitchUser? =

* **WordPress support agencies** – debug client accounts without password sharing or account handover
* **WooCommerce store owners** – review orders from the customer's exact perspective
* **Membership site admins** – verify what members see after plan changes or role updates
* **Help desk and support teams** – reproduce user-reported issues in seconds without a support ticket
* **Developers and QA teams** – test role-restricted content, locked pages, and feature flags in context

= Security-First Design =

SwitchUser is built with security as the core requirement:

* **Role hierarchy enforcement** – switch targets must have strictly lower privilege than the switcher. Peer and admin accounts are blocked at the server level.
* **Enabled by default** – switching works immediately after activation. You can disable it anytime from the Settings page.
* **HMAC-signed cookie session** – the switch origin is signed with a secret key, not stored in a plain cookie or database row that can be tampered with.
* **Nonce on every action** – switch, switch back, and switch off actions are all CSRF-protected with WordPress nonces.

= 🚀 Pro Version =

Need IP allowlists, locked accounts, scheduled switching windows, email alerts, and a full audit log dashboard?
[SwitchUser Pro is available at windcodex.com](https://windcodex.com/product/woocommerce-user-switching-plugin/)

SwitchUser Pro adds advanced controls for agencies, enterprise teams, and high-security environments:

* **Idle timeout** – automatic switch-back after a configurable period of inactivity
* **Re-authentication gate** – require password confirmation before privileged switches
* **IP allowlist** – restrict switching to known office or VPN IP ranges
* **Scheduled windows** – allow switching only during defined hours or days
* **Per-user grants** – give specific users permission to be switched into
* **Full audit log dashboard** – every switch event recorded with actor, target, IP address, and timestamp
* **Email alerts** – notify admins when a switch session starts or ends
* **Locked user protection** – prevent switching into flagged or suspended accounts

= How It Works =

1. Activate SwitchUser – **User Switching** is enabled by default.
2. Go to the **SwitchUser** settings page in wp-admin and configure which roles can switch.
3. Click **Switch To** next to any user in the Users list, profile screen, or WooCommerce order screen.
4. Work in the target account – browse the frontend, check orders, test content.
5. Click **Switch Back** in the admin bar to return to your original account instantly.

= Requirements =

* WordPress 6.0 or higher
* PHP 8.1 or higher
* WooCommerce is optional – order-screen switching only appears when WooCommerce is active

== Installation ==

**From WordPress Dashboard (Recommended)**

1. Go to **Plugins > Add New Plugin**.
2. Search for **WindCodex SwitchUser** or **SwitchUser**.
3. Click **Install Now**, then **Activate**.
4. Navigate to **SwitchUser** in the left sidebar and configure your settings.

**Manual Installation**

1. Download the plugin `.zip` file.
2. Go to **Plugins > Add New Plugin > Upload Plugin**.
3. Upload the zip and click **Install Now**, then **Activate**.
4. Go to **SwitchUser** in the left sidebar to configure.

== Frequently Asked Questions ==

= Is user switching safe? =

Yes – when done correctly. SwitchUser protects every switch action with WordPress nonces (CSRF protection), enforces role hierarchy so you can only switch into lower-privilege accounts, and stores the session in a signed HTTPOnly cookie that cannot be tampered with or replayed.

= Does SwitchUser store passwords? =

Never. SwitchUser switches your WordPress session without touching the login form. No passwords are read, stored, logged, or transmitted at any point.

= Who can switch user accounts? =

By default, any user with the `edit_users` capability (typically Administrators). You can restrict this to specific roles – for example, allowing only Shop Managers to switch – from the Access Control settings.

= Can I accidentally switch into an administrator account? =

No. SwitchUser automatically blocks switching into any account with equal or higher privilege than the switcher. The "Block switching into administrators" setting adds an explicit extra layer on top of this rule.

= How do I switch back to my original account? =

A **Switch Back** button is always shown in the WordPress admin bar during an active switch session. Click it to return instantly. You can also click **Switch Off** to end the session entirely.

= What security measures does SwitchUser use? =

SwitchUser is enabled by default after activation, enforces strict server-side role hierarchy checks, signs session cookies with HMAC (not plain database rows), protects every action with WordPress nonces, and integrates with WooCommerce order screens and an admin-bar quick-search switcher.

= Does it work with WooCommerce? =

Yes. When WooCommerce is active, a **Switch to Customer** button appears on order edit screens, letting you jump into the customer's account to reproduce checkout issues, verify order history, or check account details exactly as they see them.

= Does the switch session expire automatically? =

Yes. The switch session is stored in a cookie with a configurable TTL (default 48 hours, adjustable from 1 to 168 hours). When the cookie expires, the session ends and you are returned to your original login state.

= What happens if I close the browser during a switch session? =

The switch session is stored in a persistent cookie (not a session cookie), so it survives browser restarts until the configured TTL expires. Once expired, you will need to log in again.

= Does SwitchUser work on WordPress multisite? =

Yes. SwitchUser is fully compatible with WordPress multisite networks.

= Is SwitchUser compatible with 2FA or membership plugins? =

SwitchUser bypasses the login form entirely, so it works alongside most 2FA and membership plugins. If a plugin enforces its own session validation on every page load, there may be edge cases – check the plugin documentation or contact support.

= Can I log or audit switch sessions in the free version? =

The free version records basic switch activity in signed session cookies but does not include a persistent audit log. A full audit log with actor, target, IP, and timestamp is available in SwitchUser Pro.

= Can I restrict which users can be switched into? =

In the free version, you can restrict who can perform switches by role. Per-user target grants – allowing only specific users to be switched into – are available in SwitchUser Pro.

== Screenshots ==

1. **Users list** – Switch To link next to each user row.
2. **Admin bar** – Quick user search, Switch Back, and Switch Off controls.
3. **Settings page** – Access Control, Integration Points, and session duration settings.
4. **WooCommerce order screen** – Switch to Customer button on order edit pages.

== Changelog ==

= 1.0.0 =
* Initial release.
* One-click user switching from Users list, profile, and WooCommerce order screens.
* Admin bar quick user search (name/email) with instant switch.
* Enabled by default, with role-based access control and session duration settings.
* Role hierarchy enforcement – equal-or-higher privilege targets blocked automatically.
* Signed HTTPOnly session cookie with configurable TTL (1–168 hours).
* CSRF-protected switch, switch-back, and switch-off actions.
* Multisite compatible. Translation-ready.

== Upgrade Notice ==

= 1.0.0 =
Initial release – no upgrade steps required.
