# SMTPing Email Verifier for Mautic

Verify Mautic contacts with [SMTPing](https://smtping.com), block risky addresses on Mautic forms and route campaigns by verification result.

- **Forms**: every email field is checked on submit. Invalid, spamtrap, disposable and other risky addresses get an error message. Fails open if the API is unreachable.
- **Contacts**: a console command verifies contacts in bulk (up to 100,000 per run) and stores `smtping_status`, `smtping_band` and `smtping_verified_at` on each contact. Use them in segment filters.
- **Campaigns**: a "Verify email with SMTPing" action and an "SMTPing band" condition.

Requires Mautic 5 or 6 and PHP 8.0 or later.

> Beta (0.9.x). Please report any install or runtime issue to support@smtping.com or in GitHub issues.

## Install

With Composer, from the Mautic root:

```bash
composer require smtping/mautic-email-verifier
php bin/console mautic:plugins:reload
php bin/console cache:clear
```

Or manually: download the latest release zip, extract it to `plugins/SmtpingBundle`, then run the two commands above.

## Configure

1. In Mautic, open **Settings > Plugins**, then **SMTPing Email Verifier**.
2. Paste your API key from the [SMTPing dashboard](https://app.smtping.com) and set **Published** to Yes.
3. Under **Features**, choose whether to block risky addresses on forms and which bands to block.
4. Save.

## Verify existing contacts

```bash
# contacts never verified, up to 5,000 per run
php bin/console mautic:smtping:verify

# one segment, up to 20,000 contacts
php bin/console mautic:smtping:verify --segment=12 --limit=20000

# re-verify contacts last checked more than 90 days ago
php bin/console mautic:smtping:verify --older-than=90
```

The first run creates the three contact fields. If your Mautic creates custom fields in the background, run `php bin/console mautic:custom-field:create-column` once, then run the command again.

Add it to cron to verify new contacts every night:

```
15 2 * * * php /path/to/mautic/bin/console mautic:smtping:verify --limit=20000
```

## Bands

| band | statuses | suggested action |
| --- | --- | --- |
| `safe` | valid, alias | send |
| `avoid` | invalid, spamtrap, disposable, blacklisted, complainer, spambot, inbox_full | remove or mark Do Not Contact |
| `judgement` | catch_all, unknown, role and others | send to engaged contacts only |

Segment example: filter **SMTPing band** equals `safe` to build a send-ready segment.

## Credits

1 credit per verified address, including form checks. Every SMTPing account gets 25 free credits per day. See [pricing](https://smtping.com/pricing).

## Links

- [SMTPing API documentation](https://smtping.com/docs)
- Support: support@smtping.com

License: GPL-3.0-or-later
