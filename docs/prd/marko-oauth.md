# marko/oauth PRD

## Problem Statement

Marko has authentication packages for session and personal access token use cases, but it does not yet provide a native way for an application to act as an OAuth2 authorization server. Developers who need delegated third-party access, authorization-code flows, public clients with PKCE, or machine-to-machine client credentials currently have to assemble protocol handling, storage, routes, keys, consent, scopes, and token validation themselves.

## Solution

Build `marko/oauth`, a native Marko package that integrates `league/oauth2-server` with Marko routing, authentication, database, view, CLI, and middleware conventions. The package should feel like a Marko module while delegating protocol-critical OAuth2 behavior to a proven OAuth2 server library.

The first version should support authorization-code with PKCE, client credentials, refresh tokens with rotation, signed JWT access tokens, database-backed revocation and audit, first-class configured scopes, minimal overridable consent UI, client management commands, and a bearer-token guard/middleware for protected APIs.

## User Stories

1. As a Marko application developer, I want to install `marko/oauth`, so that my app can become an OAuth2 authorization server.
2. As a Marko application developer, I want OAuth routes registered automatically, so that installation gives me working protocol endpoints.
3. As a Marko application developer, I want configurable OAuth route prefixes, so that I can fit OAuth endpoints into my app URL structure.
4. As a Marko application developer, I want OAuth protocol routes enabled by default, so that `/oauth/authorize`, `/oauth/token`, and `/oauth/revoke` work after setup.
5. As a Marko application developer, I want management routes disabled by default, so that client administration is not accidentally exposed.
6. As a Marko application developer, I want OAuth data stored with Marko database entities and repositories, so that OAuth fits the framework's persistence model.
7. As a Marko application developer, I want repository interfaces at OAuth storage boundaries, so that I can replace persistence later if necessary.
8. As a Marko application developer, I want an `oauth:keys` command, so that I can generate signing keys without manually using OpenSSL.
9. As a Marko application developer, I want signing keys stored outside the package, so that secrets are not committed or overwritten by package updates.
10. As a Marko application developer, I want configurable key paths and passphrases, so that production deployments can use mounted secrets or environment-provided configuration.
11. As a Marko application developer, I want an `oauth:client:create` command, so that I can create OAuth clients from the CLI.
12. As a Marko application developer, I want `oauth:client:list`, so that I can inspect configured OAuth clients.
13. As a Marko application developer, I want `oauth:client:revoke`, so that compromised or retired clients can be disabled.
14. As a Marko application developer, I want client secrets hashed at rest and shown only once, so that stored client credentials are safer.
15. As a Marko application developer, I want confidential and public client types, so that browser/mobile clients are modeled differently from server-side clients.
16. As a Marko application developer, I want public clients to require PKCE for authorization-code flows, so that public clients do not rely on client secrets.
17. As a Marko application developer, I want confidential clients to use client credentials, so that machine-to-machine integrations are possible.
18. As a third-party app developer, I want to redirect users to the Marko app authorization endpoint, so that users can grant delegated access.
19. As an end user, I want to see a consent screen showing the client and requested scopes, so that I understand what access I am granting.
20. As an end user, I want to deny an authorization request, so that I can refuse access.
21. As an end user, I want previously approved scope sets remembered for a configurable period, so that I am not repeatedly asked for the same consent.
22. As an end user, I want broader scope requests to require renewed consent, so that clients cannot silently escalate access.
23. As an API client, I want to exchange authorization codes for access tokens, so that I can call protected APIs.
24. As an API client, I want to refresh tokens, so that users do not need to re-authorize every time an access token expires.
25. As a security-conscious developer, I want refresh tokens rotated by default, so that token theft is easier to detect and contain.
26. As a security-conscious developer, I want refresh-token reuse detection, so that a reused revoked refresh token can invalidate the token family.
27. As a resource server developer, I want signed JWT access tokens, so that APIs can validate tokens efficiently with a public key.
28. As a resource server developer, I want optional database revocation checks, so that revoked JWT access tokens can be rejected before natural expiry.
29. As a Marko application developer, I want configured scopes with human labels, so that unknown scopes are rejected and consent UI is clear.
30. As a Marko application developer, I want client-level allowed scopes, so that each client can be constrained to approved capabilities.
31. As a Marko application developer, I want a `RequiresScope` attribute, so that route handlers can declare required OAuth scopes.
32. As a Marko application developer, I want OAuth bearer authentication integrated with Marko middleware/guards, so that protected APIs use standard framework patterns.
33. As an API client, I want RFC-style token revocation, so that clients can revoke their own access or refresh tokens.
34. As an application administrator, I want service-level owner revocation APIs, so that user approvals and related refresh tokens can be revoked later.
35. As a package maintainer, I want the package to stay optional outside `marko/framework`, so that core installs do not carry OAuth complexity unnecessarily.

## Implementation Decisions

- Build the package as `marko/oauth` with namespace `Marko\OAuth`.
- Keep `marko/authentication-token` as the simple personal access token package; do not merge it with OAuth behavior.
- Wrap `league/oauth2-server` for protocol-critical behavior.
- Use Marko database entities and repositories as the default OAuth storage implementation.
- Provide interfaces where storage, client lookup, token persistence, approvals, and scope resolution cross package boundaries.
- Auto-register OAuth protocol routes with configurable prefix `/oauth`.
- Keep optional management routes disabled by default.
- Ship a minimal consent UI through Marko view conventions and allow app-level template override.
- Generate key pairs with an `oauth:keys` command and store them outside the package under configurable paths.
- Use signed JWT access tokens and persist token identifiers for revocation and audit.
- Support authorization-code with PKCE, client credentials, and refresh token grants in v1.
- Exclude password grant, implicit grant, device code, JWT bearer, SAML bearer, and OpenID Connect from v1.
- Treat scopes as configured capabilities with human-readable labels.
- Reject unknown requested scopes.
- Model clients as either confidential or public.
- Store only hashed client secrets and reveal plaintext only at creation.
- Avoid first-party OAuth shortcuts in v1; use `marko/authentication-token` for simple first-party API tokens.
- Avoid a full admin UI in v1; provide CLI and service primitives.
- Remember consent per user, client, and approved scope set with configurable TTL.
- Rotate refresh tokens by default and detect reuse.
- Support client revocation through `/oauth/revoke` and owner/admin revocation through services.
- Add route-aware middleware support where needed so `RequiresScope` can inspect matched route metadata.

## Testing Decisions

- Tests should verify externally visible behavior: issued tokens, redirects, repository persistence, revocation effects, scope enforcement, and CLI output.
- Avoid testing internal implementation details of `league/oauth2-server`.
- Add package structure tests similar to existing Marko packages.
- Add entity/repository tests following existing database-backed package patterns.
- Add command tests following existing CLI command tests.
- Add controller/route tests for OAuth protocol endpoints.
- Add focused grant-flow tests for client credentials, authorization code with PKCE, refresh rotation, and revocation.
- Add scope enforcement tests for `RequiresScope` and bearer-token middleware.
- Add regression tests around key generation overwrite protection and permissions where portable.
- Prior art includes tests in authentication, authentication-token, routing, database, admin-panel, and queue command packages.

## Out of Scope

- OpenID Connect.
- Password grant.
- Implicit grant.
- Device code grant.
- JWT bearer and SAML bearer grants.
- A full admin-panel UI for OAuth client management.
- Bundling `marko/oauth` into `marko/framework`.
- Replacing `marko/authentication-token`.
- Token introspection endpoint unless a later multi-service requirement appears.
- First-party OAuth shortcuts that blur OAuth with personal access tokens.

## Further Notes

The first implementation slice should scaffold the package, config, module, README, entities, key command, and client commands before implementing the grant flows. Client credentials should be implemented before authorization code because it exercises keys, clients, scopes, token issuance, and persistence without requiring consent UI.
