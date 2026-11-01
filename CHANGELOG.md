# Changelog

All notable changes to Marko are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/). While Marko is in `0.x`, the API may change between minor versions.

Entries from `0.4.0` onward are generated automatically by `bin/release.sh` from merged PR titles and labels (see `.github/release.yml`). Earlier entries were backfilled from GitHub Releases. The full list of changes for any version is also available at https://github.com/marko-php/marko/releases.

<!-- new-entries-below — do not remove this marker; bin/release.sh inserts new versions directly below it -->

## [0.12.0] - 2026-11-01

### Breaking Changes
* feat: gate before hooks, policy-level before and an ability-checked event by @markshust in https://github.com/marko-php/marko/pull/571
* feat!: add remember, add, pull and forever to the cache by @markshust in https://github.com/marko-php/marko/pull/572
* feat!: add marko/redis shared connection package and move cache-redis onto it by @markshust in https://github.com/marko-php/marko/pull/573
* feat!: engine-agnostic template functions, shared view data and precompile contract by @markshust in https://github.com/marko-php/marko/pull/577
* feat!: streamed and file responses with http range support by @markshust in https://github.com/marko-php/marko/pull/578
* feat!: relationship existence filters with exists, column comparison and not-in query primitives by @markshust in https://github.com/marko-php/marko/pull/579
* feat!: queue job contract changes (per-job state, release(JobInterface), PushResult, shared JobRunner) by @markshust in https://github.com/marko-php/marko/pull/580
* feat!: log handler contract, handler stacking and a stdout/stderr stream handler by @markshust in https://github.com/marko-php/marko/pull/581
* feat!: schedule console commands and run them in the background by @markshust in https://github.com/marko-php/marko/pull/582
* feat!: end a user's other sessions and remember-me devices by @markshust in https://github.com/marko-php/marko/pull/587
* feat!: job failure hook, one final-failure path, and job lifecycle events by @markshust in https://github.com/marko-php/marko/pull/591
* feat: run scheduled tasks on one server with onOneServer() and a lock-backed TaskMutex by @markshust in https://github.com/marko-php/marko/pull/592
* feat!: queue retry deadline (RetryUntilInterface), maxExceptions and self-release by @markshust in https://github.com/marko-php/marko/pull/597
* feat!: per-job timeout, worker --max-time, and one worker stop-reason mechanism by @markshust in https://github.com/marko-php/marko/pull/602
* feat!: redis queue driver (marko/queue-redis) by @markshust in https://github.com/marko-php/marko/pull/603
* feat!: queue:restart for graceful worker restarts after deploys by @markshust in https://github.com/marko-php/marko/pull/605
* feat: make marko/cache optional for marko/queue by @markshust in https://github.com/marko-php/marko/pull/607
* refactor!: set the module loader once in the latte and twig engine factories by @markshust in https://github.com/marko-php/marko/pull/628
* refactor!: move SleeperInterface into marko/core by @markshust in https://github.com/marko-php/marko/pull/629
* feat: detect changes to the discovery environment variables in the config cache by @markshust in https://github.com/marko-php/marko/pull/630
* fix: resolve a relative cache path against the project root by @markshust in https://github.com/marko-php/marko/pull/631
* feat!: log queue worker output through LoggerInterface instead of error_log() by @markshust in https://github.com/marko-php/marko/pull/633
### New Features
* feat: scheduler time helpers, per-task time zones and when/skip conditions by @markshust in https://github.com/marko-php/marko/pull/568
* feat: notification sender interface and FakeNotificationSender by @markshust in https://github.com/marko-php/marko/pull/569
* feat: format, size, date comparison, distinct and enum validation rules by @markshust in https://github.com/marko-php/marko/pull/570
* feat: filesystem fake, swappable disks via FilesystemManagerInterface and shared path rules by @markshust in https://github.com/marko-php/marko/pull/574
* feat: password reset broker with hashed, expiring tokens by @markshust in https://github.com/marko-php/marko/pull/575
* feat: production config caching through discovery:cache by @markshust in https://github.com/marko-php/marko/pull/576
* feat: implicit and presence-aware validation rules by @markshust in https://github.com/marko-php/marko/pull/583
* feat: password confirmation middleware for sensitive routes by @markshust in https://github.com/marko-php/marko/pull/584
* feat: exists and unique validation rules for entities (marko/validation-database) by @markshust in https://github.com/marko-php/marko/pull/585
* feat: session, auth, download and json validation assertions for http tests by @markshust in https://github.com/marko-php/marko/pull/586
* feat: atomic cache locks with redis, file and array stores by @markshust in https://github.com/marko-php/marko/pull/588
* feat: login challenge hook and two-factor authentication package by @markshust in https://github.com/marko-php/marko/pull/589
* feat: current password and password strength validation rules by @markshust in https://github.com/marko-php/marko/pull/590
* feat: passkey (webauthn) sign-in and management package (marko/authentication-passkey) by @markshust in https://github.com/marko-php/marko/pull/593
* feat: latte and twig template precompilers for view:cache by @markshust in https://github.com/marko-php/marko/pull/594
* feat: signed and temporary signed urls with a valid-signature middleware by @markshust in https://github.com/marko-php/marko/pull/595
* feat: unique queue jobs (UniqueJobInterface) that skip duplicate dispatches by @markshust in https://github.com/marko-php/marko/pull/596
* feat: add marko/forms with redirect-back flash of validation errors and old input by @markshust in https://github.com/marko-php/marko/pull/598
* feat: email verification with signed links and a verified middleware by @markshust in https://github.com/marko-php/marko/pull/599
* feat: flashed validation-error and old-input assertions on TestResponse by @markshust in https://github.com/marko-php/marko/pull/600
* feat: register only named packages with bin/register-packagist.sh by @markshust in https://github.com/marko-php/marko/pull/606
* feat: list URL_SIGNING_KEY in the skeleton .env.example by @markshust in https://github.com/marko-php/marko/pull/622
* feat: serve file and streamed responses through the roadrunner bridge by @markshust in https://github.com/marko-php/marko/pull/626
* feat: add FakeUserPersister to marko/testing by @markshust in https://github.com/marko-php/marko/pull/627
* feat: database-backed password reset token repository (marko/authentication-database) by @markshust in https://github.com/marko-php/marko/pull/635
* feat: cache-backed restart signal for queue workers on several hosts by @markshust in https://github.com/marko-php/marko/pull/637
* feat: ship marko/log with the file and stream handlers in the skeleton by @markshust in https://github.com/marko-php/marko/pull/642
* feat: throw when a failed job can't be stored instead of only logging it by @markshust in https://github.com/marko-php/marko/pull/643
### Bug Fixes
* fix: render validation redirects through http exception responders by @markshust in https://github.com/marko-php/marko/pull/604
* fix: accept the compiled-template cache directory on windows by @markshust in https://github.com/marko-php/marko/pull/623
* fix: declare health's optional packages and fail loudly when one is missing by @markshust in https://github.com/marko-php/marko/pull/634
* fix: resolve relative local disk roots against the project root by @markshust in https://github.com/marko-php/marko/pull/638
* fix: make flaky detached devserver and amphp sse stream timeout tests deterministic by @markshust in https://github.com/marko-php/marko/pull/639
### Documentation
* docs: remove the markotalk example from the readme and docs by @markshust in https://github.com/marko-php/marko/pull/645
### Refactoring
* refactor: verify admin passwords through PasswordVerifierInterface by @markshust in https://github.com/marko-php/marko/pull/624
* refactor: resolve configured paths through ProjectPaths by @markshust in https://github.com/marko-php/marko/pull/632
### Testing
* test: derive the root composer.json package set from packages/ by @markshust in https://github.com/marko-php/marko/pull/625
### CI
* ci: label a pr breaking only for a ! title or BREAKING CHANGE: footer by @markshust in https://github.com/marko-php/marko/pull/644
* ci: bump checkout to v7, setup-node to v7 and composer-install to v4 by @markshust in https://github.com/marko-php/marko/pull/646
### Maintenance
* chore: tests never read documentation by @markshust in https://github.com/marko-php/marko/pull/530


## [0.11.0] - 2026-10-06

### Breaking Changes
* fix!: wire up or remove config keys nothing reads by @markshust in https://github.com/marko-php/marko/pull/520
* fix!: render layouts innermost so route middleware headers and cookies survive by @markshust in https://github.com/marko-php/marko/pull/522
* feat!: add a destructive marker to #[Command] and refuse marked commands in mcp by @markshust in https://github.com/marko-php/marko/pull/524
* feat!: pin webhook connections to the ip the url policy validated by @markshust in https://github.com/marko-php/marko/pull/525
### New Features
* feat: ship a shared FakeEncryptor in marko/testing by @markshust in https://github.com/marko-php/marko/pull/523
* feat: per-device remember-me tokens by @markshust in https://github.com/marko-php/marko/pull/527
### Bug Fixes
* fix: pass csrf token when re-rendering the admin login form after failed credentials by @markshust in https://github.com/marko-php/marko/pull/519
* fix: run a dummy bcrypt check before rejecting unhashable values in BcryptHasher::verify by @markshust in https://github.com/marko-php/marko/pull/521
### Documentation
* docs: update mcp allow_destructive config comment for the destructive marker by @markshust in https://github.com/marko-php/marko/pull/526


## [0.10.0] - 2026-10-06

### Breaking Changes
* fix!: filter when() and missing() in resource collections and nested resources by @markshust in https://github.com/marko-php/marko/pull/441
* fix!: resolve session.path against the project root and refuse public/ by @markshust in https://github.com/marko-php/marko/pull/442
* fix!: equalize login timing and reject non-string login credentials by @markshust in https://github.com/marko-php/marko/pull/444
* fix!: enforce token tokenable_type and api token abilities in the gate by @markshust in https://github.com/marko-php/marko/pull/445
* fix!: validate mail attachment header fields and stuff dots after bare lf by @markshust in https://github.com/marko-php/marko/pull/446
* fix: preserve parameter default values in plugin interceptors by @markshust in https://github.com/marko-php/marko/pull/448
* fix!: hmac-sign file cache entries before unserializing them by @markshust in https://github.com/marko-php/marko/pull/449
* fix!: bind controller params from request input only via explicit attributes by @markshust in https://github.com/marko-php/marko/pull/450
* fix!: keep credentialed requests out of the page cache by @markshust in https://github.com/marko-php/marko/pull/451
* fix!: restrict url validation rule to http and https by default by @markshust in https://github.com/marko-php/marko/pull/452
* fix!: reject empty and short webhook secrets by @markshust in https://github.com/marko-php/marko/pull/453
* fix!: make mcp query_database read-only mode enforceable by @markshust in https://github.com/marko-php/marko/pull/454
* fix!: measure numeric strings by length in min/max/between unless the field is numeric by @markshust in https://github.com/marko-php/marko/pull/455
* fix!: compute amphp subscriber token mac over the encoded payload by @markshust in https://github.com/marko-php/marko/pull/456
* fix!: confine local filesystem to its root and stop deleteDirectory following symlinks by @markshust in https://github.com/marko-php/marko/pull/457
* fix!: restrict channel placeholder charset and refuse uri-template topics by @markshust in https://github.com/marko-php/marko/pull/458
* fix!: register csrf middleware globally and support xsrf-token for spas by @markshust in https://github.com/marko-php/marko/pull/459
* fix!: block ssrf in outgoing webhooks and stop following redirects by @markshust in https://github.com/marko-php/marko/pull/461
* fix!: separate the admin guard and user provider from the frontend by @markshust in https://github.com/marko-php/marko/pull/463
* fix!: sniff image format and set resource limits before imagick decodes input by @markshust in https://github.com/marko-php/marko/pull/464
* fix!: limit database search to declared columns and escape like wildcards by @markshust in https://github.com/marko-php/marko/pull/465
* fix!: stop leaking exception details from /health and enforce health.secret by @markshust in https://github.com/marko-php/marko/pull/466
* fix!: reject bcrypt passwords over 72 bytes or containing nul by @markshust in https://github.com/marko-php/marko/pull/467
* fix!: keep image/* wildcard in mimetypes from admitting svg by @markshust in https://github.com/marko-php/marko/pull/468
* fix!: drop underscore header names in roadrunner request bridge by @markshust in https://github.com/marko-php/marko/pull/469
* fix!: reject traversal values in route parameters by @markshust in https://github.com/marko-php/marko/pull/470
* fix!: harden debugbar redaction, storage permissions and access control by @markshust in https://github.com/marko-php/marko/pull/472
* fix!: give http-guzzle default timeouts, require guzzle ^7.9, and redact urls in exceptions by @markshust in https://github.com/marko-php/marko/pull/473
* fix!: install a safe bootstrap error handler before application boot by @markshust in https://github.com/marko-php/marko/pull/475
* fix!: default compiled-template cache to storage/views and refuse unsafe directories by @markshust in https://github.com/marko-php/marko/pull/477
* fix!: guard mail-log against production and stop logging bodies outside dev by @markshust in https://github.com/marko-php/marko/pull/478
* fix!: escape log control characters, restrict log file permissions, redact sensitive context by @markshust in https://github.com/marko-php/marko/pull/479
* fix!: add owner-scoped markAsReadFor and deleteFor to notification repository by @markshust in https://github.com/marko-php/marko/pull/482
* fix!: use native prepares and single statements on mysql by @markshust in https://github.com/marko-php/marko/pull/484
* fix!: refuse untrusted discovery cache files and randomize temp names by @markshust in https://github.com/marko-php/marko/pull/486
* fix!: return only aggregate counts from the amphp health endpoint by @markshust in https://github.com/marko-php/marko/pull/488
* fix!: verify tls by default for pgsql, mysql, pubsub and rabbitmq transports by @markshust in https://github.com/marko-php/marko/pull/490
* fix!: store media on the configured disk and verify uploaded tmp paths by @markshust in https://github.com/marko-php/marko/pull/491
* fix!: enforce admin section permissions and class-level requirespermission by @markshust in https://github.com/marko-php/marko/pull/496
* fix!: send security headers globally without overriding route headers by @markshust in https://github.com/marko-php/marko/pull/497
* fix!: harden rate limiter against ipv6 rotation, cache fail-open and proxy ranges by @markshust in https://github.com/marko-php/marko/pull/498
* fix!: allowlist mcp console commands, refuse production serving, tail logs by @markshust in https://github.com/marko-php/marko/pull/499
* fix!: bound page cache key flooding and make tag indexing o(1) by @markshust in https://github.com/marko-php/marko/pull/500
* fix!: derive hmac subkeys and bind cache macs to their key by @markshust in https://github.com/marko-php/marko/pull/501
* feat!: add associated data, key rotation and per-cipher key length to encryption by @markshust in https://github.com/marko-php/marko/pull/502
* fix!: expire remember-me tokens server-side and rotate session and csrf token on login and logout by @markshust in https://github.com/marko-php/marko/pull/503
* fix!: remove config keys nothing reads and guard against new ones by @markshust in https://github.com/marko-php/marko/pull/504
* fix!: verify #[WebhookEndpoint] routes and harden webhook replay, queue secrets and body parsing by @markshust in https://github.com/marko-php/marko/pull/505
* fix!: add queue worker timeout, memory and job limits, and graceful shutdown by @markshust in https://github.com/marko-php/marko/pull/507
* fix!: rehash passwords on login and back authentication with marko/hashing by @markshust in https://github.com/marko-php/marko/pull/508
* feat!: throttle login attempts with lockout and exponential backoff by @markshust in https://github.com/marko-php/marko/pull/509
### Bug Fixes
* fix: reject truncated gcm auth tags and wrong-length ivs in openssl decrypt by @markshust in https://github.com/marko-php/marko/pull/439
* fix: pass inner denials through layout middleware by @markshust in https://github.com/marko-php/marko/pull/440
* fix: split sse data on every line terminator and reject nul in fields by @markshust in https://github.com/marko-php/marko/pull/443
* fix: reject path traversal in template names by @markshust in https://github.com/marko-php/marko/pull/447
* fix: enforce dimension and decompression-bomb limits in gd image processor by @markshust in https://github.com/marko-php/marko/pull/460
* fix: read session rows from the primary on read/write split connections by @markshust in https://github.com/marko-php/marko/pull/462
* fix: reject poison rabbitmq messages and ack only after a confirmed republish by @markshust in https://github.com/marko-php/marko/pull/474
* fix: encode s3 copy source, reject unsafe paths, bound temporary url expiry by @markshust in https://github.com/marko-php/marko/pull/476
* fix: resolve rollback migration names against known migration files by @markshust in https://github.com/marko-php/marko/pull/480
* fix: render generated migration sql as escaped string literals by @markshust in https://github.com/marko-php/marko/pull/481
* fix: validate devai orphan-skill names before recursive cleanup by @markshust in https://github.com/marko-php/marko/pull/485
* fix: return 403 from pusher auth for channels with no authorizer by @markshust in https://github.com/marko-php/marko/pull/487
* fix: mask credential headers and harden error handler fallbacks by @markshust in https://github.com/marko-php/marko/pull/489
* fix: strip all secret-looking keys from failed login event credentials by @markshust in https://github.com/marko-php/marko/pull/492
* fix: make discovery skips of files referencing missing marko classes visible by @markshust in https://github.com/marko-php/marko/pull/493
* fix: harden devai supply chain by @markshust in https://github.com/marko-php/marko/pull/494
* fix: quote smtp display names and validate envelope addresses by @markshust in https://github.com/marko-php/marko/pull/495
### Testing
* test: fix amphp token tests after channel charset change by @markshust in https://github.com/marko-php/marko/pull/471
* test: fix csrf rotation encryptor fake after aad interface change by @markshust in https://github.com/marko-php/marko/pull/506
* test: fix roadrunner e2e session-cookie assertion after global csrf by @markshust in https://github.com/marko-php/marko/pull/510
### CI
* fix: send packagist token in authorization header instead of url by @markshust in https://github.com/marko-php/marko/pull/483


## [0.9.0] - 2026-10-06

### Breaking Changes
* fix(mail-smtp): build RFC 5322 conformant messages and repair STARTTLS by @etienne-scherrer in https://github.com/marko-php/marko/pull/158
* fix: mirror real env vars into $_ENV, share config, and add AppEnvironment by @markshust in https://github.com/marko-php/marko/pull/192
* feat: support --name value for long cli options by @markshust in https://github.com/marko-php/marko/pull/196
* fix: harden the rate limiter for redis, ipv6 and per-route limits by @markshust in https://github.com/marko-php/marko/pull/197
* feat: add psr-20 clock with a fake for tests by @markshust in https://github.com/marko-php/marko/pull/200
* feat: wire remember-me and auth events through AuthManager by @markshust in https://github.com/marko-php/marko/pull/201
* fix: stop database queue retrying forever and bind the queue worker by @markshust in https://github.com/marko-php/marko/pull/202
* feat: add entity casts, automatic timestamps and encrypted columns by @markshust in https://github.com/marko-php/marko/pull/206
* feat: route precedence, 405, HEAD, OPTIONS and global cors by @markshust in https://github.com/marko-php/marko/pull/210
* feat: savepoints, after-commit callbacks, row locks and upsert by @markshust in https://github.com/marko-php/marko/pull/213
* fix: record a container-aware job that fails for the last time instead of crashing the worker by @markshust in https://github.com/marko-php/marko/pull/241
* refactor: move the console confirmation prompter into core by @markshust in https://github.com/marko-php/marko/pull/244
* fix: render authorization failures through the exception renderer by @markshust in https://github.com/marko-php/marko/pull/246
* feat: typed exceptions and opt-in retries for database concurrency errors by @markshust in https://github.com/marko-php/marko/pull/247
* feat: resolve the gate and guard lazily in the #[Can] middleware by @markshust in https://github.com/marko-php/marko/pull/249
* feat: require --force for destructive db commands outside development and testing by @markshust in https://github.com/marko-php/marko/pull/259
* feat: skip stateful middleware on unmatched requests and persist sessions lazily by @markshust in https://github.com/marko-php/marko/pull/260
* feat!: wire the authentication-token guard through a guard driver registry by @markshust in https://github.com/marko-php/marko/pull/261
* refactor: read time through the psr-20 clock in database, queue, queue-database and scheduler by @markshust in https://github.com/marko-php/marko/pull/272
* refactor: read time through the psr-20 clock in authentication, admin-auth, errors and log by @markshust in https://github.com/marko-php/marko/pull/274
* fix: run destructive test database helpers only in testing environments by @markshust in https://github.com/marko-php/marko/pull/278
* fix: validate session ids against the store so unknown cookies are never adopted by @markshust in https://github.com/marko-php/marko/pull/285
* feat: start the session lazily on cookieless requests by @markshust in https://github.com/marko-php/marko/pull/299
* fix: store queue, token, notification and webhook timestamps in database.timezone by @markshust in https://github.com/marko-php/marko/pull/300
* fix: throw for guard names missing from authentication.guards by @markshust in https://github.com/marko-php/marko/pull/302
* refactor: render admin-api errors through HttpException instead of an errors envelope by @markshust in https://github.com/marko-php/marko/pull/316
* feat: read database-generated primary keys back on save by @markshust in https://github.com/marko-php/marko/pull/321
* feat: pin the database session time zone to database.timezone on connect by @markshust in https://github.com/marko-php/marko/pull/327
* feat: quote every identifier through ConnectionInterface::quoteIdentifier() by @markshust in https://github.com/marko-php/marko/pull/331
* feat: build admin sections on first use instead of at boot by @markshust in https://github.com/marko-php/marko/pull/333
* feat: update, report and prune unregistered admin permissions on sync by @markshust in https://github.com/marko-php/marko/pull/335
* fix: create the admin-auth tables from entities on db:migrate by @markshust in https://github.com/marko-php/marko/pull/340
* feat: create the queue and session tables from entities with db:migrate by @markshust in https://github.com/marko-php/marko/pull/345
* fix: give admin-auth keys, slugs and emails one canonical form on every driver by @markshust in https://github.com/marko-php/marko/pull/350
* fix: capture warning reasons instead of suppressing them with @ by @markshust in https://github.com/marko-php/marko/pull/359
* refactor: remove the deprecated global env() helper by @markshust in https://github.com/marko-php/marko/pull/361
### New Features
* feat: response decoration api with cookie support by @markshust in https://github.com/marko-php/marko/pull/152
* feat: roadrunner application server support by @markshust in https://github.com/marko-php/marko/pull/153
* feat: validate http client options and add FakeHttpClient by @markshust in https://github.com/marko-php/marko/pull/194
* feat: parse json request bodies and map uploaded files by @markshust in https://github.com/marko-php/marko/pull/198
* feat: map exceptions to http responses and fix errors-advanced by @markshust in https://github.com/marko-php/marko/pull/203
* feat: add broadcasting interface with mercure and pusher drivers by @markshust in https://github.com/marko-php/marko/pull/205
* feat: work queues in priority order and make retry backoff configurable by @markshust in https://github.com/marko-php/marko/pull/207
* feat: throw typed exceptions for database constraint violations by @markshust in https://github.com/marko-php/marko/pull/208
* feat: queue async observers through marko/queue instead of running them inline by @markshust in https://github.com/marko-php/marko/pull/209
* feat: add an in-process HTTP test client to marko/testing by @markshust in https://github.com/marko-php/marko/pull/212
* feat: add native async SSE broadcasting server (marko/broadcasting-amphp) by @markshust in https://github.com/marko-php/marko/pull/214
* feat: add database test refresh and entity factories by @markshust in https://github.com/marko-php/marko/pull/215
* feat: named routes, prefixes, catch-all and constrained parameters, WithoutMiddleware by @markshust in https://github.com/marko-php/marko/pull/216
* feat: cache module discovery and routes so production requests skip re-scanning by @markshust in https://github.com/marko-php/marko/pull/237
* feat: lossless, case-insensitive response header access by @markshust in https://github.com/marko-php/marko/pull/238
* feat: multi-file uploads and a scoped cookie jar in the test client by @markshust in https://github.com/marko-php/marko/pull/240
* feat: add file upload validation rules by @markshust in https://github.com/marko-php/marko/pull/242
* feat: support pusher presence channels by @markshust in https://github.com/marko-php/marko/pull/273
* feat: honour cookie expiry, samesite and public suffixes in the test client jar by @markshust in https://github.com/marko-php/marko/pull/279
* feat: validate array items with wildcard keys like photos.* and items.*.name by @markshust in https://github.com/marko-php/marko/pull/280
* feat: wire token_expiration_days as the default token lifetime by @markshust in https://github.com/marko-php/marko/pull/281
* feat: back off between transaction retries and retry mariadb 1020 conflicts by @markshust in https://github.com/marko-php/marko/pull/284
* feat: fail the boot when #[Can] routes exist but the guard cannot be built by @markshust in https://github.com/marko-php/marko/pull/286
* feat: read config env values through a typed Env reader that rejects invalid input by @markshust in https://github.com/marko-php/marko/pull/334
* feat: read generated keys back on mariadb with insert returning by @markshust in https://github.com/marko-php/marko/pull/339
* feat: deprecate the global env() helper and drop stale marko/env requires by @markshust in https://github.com/marko-php/marko/pull/348
* feat: name the config file in errors thrown while it loads by @markshust in https://github.com/marko-php/marko/pull/349
### Bug Fixes
* fix: retry the packagist update on transient upstream failures by @markshust in https://github.com/marko-php/marko/pull/148
* fix: register marko/testing pest expectations through a pest plugin by @markshust in https://github.com/marko-php/marko/pull/190
* fix: enforce #[Can] authorization via global middleware by @markshust in https://github.com/marko-php/marko/pull/191
* fix: make the scheduler run boot-registered tasks, add overlap protection and schedule:work by @markshust in https://github.com/marko-php/marko/pull/193
* fix: make redis and rabbitmq drivers honor their config by @markshust in https://github.com/marko-php/marko/pull/195
* fix: share the database connection and entity hydrator across repositories by @markshust in https://github.com/marko-php/marko/pull/199
* fix: make db:migrate and db:rebuild safe in production by @markshust in https://github.com/marko-php/marko/pull/211
* fix: apply and reverse column default and nullability changes in migrations by @markshust in https://github.com/marko-php/marko/pull/243
* fix: share one redis connection across pubsub-redis subscriptions by @markshust in https://github.com/marko-php/marko/pull/245
* fix: make devserver process stop deterministic and its tests flake-free by @markshust in https://github.com/marko-php/marko/pull/248
* fix: fail a job with an invalid backoff instead of stopping the worker by @markshust in https://github.com/marko-php/marko/pull/258
* fix: serve never-expiring page cache entries and reject negative ttls by @markshust in https://github.com/marko-php/marko/pull/277
* fix: report 4xx/5xx bodies from mercure, pusher and webhook senders by @markshust in https://github.com/marko-php/marko/pull/283
* fix: keep accepted lengths, defaults and native definitions in mysql column modifications by @markshust in https://github.com/marko-php/marko/pull/287
* fix: detect devserver startup failures without fixed sleeps by @markshust in https://github.com/marko-php/marko/pull/288
* fix: send the stateless guard's challenge on #[Can] 401s by @markshust in https://github.com/marko-php/marko/pull/298
* fix: cast postgresql type changes and support expression column defaults by @markshust in https://github.com/marko-php/marko/pull/301
* fix: apply webhook.timeout to deliveries and cap recorded success bodies by @markshust in https://github.com/marko-php/marko/pull/308
* fix: bind the admin permission registry as a shared singleton by @markshust in https://github.com/marko-php/marko/pull/309
* fix: generate uniqueness changes and settle mysql introspected types and defaults by @markshust in https://github.com/marko-php/marko/pull/310
* fix: roll back started services when marko up fails and support IPv6 hosts by @markshust in https://github.com/marko-php/marko/pull/312
* fix: change the sequence type with a postgresql auto-increment key by @markshust in https://github.com/marko-php/marko/pull/317
* fix: answer admin guests on a stateless guard with a 401 instead of a login redirect by @markshust in https://github.com/marko-php/marko/pull/319
* fix: throw a helpful AdminException when a class lacks #[AdminSection] by @markshust in https://github.com/marko-php/marko/pull/320
* fix: settle expression defaults the database rewrites so db:diff reaches zero by @markshust in https://github.com/marko-php/marko/pull/322
* fix: discover admin sections and permissions at boot by @markshust in https://github.com/marko-php/marko/pull/326
* fix: shorten derived index and foreign key names over 63 bytes by @markshust in https://github.com/marko-php/marko/pull/328
* fix: compile mariadb shared-lock modifiers as lock in share mode by @markshust in https://github.com/marko-php/marko/pull/332
* fix: step mysql batch insert ids by auto_increment_increment and document returning row order by @markshust in https://github.com/marko-php/marko/pull/351
* fix: add primary-key columns to existing tables together with their key by @markshust in https://github.com/marko-php/marko/pull/352
* fix: quote raw sql identifiers through the connection in every package by @markshust in https://github.com/marko-php/marko/pull/353
* fix: sync admin user roles in one transaction with batched inserts by @markshust in https://github.com/marko-php/marko/pull/354
### Documentation
* docs: embed introduction video on the Introduction page by @markshust in https://github.com/marko-php/marko/pull/155
* docs: remove scanlines and default captions from the intro video by @markshust in https://github.com/marko-php/marko/pull/157
* docs: fix wrong snippets in readmes and docs and guard class references by @markshust in https://github.com/marko-php/marko/pull/189
* docs: show env() and Env side by side in the env docs by @markshust in https://github.com/marko-php/marko/pull/360
### Refactoring
* refactor: use query builder row locks and savepoints in DatabaseQueue and RoleRepository by @markshust in https://github.com/marko-php/marko/pull/239
* refactor: read time through the psr-20 clock in the cache drivers and rate limiter by @markshust in https://github.com/marko-php/marko/pull/270
* refactor: read time through the psr-20 clock in notification, broadcasting, media and sse by @markshust in https://github.com/marko-php/marko/pull/271
* refactor: throw http exceptions from admin auth middleware by @markshust in https://github.com/marko-php/marko/pull/282
### Testing
* test: add an integration suite that boots a real app against postgres and redis by @markshust in https://github.com/marko-php/marko/pull/204
* test: use shared fakes in broadcasting and roadrunner tests by @markshust in https://github.com/marko-php/marko/pull/250
* test: run the real-driver suites in ci and turn the known-gap todos into tests by @markshust in https://github.com/marko-php/marko/pull/262
* test: make pest plugin and amphp sse server tests deterministic under parallel load by @markshust in https://github.com/marko-php/marko/pull/311
* test: run the mysql driver integration suite against mariadb 11.8 in ci by @markshust in https://github.com/marko-php/marko/pull/318
* test: fail the suite on notices, deprecations, risky tests and warnings by @markshust in https://github.com/marko-php/marko/pull/357
### CI
* ci(docs): deploy docs when docs-markdown content changes by @markshust in https://github.com/marko-php/marko/pull/156

## New Contributors
* @etienne-scherrer made their first contribution in https://github.com/marko-php/marko/pull/158


## [0.8.5] - 2026-07-26

### New Features
* feat: surface Claude Code multi-instance config-isolation tip on devai:install by @markshust in https://github.com/marko-php/marko/pull/143
* feat: add /release skill for version assessment and release execution by @markshust in https://github.com/marko-php/marko/pull/146
### Bug Fixes
* fix: support union-typed entity columns via explicit Column type by @TuVanDev in https://github.com/marko-php/marko/pull/145
### Documentation
* docs: correct module/plugin naming convention in skills by @markshust in https://github.com/marko-php/marko/pull/142
* docs: correct multi-instance MCP guidance to lead with per-project scoping by @markshust in https://github.com/marko-php/marko/pull/144
### CI
* ci: gate every PR on tests, lint, and static analysis by @markshust in https://github.com/marko-php/marko/pull/147

## New Contributors
* @TuVanDev made their first contribution in https://github.com/marko-php/marko/pull/145


## [0.8.4] - 2026-06-24

### New Features
* feat: make a fresh skeleton work with zero bootstrapping by @markshust in https://github.com/marko-php/marko/pull/139
* feat: self-refreshing mcp/lsp code index by @markshust in https://github.com/marko-php/marko/pull/141
### Bug Fixes
* fix: show devai:install prompt text and stream live progress by @markshust in https://github.com/marko-php/marko/pull/138
* fix: prevent devai:install composer hang on invisible prompt by @markshust in https://github.com/marko-php/marko/pull/140


## [0.8.3] - 2026-06-24

### Bug Fixes
* fix: remove orphaned devai bootstrap shim from skeleton src by @markshust in https://github.com/marko-php/marko/pull/137


## [0.8.2] - 2026-06-24

### New Features
* feat(devai): offer to install a docs search driver during devai:install by @markshust in https://github.com/marko-php/marko/pull/133
* feat: add marko/testing as a skeleton dev dependency by @markshust in https://github.com/marko-php/marko/pull/134
* feat: warm caches on install and add MCP handshake timeout by @markshust in https://github.com/marko-php/marko/pull/135
### Documentation
* docs: clarify discovery cache vs code index and when a reindex is needed by @markshust in https://github.com/marko-php/marko/pull/136
### CI
* ci: retry split-repo pushes on concurrent ref-lock by @markshust in https://github.com/marko-php/marko/pull/131
### Maintenance
* chore: remove deprecated marko/docs-vec driver in favor of docs-fts by @markshust in https://github.com/marko-php/marko/pull/132


## [0.8.1] - 2026-06-24

### New Features
* feat: tier 1 — close six critical/high security audit findings by @markshust in https://github.com/marko-php/marko/pull/116
* feat: tier 2 — fix ten high-severity correctness defects by @markshust in https://github.com/marko-php/marko/pull/117
* feat: tier 3 — fix twenty medium-severity defects by @markshust in https://github.com/marko-php/marko/pull/118
* feat: tier 4 — eliminate N+1 query loops on hot paths by @markshust in https://github.com/marko-php/marko/pull/119
* feat: tier 5 — harden twenty-four low-severity gaps by @markshust in https://github.com/marko-php/marko/pull/120
* feat: compiled discovery cache (+ recovered tier2 tokenizer prerequisite) by @markshust in https://github.com/marko-php/marko/pull/121
* feat: make devai guideline files marker-based and fully user-overridable by @markshust in https://github.com/marko-php/marko/pull/124
### Bug Fixes
* fix: ship a correct .gitignore to generated projects by @markshust in https://github.com/marko-php/marko/pull/123
* fix(marko-skills): derive scaffold vendor from project dir, never marko by @markshust in https://github.com/marko-php/marko/pull/126
* fix(docs-fts): sanitize natural-language queries into safe FTS5 expressions by @markshust in https://github.com/marko-php/marko/pull/127
* fix(docs-vec): make the hybrid driver buildable and runnable on stock PHP by @markshust in https://github.com/marko-php/marko/pull/130
### Documentation
* docs: catalog 0.8.0 packages in main README by @markshust in https://github.com/marko-php/marko/pull/110
* docs: document GitHub Actions version convention by @markshust in https://github.com/marko-php/marko/pull/113
* docs: add audit remediation implementation plans by @markshust in https://github.com/marko-php/marko/pull/114
* docs: fold remaining audit findings into remediation plans by @markshust in https://github.com/marko-php/marko/pull/115
* docs: document devai marker-based override model in agent docs by @markshust in https://github.com/marko-php/marko/pull/125
* docs: improve search_docs ranking for module-system and config queries by @markshust in https://github.com/marko-php/marko/pull/129
### CI
* ci: add README package catalog drift check by @markshust in https://github.com/marko-php/marko/pull/111
* ci: bump actions/checkout to v6 in readme drift check by @markshust in https://github.com/marko-php/marko/pull/112
### Maintenance
* chore: migrate hcf pipeline to agent frontmatter and prune stray docs by @markshust in https://github.com/marko-php/marko/pull/122


## [0.8.0] - 2026-06-03

### New Features
* feat: add marko/codeindexer (with interface subtraction) by @markshust in https://github.com/marko-php/marko/pull/97
* feat: add marko/claude-plugins by @markshust in https://github.com/marko-php/marko/pull/99
* feat: add marko/docs (documentation search contract) by @markshust in https://github.com/marko-php/marko/pull/100
* feat: add marko/docs-markdown (docs content as a module) by @markshust in https://github.com/marko-php/marko/pull/101
* feat: add marko/lsp by @markshust in https://github.com/marko-php/marko/pull/102
* feat: add marko/docs-fts + marko/docs-vec (docs search drivers) by @markshust in https://github.com/marko-php/marko/pull/103
* feat: add marko/mcp (PersistLastErrorPlugin + LastErrorTool dropped, Runtime/Contracts flattened) by @markshust in https://github.com/marko-php/marko/pull/104
* feat: add marko/devai (4 marker interfaces collapsed into single install()) by @markshust in https://github.com/marko-php/marko/pull/105
### Bug Fixes
* fix: raise memory_limit in composer test scripts by @markshust in https://github.com/marko-php/marko/pull/107
* fix: raise memory_limit in release.sh test invocation by @markshust in https://github.com/marko-php/marko/pull/108
* fix: raise memory_limit in IntegrationVerificationTest pest subprocesses by @markshust in https://github.com/marko-php/marko/pull/109
### Documentation
* docs: add marko/codeindexer reference page + fix README doc link by @markshust in https://github.com/marko-php/marko/pull/98
* docs: widen sidebar + fix Claude Code install method + clarify MCP verification by @markshust in https://github.com/marko-php/marko/pull/106
### Other Changes
* rename: rate-limiting → ratelimiter and dev-server → devserver by @markshust in https://github.com/marko-php/marko/pull/96


## [0.7.0] - 2026-05-27

### Breaking Changes
* feat: extract admin-panel templates into engine-specific sibling packages by @markshust in https://github.com/marko-php/marko/pull/94
### New Features
* feat(database): add selectRaw, whereRaw, and orderByRaw to QueryBuilderInterface by @michalbiarda in https://github.com/marko-php/marko/pull/78
* feat(core): allow packages to declare global middleware in module.php by @michalbiarda in https://github.com/marko-php/marko/pull/80
* feat(database-readwrite): add marko/database-readwrite package by @markshust in https://github.com/marko-php/marko/pull/86
* feat: add Twig template engine driver as sibling to Latte by @markshust in https://github.com/marko-php/marko/pull/88
* feat: centralize driver registries with known-drivers.php pattern by @markshust in https://github.com/marko-php/marko/pull/91
### Bug Fixes
* fix(docs): resolve expressive-code build warnings for latte and env by @markshust in https://github.com/marko-php/marko/pull/83
* fix: point split workflow at MARKO_BUILD_PAT secret by @markshust in https://github.com/marko-php/marko/pull/84
* fix: drop workflow-file PUT that 404s on freshly created split repos by @markshust in https://github.com/marko-php/marko/pull/87
* fix: harden 0.7.0 release (lint config, test fixes, dep refresh) by @markshust in https://github.com/marko-php/marko/pull/95
### Documentation
* docs: document boot-callback dialect override pattern for postgres-wire-compatible databases by @markshust in https://github.com/marko-php/marko/pull/85
### Refactoring
* refactor(view): align ViewInterface bindings with Marko's simple-binding preference by @markshust in https://github.com/marko-php/marko/pull/90
* refactor(view): drop mutual conflict; align with multi-driver pattern by @markshust in https://github.com/marko-php/marko/pull/92


## [0.6.1] - 2026-05-26

### Bug Fixes
* fix(database): use SchemaRegistry in DiffCommand and MigrateCommand for extender merge by @michalbiarda in https://github.com/marko-php/marko/pull/67
* fix(database-pgsql): normalise jsonb → json in introspector type map by @michalbiarda in https://github.com/marko-php/marko/pull/69
* fix(database-pgsql): JSON-encode array bindings before passing to PDO by @michalbiarda in https://github.com/marko-php/marko/pull/72
* fix(database): link entity extenders at boot so companions hydrate during HTTP requests by @michalbiarda in https://github.com/marko-php/marko/pull/74
* fix(database-mysql): JSON-encode array bindings before passing to PDO by @markshust in https://github.com/marko-php/marko/pull/82


## [0.6.0] - 2026-05-12

### New Features
* feat(page-cache): full-page HTTP cache with file driver, tag invalidation, and entity bridge by @michalbiarda in https://github.com/marko-php/marko/pull/58
* feat(routing): memoize RouteMatcher and share one instance with Router by @markshust in https://github.com/marko-php/marko/pull/62
* feat: extend existing entity tables via #[Table(extends:)] by @markshust in https://github.com/marko-php/marko/pull/64
### Bug Fixes
* fix(tests): align RepoManagementScriptsTest with batched gh repo list by @markshust in https://github.com/marko-php/marko/pull/60
* fix: auto-create missing split repos and cancel superseded runs by @markshust in https://github.com/marko-php/marko/pull/65
### Maintenance
* chore(release): batch split-repo lookup and auto-create from release.sh by @markshust in https://github.com/marko-php/marko/pull/56

## New Contributors
* @michalbiarda made their first contribution in https://github.com/marko-php/marko/pull/58


## [0.5.0] - 2026-05-01

### New Features
* feat: Add marko/inertia-react package by @ps-carvalho in https://github.com/marko-php/marko/pull/51
* feat: Add marko/inertia-vue package by @ps-carvalho in https://github.com/marko-php/marko/pull/52
* feat: Add marko/inertia-svelte package by @ps-carvalho in https://github.com/marko-php/marko/pull/53
### Bug Fixes
* fix(tests): eliminate uniqid() parallel-flake and PHP 8.5 setAccessible() deprecation by @markshust in https://github.com/marko-php/marko/pull/55
### Maintenance
* chore: sort root composer.json replace and autoload-dev alphabetically by @markshust in https://github.com/marko-php/marko/pull/54


## [0.4.2] - 2026-05-01

### Bug Fixes
* fix: harden release pipeline (deterministic changelog, self-healing Packagist) by @markshust in https://github.com/marko-php/marko/pull/50


## [0.4.1] - 2026-05-01

### Bug Fixes
* fix: push main before generating release notes by @markshust in https://github.com/marko-php/marko/pull/49


## [0.4.0] - 2026-05-01

### New Features
* feat: close database layer gaps for 1.0 by @markshust in https://github.com/marko-php/marko/pull/40
* feat: Add marko/vite package by @ps-carvalho in https://github.com/marko-php/marko/pull/42
* feat: add marko/debugbar package by @ps-carvalho in https://github.com/marko-php/marko/pull/43
* feat: Add marko/inertia package by @ps-carvalho in https://github.com/marko-php/marko/pull/47
### Documentation
* docs: use composer test for faster local test runs by @markshust in https://github.com/marko-php/marko/pull/38
* docs: document integration-destructive group and run it in release script by @markshust in https://github.com/marko-php/marko/pull/39
* docs: expand PR review process with package-PR checklist by @markshust in https://github.com/marko-php/marko/pull/46
### Refactoring
* refactor: preference discovery and class extraction logic by @iamlasse in https://github.com/marko-php/marko/pull/44
* refactor: offload plugin discovery to PluginDiscovery class by @iamlasse in https://github.com/marko-php/marko/pull/45
### Maintenance
* feat: add CHANGELOG.md with release-script automation by @markshust in https://github.com/marko-php/marko/pull/48

## New Contributors
* @ps-carvalho made their first contribution in https://github.com/marko-php/marko/pull/42
* @iamlasse made their first contribution in https://github.com/marko-php/marko/pull/44

## [0.3.1] - 2026-04-21

### Bug Fixes
- `save()` silently skipped update for entities inserted in the same request ([#37](https://github.com/marko-php/marko/pull/37))

### Documentation
- Fix database config examples to use flat format ([#33](https://github.com/marko-php/marko/pull/33))
- Add intro video to README after Why Marko section ([#34](https://github.com/marko-php/marko/pull/34))
- Fix code-standards violations across tutorials and guides ([#35](https://github.com/marko-php/marko/pull/35))

## [0.3.0] - 2026-04-15

### Breaking Changes
- Auto-convert camelCase property names to snake_case column names ([#30](https://github.com/marko-php/marko/pull/30))

### New Features
- Add `route:list` CLI command to `marko/routing` ([#25](https://github.com/marko-php/marko/pull/25))
- Add `doc-updater` to post-implementation pipeline ([#26](https://github.com/marko-php/marko/pull/26))
- Add ORM relationships, collections, and query specifications ([#28](https://github.com/marko-php/marko/pull/28))
- Allow overriding host for dev server up ([#11](https://github.com/marko-php/marko/pull/11))
- Optional TLS for database connections ([#6](https://github.com/marko-php/marko/pull/6))

### Bug Fixes
- Use DI container to instantiate layout components ([#29](https://github.com/marko-php/marko/pull/29))

### Documentation
- Add layout package to README, remove blog reference ([#23](https://github.com/marko-php/marko/pull/23))

## [0.2.0] - 2026-04-10

### New Features
- Add issue type to bug report and feature request templates ([#19](https://github.com/marko-php/marko/pull/19))
- Attribute-driven layout system ([#20](https://github.com/marko-php/marko/pull/20))

### Bug Fixes
- Issue template dropdown validation errors ([#16](https://github.com/marko-php/marko/pull/16))
- Restore package options to issue template dropdowns ([#22](https://github.com/marko-php/marko/pull/22))

### Refactoring
- Remove `marko/blog` package from framework ([#21](https://github.com/marko-php/marko/pull/21))

## [0.1.3] - 2026-04-06

### Bug Fixes
- Derive repo from git remote for `gh release create` ([#14](https://github.com/marko-php/marko/pull/14))
- Replace `PluginProxy` with generated interceptor classes ([#15](https://github.com/marko-php/marko/pull/15))

## [0.1.2] - 2026-04-05

### Breaking Changes
- Preload project autoloader in `bin` ([#10](https://github.com/marko-php/marko/pull/10))
- Add missing `QueryBuilderFactoryInterface` implementation in `marko/database-mysql` ([#7](https://github.com/marko-php/marko/pull/7))

### New Features
- Integrate plugin interception into container resolution ([#12](https://github.com/marko-php/marko/pull/12))

### Documentation
- Add PR review process guide ([#13](https://github.com/marko-php/marko/pull/13))

## [0.1.1] - 2026-04-05

### Maintenance
* fix(split): notify Packagist after tag push to prevent missed updates by @markshust in https://github.com/marko-php/marko/pull/2
* feat(release-workflow): add automated release workflow and contribution conventions by @markshust in https://github.com/marko-php/marko/pull/5

## [0.1.0] - 2026-03-30

Initial public-ready release. Improved first-application guide flow and clarity, added `marko open` command, and clarified `app/foo` directory creation.

## [0.0.2] - 2026-03-26

Standardized `NoDriverException` across all interface packages. Each interface package ships its own `NoDriverException` with a `DRIVER_PACKAGES` constant listing known implementations; the container detects and throws these specific exceptions instead of the generic `BindingException`.

## [0.0.1] - 2026-03-25

First tagged release. Established `integration-destructive` test group with `--parallel` execution to prevent OOM, and configured the release script to exclude that group during normal test runs.
