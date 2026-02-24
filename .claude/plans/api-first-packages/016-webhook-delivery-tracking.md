# Task 016: Webhook Delivery Tracking

**Status**: pending
**Depends on**: 015
**Retry count**: 0

## Description
Implement webhook delivery tracking with a WebhookAttempt entity that records delivery attempts, and retry logic with exponential backoff for failed deliveries.

## Context
- Package: `packages/webhook/`
- Study `packages/blog/src/Entity/Post.php` for entity pattern with #[Table] and #[Column] attributes
- Study `packages/queue/src/Contracts/JobInterface.php` for job retry patterns
- WebhookAttempt entity tracks: webhook_url, event, status_code, response_body, error_message, attempted_at
- Retry uses exponential backoff: delay = base_delay * (2 ^ attempt_number)
- Max retries configurable via config/webhook.php
- DispatchWebhookJob from task 014 should record attempts and handle retries

## Requirements (Test Descriptions)
- [ ] `it defines WebhookAttempt entity with table and column attributes`
- [ ] `it records successful delivery attempts with status code and response`
- [ ] `it records failed delivery attempts with error details`
- [ ] `it retries failed deliveries with exponential backoff`
- [ ] `it stops retrying after reaching maximum retry limit from config`

## Acceptance Criteria
- All requirements have passing tests
- WebhookAttempt entity in `src/Entity/`
- Retry logic integrated into DispatchWebhookJob
- Backoff calculation: configurable base delay * 2^attempt
- Attempt recording uses WebhookAttemptRepositoryInterface
- Code follows code standards

## Implementation Notes
