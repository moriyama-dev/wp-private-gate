=== WP Private Gate ===
Contributors: yoshiromoriyama
Donate link: https://takumi.ca
Tags: login, security, private, lockout, rest-api
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lock down your private WordPress site. Force login for all visitors, block REST API and XML-RPC, and lock out repeated failed login attempts.

== Description ==

WP Private Gate turns a WordPress install into a fully private site: nothing is reachable without logging in first. It's built for people running a diary, notes site, or internal tool on WordPress that should never be publicly visible or crawlable.

Unlike most "force login" or "password protect" plugins, WP Private Gate combines three protections in a single, dependency-free plugin:

* **Site-wide lockdown** — every page, post, and feed redirects unauthenticated visitors to the standard WordPress login screen.
* **REST API blocking** — unauthenticated requests to `/wp-json/` receive a `401 Unauthorized` response.
* **XML-RPC disabled** — `/xmlrpc.php` is fully disabled for everyone, authenticated or not.
* **Failed-login lockout** — an IP address is locked out for a configurable amount of time after too many failed login attempts, and by default the plugin says nothing that would tell an attacker they're locked out.

Developed and maintained by Yoshiro Moriyama, founder of Takumi Web Services — a WordPress development studio based in Toronto, Canada.

= Features =

* Redirects every unauthenticated front-end request to `wp-login.php`.
* Returns `401 Unauthorized` for unauthenticated REST API requests.
* Disables XML-RPC entirely.
* Locks out an IP address after a configurable number of failed login attempts (default: 5 attempts / 30 minutes).
* Lockout state is stored per IP address, not per username.
* Single settings screen under Settings > WP Private Gate.
* No custom database tables — everything is stored as options and removed cleanly on uninstall.

== Installation ==

1. Upload the `wp-private-gate` folder to `/wp-content/plugins/`, or install it directly from the Plugins screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to Settings > WP Private Gate to adjust the failed-login threshold and lockout duration.

== Frequently Asked Questions ==

= Will this lock me out of my own site? =

Only if you fail to log in more times than the configured threshold (5 by default). `wp-login.php` itself is never blocked, since it's the only way to authenticate.

= Does this block search engines and RSS readers too? =

Yes. Since the entire site requires authentication, no unauthenticated client — including search engine crawlers and feed readers — can access any content.

= Why doesn't the login form say I'm locked out? =

By default, WP Private Gate intentionally shows a generic "incorrect username or password" message instead of revealing that the IP is locked out. This keeps an attacker running a brute-force attempt from learning that their requests are being blocked outright. This can be changed in Settings > WP Private Gate.

== Screenshots ==

1. Settings screen under Settings > WP Private Gate.

== Changelog ==

= 1.0.0 =
* Initial release: site-wide lockdown, REST API blocking, XML-RPC disabling, and failed-login lockout.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
