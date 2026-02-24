# Task 014: Webhook Sending Interfaces and Implementation

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the marko/webhook package scaffolding with sending interfaces, HMAC-SHA256 signature signing, HTTP dispatch, and queue integration for reliable webhook delivery.

## Context
- New package at `packages/webhook/`
- Namespace: `Marko\Webhook`
- Depends on: marko/core, marko/routing, marko/http, marko/queue, marko/config
- Study `packages/http/src/Contracts/HttpClientInterface.php` for HTTP client usage
- Study `packages/queue/src/Contracts/JobInterface.php` and `packages/queue/src/Job/AbstractJob.php` for job pattern
- Study `packages/mail/` for a similar "dispatch something externally" pattern
- Webhook payload: event name, data array, URL, secret for signing
- Signature: HMAC-SHA256 of JSON payload, sent in X-Webhook-Signature header
- Delivery via queue job for reliability and retry

## Requirements (Test Descriptions)
- [ ] `it defines WebhookDispatcherInterface with dispatch method`
- [ ] `it creates WebhookPayload value object with url, event, data, and secret`
- [ ] `it signs payloads with HMAC-SHA256 via WebhookSignature utility`
- [ ] `it dispatches webhooks synchronously via HTTP client`
- [ ] `it queues webhook delivery via DispatchWebhookJob for async dispatch`
- [ ] `it creates valid package scaffolding with composer.json, module.php, and config`

## Acceptance Criteria
- All requirements have passing tests
- Interfaces in `src/Contracts/`
- Sending classes in `src/Sending/`
- Value objects in `src/Value/`
- Jobs in `src/Jobs/`
- Config at `config/webhook.php` with timeout, max_retries, retry_backoff settings
- Code follows code standards

## Implementation Notes
