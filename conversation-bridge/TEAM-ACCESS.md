# Private lead access from Teams

Enable **Conversation Bridge → Integrations → Team access from Teams** after updating the plugin. This is opt-in; ordinary WordPress administrator access remains unchanged.

Every future form/chatbot notification gets a server-generated four-digit **Bridge PIN**, **Bridge access until (IST)** and a private **Open in WordPress Bridge** link. Each active lead has a different PIN. The PIN is delivered in the Teams message; it is not displayed on the public sign-in page, in administrator settings, or in the URL.

The existing Power Automate `Select → Post message in a chat or channel` flow already includes all supplied facts and uses the supplied action URL. It needs no new expressions for the PIN fields. Previously delivered messages retain their original administrator links; do not resend accepted alerts just to replace their links.

The link contains a separate unguessable access token. Both the exact link and its PIN are required. A PIN alone cannot find a lead. After unlocking it, team members can view **that single lead**, its learning goal, page attribution, and its chatbot conversation where applicable. They can save counsellor status and notes through the existing review service. No lead list, cross-record navigation, exports, WordPress settings, credential settings or other administrator features are granted.

New and Junk Lead stay in WordPress. Saving other statuses uses the current TeleCRM connection and exact status mapping. Opening a page, entering a PIN and receiving a Teams notification never qualify or send a lead. Uncertain TeleCRM results still require the existing administrator reconciliation workflow. A shared alert does not authenticate an individual staff identity; an optional counsellor name is recorded with the review note, with WordPress actor `0` rather than impersonating an administrator.

## Access lifetime and controls

- HTTPS is required on hosted sites; loopback localhost is permitted for development.
- Links expire after 1, 7 (default), or 30 days. Changing the lifetime affects newly allocated links.
- Each unlocked browser session lasts at most 30 minutes, or until the link expires.
- Five incorrect PIN attempts lock that lead for the remainder of the current 15-minute window, across IPs/browsers. There is also an IP-wide failed-attempt limit. Successful sign-ins do not consume this allowance.
- “Lock this lead” revokes that session. Disable team access or choose “Expire all existing team links” to invalidate all existing links and sessions immediately. Re-enabling does not revive old links.
- Only PIN hashes are used for checks. An authenticated encrypted copy is retained for notification retries; the raw PIN and private link must never be added to CRM payloads or application logs. PINs are unique across active links (10,000 possible four-digit values); allocation fails closed if a unique PIN cannot be allocated.
- Private pages send no-store, noindex, no-referrer and anti-framing headers, load no analytics/third-party resources and require session-bound CSRF checks for review/logout. Login challenges use a separate SameSite cookie.
- Exclude the `tlit_team_bridge` query parameter from any upstream/server/CDN full-page caching. Do not share team links or screenshots containing them outside the intended team.
- Chat privacy erasure revokes its team link in the same transaction. A deleted underlying record is never accessible through an old link.

## Local verification

```powershell
C:\xampp\php\php.exe conversation-bridge/tests/team-access-wordpress-test.php D:/wordpress/bridge-test-runtime/wordpress/wp-load.php
```

This suite refuses non-local runtimes, requires outbound HTTP blocking and intercepts every provider request. It covers authentication, cross-lead isolation, CSRF, unique PINs/retries, expiry, revocation, erased records, both review types, exact TeleCRM mapping and notification formatting. No live test lead or Teams message is sent.
