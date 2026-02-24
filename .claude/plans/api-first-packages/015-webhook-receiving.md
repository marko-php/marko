# Task 015: Webhook Receiving and Verification

**Status**: pending
**Depends on**: 014
**Retry count**: 0

## Description
Implement webhook receiving with signature verification, payload parsing, and the WebhookEndpoint attribute for declaring webhook receiver routes.

## Context
- Package: `packages/webhook/`
- Study `packages/routing/src/Attributes/` for route attribute patterns (Get, Post, etc.)
- Study `packages/security/src/` for existing signature/CSRF verification patterns
- WebhookReceiverInterface handles incoming webhook requests
- Signature verification reuses WebhookSignature from task 014
- WebhookEndpoint attribute registers POST routes for receiving webhooks
- Incoming webhooks are verified by checking X-Webhook-Signature header against computed HMAC

## Requirements (Test Descriptions)
- [ ] `it defines WebhookReceiverInterface with receive method`
- [ ] `it verifies incoming webhook signatures using HMAC-SHA256`
- [ ] `it throws InvalidSignatureException for failed signature verification`
- [ ] `it parses JSON payloads from incoming webhook request bodies`
- [ ] `it defines WebhookEndpoint attribute for route registration`

## Acceptance Criteria
- All requirements have passing tests
- Receiving classes in `src/Receiving/`
- WebhookEndpoint attribute in `src/Attributes/`
- InvalidSignatureException follows loud errors pattern
- Timing-safe comparison for signature verification (hash_equals)
- Code follows code standards

## Implementation Notes
