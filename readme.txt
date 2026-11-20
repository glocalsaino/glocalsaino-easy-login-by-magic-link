=== GlocalSaino Easy Login by Magic Link ===
Contributors: glocalsaino, rafammoo
Tags: magic link, passwordless, login link, ip restriction, device binding
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Passwordless login links that only work where they should: locked to an IP or a browser, requested by email or at registration, per-role redirects.

== Description ==

Most magic-link plugins stop at "click to log in". **GlocalSaino Easy Login by Magic Link** controls *where* a link can be used, so a forwarded, copied or intercepted link is worthless.

= What makes it different =

* **Same-browser links.** A link requested from the login form only works in the browser that asked for it. Someone who reads the email on another device, or steals the link, cannot use it.
* **Device lock for shared links.** Links you create in the dashboard can be locked to the browser of their first login. A multi-use link for a client or a support agent keeps working for them and nobody else.
* **IP restriction.** Limit a link to an office IP, a VPN range (CIDR, IPv4 and IPv6) or to the IP of its first use.
* **Scanner-proof by default.** Email security filters open links to check them. Opening the link only shows a "Log in" button; the click logs in, so scanners never use up single-use links.

= Let users log themselves in =

* **Request form shortcode** – put `[glocalsaino_magic_link_form]` on any page. Users enter their email address and receive a single-use login link. The answer is always the same ("If an account exists for this email address…"), so the form never reveals who is registered. Requests are rate-limited per email address and per IP, and a hidden field stops simple bots.
* **Registration checkbox** – new users can tick "Also email me a magic link" on the WordPress registration form, and the link is added to the welcome email WordPress already sends.
* **Roles you choose** – administrators (or any role you pick) never receive links by email.

= Redirects by role =

Send each role to its own page after logging in: customers to their account, editors to the dashboard, members to the member area. A page set on the link or in the shortcode takes priority.

= Links created by the administrator =

* **256-bit tokens** generated with `random_bytes()`; the link never contains the password.
* **Expiry** – 15 minutes, 1 hour, 1 day, 7 days, 30 days or never.
* **Usage limit** – 1 use (default), 5, 10 or unlimited.
* **Redirect page** per link.
* **Instant revocation**, copy to clipboard, status and use counter at a glance.

= Use cases =

* **Client handoff** – a link that only works on the client's own laptop.
* **Technical support** – temporary access restricted to the support team's IP.
* **Membership and course sites** – members request a login link instead of resetting forgotten passwords.
* **Demos** – open a demo site straight into the right account.

No external services, no extra database tables. Everything is free: there are no paid tiers, limits or license keys.

= For developers =

* `gselml_logged_in` action – fires after every magic-link login.
* `gselml_redirect_to` filter – change the landing page.
* `gselml_request_email` filter – change the email that delivers requested links.
* `gselml_user_can_receive_emailed_links` filter – decide who may receive links by email.
* `gselml_client_ip` filter – supply the real client IP behind a proxy or CDN.

= How to use =

1. Go to **Easy Login → Magic links** to create links by hand, with optional IP and device restrictions.
2. Go to **Easy Login → Settings** to set up the request form, the registration checkbox, the roles that cannot get links by email and the redirects by role.
3. Add `[glocalsaino_magic_link_form]` to a page, for example your login or "My account" page.

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

= How does the same-browser protection work? =

When a visitor requests a link (or registers), the site stores a random identifier in a cookie in that browser and saves only a keyed hash of it with the link. The link is accepted only if the same cookie comes back. It contains no personal data and is not used for tracking. Turn it on in **Easy Login → Settings**. Users then need to open the email on the same device and browser; links opened inside some email apps' own browser will not work.

= Does the request form reveal whether an email address is registered? =

No. The message is the same for every address, and the same browser cookie is set either way. Rate limits apply to every address, registered or not.

= My site is behind Cloudflare or another proxy. Does the IP restriction work? =

The plugin uses the connection IP (`REMOTE_ADDR`), which cannot be faked. Behind a proxy that is the proxy's IP. Use the `gselml_client_ip` filter to pass the real visitor IP from the header your proxy sets, only if your server accepts connections from that proxy alone.

= Can administrators request a link with the form? =

Not by default: the Administrator role is in "Roles that cannot get links by email". The form shows the usual message but sends nothing. You can change this in the settings.

= Can I create links for any user, including administrators? =

Yes. The dropdown lists every user of the site. A link for an administrator gives full access to the dashboard, so handle those links with special care.

= Can the link redirect to another website? =

No. For safety, the redirect URL must point to a page of your own site.

= How many links can I create? =

There is no limit.

= What happens to my links if I delete the plugin? =

All stored links are deleted together with the plugin.

== Screenshots ==

1. Magic links page: choose the user, landing page, expiry, maximum uses, "Log in" button page, IP restriction and device lock.
2. What the person sees after opening the link: one click on Log in and they are logged in.
3. Settings: request form, registration checkbox, security of emailed links and redirects by role.
4. The request form added with the shortcode.

== Privacy ==

This plugin does not send any data to third parties and makes no external connections.

* For each link it stores, in your own WordPress database, the token, the linked user ID, an optional label and redirect URL, the creation and expiry dates, a use counter with the time of the last use, and its restrictions: the allowed IP addresses, the IP address of the first login when the link is locked to it, and a keyed hash of the browser identifier when it is locked to a browser.
* Device-locked links and the same-browser protection set a cookie, `glocalsaino_magic_link_device`, holding a random identifier. It is strictly necessary for that security feature and is not used for tracking.
* The request form keeps short-lived counters (one hour) keyed by a hash of the email address and of the IP address to enforce the rate limits.
* Emailed links that have expired or been used are deleted automatically. Revoking a link deletes it permanently. Deleting the plugin deletes all links and settings.

== Changelog ==

= 1.0.0 =
* First public release.
* Magic links with expiry, usage limit, redirect page and a scanner-proof "Log in" button page.
* IP restriction (list of IPs and CIDR ranges, or the IP of the first login) and device lock (browser of the first login).
* Request form shortcode with identical answers, rate limits and same-browser protection.
* Registration checkbox that adds a magic link to the welcome email.
* Redirects by role.

== Upgrade Notice ==

= 1.0.0 =
First public release.
