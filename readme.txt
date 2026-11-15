=== GlocalSaino Easy Login by Magic Link ===
Contributors: glocalsaino, rafammoo
Tags: magic link, passwordless, login, one-click login, temporary access
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create magic links that log a WordPress user in with one click, without a password. Set expiry, maximum uses and a redirect page per link.

== Description ==

**GlocalSaino Easy Login by Magic Link** lets the site administrator create one-click links that log a visitor in as a specific WordPress user, without typing a password.

Each link carries a random 256-bit token. It never contains or sends the user's password. When the link is opened, the site checks the token and shows a simple page with a **Log in** button; one click sets the normal WordPress login cookie and sends the user to the page you chose.

That button page is on by default because many email security filters open every link in a message to scan it. Without it, those automatic visits could use up a single-use link before the person even sees it. You can turn it off per link for a direct one-click login.

= Use cases =

* **Client onboarding** – send a newly registered client a link so their first visit is frictionless.
* **Technical support** – give a support agent temporary access without sharing the real password.
* **Demos and presentations** – open a demo site straight into the right account, no login screen.
* **Access recovery** – an admin-managed alternative to "Lost your password?".

= Features =

* **256-bit tokens** generated with `random_bytes()`.
* **Expiry** – 1 hour, 1 day, 7 days, 30 days or never.
* **Usage limit** – 1 use (default), 5, 10 or unlimited.
* **Redirect page per link** – any page of your site.
* **Scanner-proof "Log in" button page** (on by default, optional per link) – opening the link never uses it up, only the click does.
* **Clear message for expired, used-up or revoked links**, with a link to the normal login page.
* **Instant revocation** of any link from the admin page.
* **Copy to clipboard** in one click.
* **Status at a glance** – active, expired or used up, with a use counter.
* **No external services, no extra database tables** – links are stored in a single WordPress option, removed when the plugin is deleted.
* Developer hook `gselml_logged_in` fires after every magic-link login.

Everything is free: there are no paid tiers, limits or license keys.

= How to use =

1. Go to the **Easy Login** menu in the dashboard.
2. Pick the user, the expiry and the maximum number of uses.
3. Click **Create magic link** and copy the link from the list.
4. Send the link to the user. Opening it shows a **Log in** button (or logs them in straight away if you turned that page off).

== Installation ==

1. Upload the `glocalsaino-easy-login-by-magic-link` folder to `/wp-content/plugins/`, or install the plugin from **Plugins → Add New**.
2. Activate it.
3. Go to the **Easy Login** menu in the dashboard to create your first link.

== Frequently Asked Questions ==

= Is it safe? Does the link contain the user's password? =

No. The link only contains a random 256-bit token (64 hexadecimal characters). The password is never included or sent. The token is an opaque key that the site exchanges for a login session. Anyone holding an active link can log in as that user, so treat links like passwords: use short expiry times and a single use whenever possible, and revoke links you no longer need.

= What happens when a link expires or reaches its usage limit? =

The token stops working. The visitor sees a short message saying the link is no longer valid, with a link to the normal login page.

= Can email security scanners use up my links? =

Some email security filters open every link in incoming messages to scan them. With the "Log in" button page (on by default) those automatic visits do not count: only a click on the button logs in and uses the link. If you turned that page off for a link, scanners can use it up; create a new link with the page on.

= Can I create links for any user, including administrators? =

Yes. The dropdown lists every user of the site. A link for an administrator gives full access to the dashboard, so handle those links with special care.

= Can the link redirect to another website? =

No. For safety, the redirect URL must point to a page of your own site.

= How many links can I create? =

There is no limit.

= What happens to my links if I delete the plugin? =

All stored links are deleted together with the plugin.

== Privacy ==

This plugin does not send any data to third parties and makes no external connections.

* For each link it stores, in your own WordPress database, the token, the linked user ID, an optional label and redirect URL, the creation and expiry dates, and a use counter with the time of the last use.
* Revoking a link deletes it permanently. Deleting the plugin deletes all links.

== Changelog ==

= 1.0.0 =
* First public release: create, list, copy and revoke magic login links with expiry, usage limit, redirect page and an optional scanner-proof "Log in" button page.

== Upgrade Notice ==

= 1.0.0 =
First public release.
