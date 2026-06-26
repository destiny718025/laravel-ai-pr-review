---
last_mapped: 2026-06-26
focus: tech
---

# Codebase Integrations

## Summary

The current application has only Laravel's default integration configuration. It does not yet integrate with GitHub, OpenAI, Anthropic, another LLM provider, a webhook endpoint, OAuth, or a production database service. Existing integration points are placeholders provided by Laravel config files.

## Existing External Service Config

`config/services.php` currently defines default Laravel service slots:

- Postmark: `services.postmark.key`, from `POSTMARK_API_KEY`.
- Resend: `services.resend.key`, from `RESEND_API_KEY`.
- AWS SES: `services.ses.key`, `services.ses.secret`, and `services.ses.region`.
- Slack notifications: `services.slack.notifications.bot_user_oauth_token` and default channel.

These are framework defaults and are not currently used by application code.

## Storage Integrations

`config/filesystems.php` includes standard Laravel disks:

- Local disk for application storage.
- Public disk for published files.
- S3-compatible disk using AWS-related environment variables.

No application code currently writes review artifacts, PR diffs, or generated comments to storage.

## Database Integrations

`config/database.php` defines these supported connections:

- SQLite, default connection.
- MySQL.
- MariaDB.
- PostgreSQL.
- SQL Server.
- Redis for cache/queue use cases.

The default local path is `database/database.sqlite`. The project has no custom database tables yet for repositories, pull requests, diffs, reviews, findings, rules, or users beyond Laravel's default `users` table.

## Queue Integrations

`config/queue.php` supports:

- `sync`
- `database`, currently the default.
- `beanstalkd`
- `sqs`
- `redis`
- `deferred`
- `background`
- `failover`

The default jobs migration exists, so background review processing can use database queues immediately.

## Mail and Notification Integrations

`config/mail.php` defaults to the `log` mailer unless overridden. The codebase has no notification classes yet. Slack config exists as a default service slot, but no Slack notifications are implemented.

## Webhook Surface

No webhook routes are currently defined.

- `routes/web.php` only defines `GET /`.
- There is no `routes/api.php` in this skeleton.
- `bootstrap/app.php` is already configured to render JSON for `api/*`, which is useful once API routes are added.

## Missing Planned Integrations

For an AI PR review tool, likely integrations still need to be introduced:

- GitHub App installation flow or personal access token flow.
- GitHub webhook signature verification.
- GitHub Pull Request API client for files, comments, checks, and statuses.
- AI provider client for review generation.
- Queue worker for asynchronous review jobs.
- Optional storage for raw diffs or normalized review artifacts.
- Optional notification channel for review completion or failures.

## Security Notes

- No real secrets were found in application code during mapping.
- Config files reference environment variable names only.
- Future mapping should avoid copying actual `.env` values into planning docs.
- GitHub webhook secrets and AI API keys should never be persisted in review records or logs.
