# Plan: Tier 2 — High-Severity Correctness Defects

## Created
2026-06-10

## Status
ready

## Objective
Remediate eight high-severity correctness defects that silently break core Marko
features (plugins on DI-constructed classes, advanced error handling, class
discovery, async observers, both queue drivers, the SMTP mail driver, read/write
splitting, and PostgreSQL batch inserts) so each feature does what its contract
promises, with loud failures instead of silent corruption.

## Related Issues
none

## Discovery Notes
- **New plan.** No existing `tier2-high-correctness` task files; the directory was
  empty at authoring time.
- **F1 supersedes the deferred slice of `plugin-interceptor-proxy-fix`.** That plan
  is fully `completed` (tasks 001–008), and its interface-wrapper strategy works
  (the wrapper calls `new $className()` then `initInterception($target, ...)`, so
  it delegates to a fully constructed `$target`). But its task 005 *explicitly
  deferred* the concrete-subclass-with-constructor case: "This is complex — for now,
  prefer interface wrapper when possible." The bug the audit cites still lives in
  `PluginInterceptor.php` (the concrete-subclass branch does `new $className()` with
  zero args → the generated subclass extends a target whose promoted constructor has
  mandatory args → `ArgumentCountError`) and in `InterceptorClassGenerator`
  (generated subclass declares no constructor). **Task 001 supersedes that deferred
  slice only**; it does NOT re-touch the interface-wrapper path, the trait, the
  registry, or container wiring delivered by the prior plan.
- **Locked decision (F2):** each error driver binds `ErrorHandlerInterface`
  *independently*. Per the container architecture, two installed modules binding the
  same interface is a deliberate, loud `BindingConflictException` ("install exactly
  one error driver"). So we do NOT make errors-advanced share/extend errors-simple's
  binding — we keep errors-advanced binding the interface itself and make its handler
  a real, self-sufficient implementation. errors-advanced may keep *code-level* reuse
  of errors-simple `src/` classes (Environment, CodeSnippetExtractor, formatters) via
  its composer `require`; the conflict is solely that both `module.php` files bind the
  interface, which is correct by-design when only one driver is installed.
- **Locked decision (F7 — SMTP socket testing):** protocol-logic tests (connect →
  EHLO → STARTTLS → AUTH → MAIL/RCPT/DATA, dot-stuffing, case-insensitive auth) run
  against a fake `SocketInterface` following the existing `createMockSocket`
  pattern already used in `mail-smtp/tests`. The new concrete `StreamSocket` gets a
  thin, opt-in integration test grouped `integration-destructive` (it opens a real
  TCP stream) so the fast `composer test` run never depends on a live SMTP server.
- **F3 / discovery-cache ordering:** the standalone `discovery-cache` plan builds a
  cache *on top of* discovery results. It must land AFTER this fix (Task 003) so the
  cache never persists silently-dropped classes. Stated again in Task 003.
- **Tier 1 rebase note:** the queue tasks here (F4 RabbitMQ, F5 DatabaseQueue, F6
  AsyncObserverJob) touch files that Tier 1's `unserialize()` hardening also touches
  (`unserialize($message->getBody())`, `unserialize($row['payload'])`,
  `unserialize($this->eventData)`). When Tier 1 lands first, rebase these tasks onto
  the hardened `unserialize` seam (allowed-classes / safe-unserialize helper) rather
  than re-introducing raw `unserialize`. These tasks change attempt/reservation/
  delegation semantics, not the deserialization call site itself.
- **F9 design constraint:** `ConnectionInterface` has NO driver/dialect accessor
  today (confirmed: only `connect/disconnect/isConnected/query/execute/prepare/
  lastInsertId`). A correct per-dialect batch-insert id strategy therefore needs a
  dialect signal. Task 009 adds a minimal driver-name accessor to the connection
  contract (and its drivers/read-write wrapper) so `insertBatch` can choose
  `INSERT ... RETURNING` (pgsql) vs the `LAST_INSERT_ID + offset` strategy (mysql).

## Scope
### In Scope
- F1: concrete-subclass interceptor instantiation for targets with constructor DI.
- F2: errors-advanced standalone boot + real `register()`/`handleError()`.
- F3: tokenizer-based class-name extraction in `ClassFileParser`.
- F4: RabbitMQ attempt persistence, release-to-correct-queue, failed-job store redesign.
- F5: DatabaseQueue atomic reserve, reservation-timeout reclaim, single attempt source.
- F6: AsyncObserverJob self-resolving observer invocation + Worker wiring.
- F7: real `StreamSocket`, factory connect/auth/STARTTLS sequence, case-insensitive
  auth, DATA dot-stuffing, `SmtpConfig` default removal, `module.php` socket binding.
- F8: read/write `transaction()` sticky-write, write-statement routing to primary,
  replica-selection safety after fallback removal.
- F9: PostgreSQL-correct `insertBatch()` primary-key assignment via `RETURNING`.

### Out of Scope
- The interface-wrapper strategy, plugin registry, trait, and container wiring already
  delivered by `plugin-interceptor-proxy-fix` (F1 touches only the concrete-subclass path).
- New mail features beyond making the existing SMTP driver actually send.
- The `discovery-cache` feature itself (separate plan; depends on Task 003).
- Tier 1's `unserialize` hardening (separate tier; rebase note above).
- Whoops-style rendering changes in errors-advanced beyond wiring `register()`.
- README/docs pages (handled by the doc-updater pipeline).

## Success Criteria
- [ ] A `#[Before]`/`#[After]` plugin works on a concrete class whose constructor has
      mandatory promoted dependencies, delegating to the real target instance + state.
- [ ] An app booting with ONLY errors-advanced installs working error/exception
      handlers and surfaces warnings/notices/deprecations loudly (never swallows).
- [ ] Discovery finds classes whose files contain the word "class" in docblocks/
      strings, namespaced classes, `final`/`abstract`/`readonly` classes, enums; and
      returns null for class-less files — no silent drops.
- [ ] An `#[Observer(async: true)]` observer actually executes when its queued job runs.
- [ ] DatabaseQueue never lets two workers run the same job; crashed-worker
      reservations are reclaimed after timeout; `maxAttempts` means N executions.
- [ ] RabbitMQ jobs stop after `maxAttempts` and land in the failed-job store; the
      failed-job repo `all()/find()/delete()` terminate without livelock; releases
      return to the originating queue.
- [ ] SMTP driver sends a real message: factory connects/authenticates;
      case-insensitive AUTH; STARTTLS checks the 220 reply; DATA dot-stuffs lines
      beginning with `.`; multi-recipient envelopes work.
- [ ] Writes inside `transaction()` and `INSERT ... RETURNING` route to the primary;
      replica selection never indexes a removed replica.
- [ ] `insertBatch()` assigns each entity its true DB id on both mysql and pgsql.
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | F1: concrete-subclass interceptor instantiation for constructor-DI targets (core/Plugin) | - | pending |
| 002 | F2: errors-advanced standalone boot + real register()/handleError() | - | pending |
| 003 | F3: tokenizer-based ClassFileParser::extractClassName (core/Discovery) | - | pending |
| 004 | F6: AsyncObserverJob self-resolves + invokes observer; Worker gains ContainerInterface dep + wiring (queue) | - | pending |
| 005 | F5: DatabaseQueue atomic reserve + reservation-timeout reclaim + attempt source (queue-database) | 004, 009 | pending |
| 006 | F4a: RabbitmqQueue attempt persistence + release-to-origin-queue + per-queue declare (queue-rabbitmq) | 004 | pending |
| 007 | F4b: RabbitmqFailedJobRepository non-livelocking store (queue-rabbitmq) | 006 | pending |
| 008 | F8: ReadWriteConnection transaction sticky-write + write routing + replica selection safety (database-readwrite) | 009 | pending |
| 009 | F9: Repository::insertBatch RETURNING-based PK assignment + ConnectionInterface::driverName() accessor (database, ~43 stub implementers) | - | pending |
| 010 | F7a: concrete StreamSocket implements SocketInterface (mail-smtp) | - | pending |
| 011 | F7b: factory connect→EHLO→STARTTLS(220)→AUTH(case-insensitive) sequence + SmtpConfig defaults removal + module binding (mail-smtp) | 010 | pending |
| 012 | F7c: DATA dot-stuffing + multi-recipient correctness (mail-smtp) | 011 | pending |

Parallel batches (each task is a distinct file-cluster; 008 and 005 now serialize
after 009 because 009's `ConnectionInterface::driverName()` addition edits the same
`ReadWriteConnection.php` / queue-database + readwrite test-stub files those tasks touch,
and the abstract-method addition must land first or the suites fail to load):
```
Batch 1: 001  002  003  004  009  010
Batch 2: 005 (←004,009)   006 (←004)   008 (←009)   011 (←010)
Batch 3: 007 (←006)   012 (←011)
```
Note: 005, 008, and 009 all run in batches such that 009 (Batch 1) completes before 005
and 008 (Batch 2). They must NOT be parallelized with 009 — they share files with it.

## Architecture Notes
- **F1 instantiation strategy (corrected).** The concrete-subclass generated methods
  call `parent::$method(...)` (via the trait's `interceptParentCall`), and non-plugged
  public methods fall through to the parent via inheritance — BOTH execute the parent
  body against `$this` (the interceptor), never against `$target`. So
  `newInstanceWithoutConstructor()` ALONE leaves the interceptor's promoted/typed
  properties uninitialized and the first method touching a constructor-injected property
  throws an uninitialized-property `Error`. The fix:
  `(new ReflectionClass($className))->newInstanceWithoutConstructor()`, THEN
  reflection-copy every property value from `$target` onto the interceptor (private +
  protected, walking the class hierarchy), THEN `initInterception($target, ...)`. After
  the copy the interceptor is a stateful stand-in for `$target`, so `parent::$method` and
  inherited non-plugged methods see real state. Do NOT emit a constructor on the
  generated subclass (it would shadow the parent). The interface-wrapper path (which
  truly delegates to `$target`) stays unchanged. (Approach (b) — a constructor
  passthrough calling `parent::__construct(...)` — also works but must reconstruct
  promoted/variadic/optional args; the state-copy approach is simpler and reuses the
  container's already-built `$target`.)
- **F2 boot.** errors-advanced keeps `bindings => [ErrorHandlerInterface => AdvancedErrorHandler]`
  and a `boot` that calls `register()`. `register()` mirrors `SimpleErrorHandler`:
  `set_exception_handler`, `set_error_handler`, `register_shutdown_function`, storing
  previous handlers; `unregister()` restores them. `handleError()` must respect
  `error_reporting()`, convert to `ErrorException`, and surface non-fatals loudly
  (stderr in CLI) rather than `return true` no-op. errors-advanced and errors-simple
  binding the same interface when both installed is the intended loud conflict.
- **F3 tokenizer.** Replace both `preg_match` calls in `extractClassName` with a
  `token_get_all()` scan: track `T_NAMESPACE` (consume name tokens until `;`/`{`),
  detect `T_CLASS`/`T_INTERFACE`/`T_TRAIT`/`T_ENUM` followed by `T_STRING`, skipping
  `T_CLASS` used as `::class` (preceded by `T_DOUBLE_COLON`) and anonymous classes
  (`new class`). Return the first type declaration's FQN, or null. `final`/`abstract`/
  `readonly` are separate tokens before `T_CLASS`, so the keyword detection is
  unaffected; the win is that the word "class" inside `T_COMMENT`/`T_DOC_COMMENT`/
  `T_CONSTANT_ENCAPSED_STRING` no longer matches.
- **F4 RabbitMQ.** Persist incremented attempts into the republished payload on
  `release()` so `Worker`'s `attempts < maxAttempts` check terminates; release to the
  job's *originating* queue (thread the queue name through `pop()`'s tracking maps,
  not the hardcoded `defaultQueue`). Redesign the failed-job store so reads do not
  `basic_get`+`basic_nack`-requeue the head forever — use AMQP `message_id` (set on
  publish) for `find/delete`, and drain-then-restore or a dedicated store so `all()`
  reads each message exactly once per call.
- **F5 DatabaseQueue.** Reserve atomically: `SELECT ... FOR UPDATE SKIP LOCKED` inside
  the existing transaction wrapper (pgsql + mysql 8 support it), then `UPDATE ... SET
  reserved_at = :now WHERE id = :id AND reserved_at IS NULL` and treat affected-rows=0
  as "lost the race → return null/retry". Reclaim crashed reservations by widening the
  candidate predicate to `reserved_at IS NULL OR reserved_at <= :now - retry_after`
  (`queue.retry_after`). Stop double-counting attempts: the DB increment in `popJob`
  and the `Worker::work()` `incrementAttempts()` must not both count — make the worker
  the single source of truth (or the DB, but exactly one), so `maxAttempts` = N
  executions. Note mysql vs pgsql `SKIP LOCKED` syntax is identical; document the
  `retry_after` config key.
- **F6 AsyncObserverJob.** Give `AsyncObserverJob` access to the container so
  `handle()` resolves the observer (`$container->get($this->observerClass)`) and calls
  `->handle($event)` with NO external resolver. `Worker` gains a new
  `ContainerInterface` constructor dependency (autowired in production; ~10 `new
  Worker(...)` test call sites in WorkerTest + Feature/IntegrationTest must be updated)
  and sets the container on a popped `AsyncObserverJob` (via a `setContainer()` setter,
  guarded by `instanceof AsyncObserverJob`) before calling `handle()`. The container is a
  `private ?ContainerInterface $container = null` property that is null at push/serialize
  time, so `serialize($this)` is safe and no `__serialize`/`__sleep` magic method is
  needed (Marko bans magic methods). `handle()` keeps the `JobInterface::handle(): void`
  signature (the `$resolver` param is removed; the existing resolver-based
  `AsyncObserverJobTest` must be rewritten to use `setContainer()`).
- **F8 read/write.** `transaction()` must set `stickyWrite = true` for the whole
  callback (like `beginTransaction()`), then delegate to `$write->transaction()`, and
  reset sticky in a `finally` so a throwing callback still clears it. (The write driver's
  `transaction()` calls `$callback()` with no args, so user code inside the callback
  calls back through the `ReadWriteConnection` it holds — sticky-write on `$this` is what
  routes those calls to the primary.) Any write-producing statement (INSERT/UPDATE/DELETE,
  and pgsql `INSERT ... RETURNING` which currently flows through `query()`) must route to
  the primary — detect leading INSERT/UPDATE/DELETE in `query()` after trimming leading
  whitespace and stripping a leading SQL comment, case-insensitively (leading-`WITH` CTEs
  are out of scope for v1 — documented). Fix `WeightedReplicaSelector::select()`: it is a
  `readonly class` so it cannot mutate `$weights`; compute the distribution over only the
  first `count($replicas)` weights (re-sum that slice) or select positionally modulo the
  passed-array length, so `$replicas[$index]` is always defined after a fallback removes a
  replica.
- **F9 insertBatch.** Add a `driverName(): string` accessor to `ConnectionInterface`
  (returning a per-driver constant: `'mysql'`/`'pgsql'`; NOT `PDO::ATTR_DRIVER_NAME`
  which needs a live connection). This is an abstract-method addition that ripples to
  ~43 implementers — 3 production (`MySqlConnection`, `PgSqlConnection`,
  `ReadWriteConnection` passthrough) and ~40 test stubs spread across `database`,
  `database-mysql`, `database-pgsql`, `database-readwrite`, `search`, `session-database`,
  `queue-database`, `health`, and `admin-auth`. EVERY stub must gain the method in Task
  009's cycle or those suites fail to load (and Tasks 005/008 must run AFTER 009 because
  they edit the same stub/connection files). In `insertBatch`, for pgsql use `INSERT ...
  RETURNING <pk>` via the row-returning `query()` path and map returned ids positionally
  to entities (loud `BatchInsertException` if the RETURNING row count != entity count);
  for mysql keep `LAST_INSERT_ID()` + offset (valid because mysql guarantees consecutive
  auto-increment for a single multi-row insert). Never use `lastInsertId()` (= `LASTVAL()`
  = last row) as the first id on pgsql.
- **F7 SMTP.** New `StreamSocket implements SocketInterface` using
  `stream_socket_client` (honour `timeout`), `fwrite`/`fgets` for write/read,
  `stream_socket_enable_crypto` for `enableTls`, and `fclose` for close; loud
  `TransportException` on connect/TLS failure. Factory wires
  `connect($host,$port,$encryption)` → `ehlo` → (if STARTTLS advertised / encryption
  == tls) `startTls()` (which must assert a 220 reply before `enableTls`) → re-`ehlo`
  → `authenticate(username,password,mode)` using `SmtpConfig`, with `mode` matched
  case-insensitively (`strtoupper`) so config's lowercase `login`/`plain` work. STARTTLS
  runs only when `encryption === 'tls'`; `'ssl'` is implicit TLS at connect (no STARTTLS).
  DATA dot-stuffs: every line beginning with `.` is doubled before the terminating
  `\r\n.\r\n`. `SmtpConfig` getters drop hardcoded `??` fallbacks for REQUIRED keys
  (`host/port/encryption/timeout/auth_mode`) and throw loudly when missing — but
  `auth_mode` must FIRST be added to `config/mail.php`'s `smtp` block (it is absent
  today), and `username`/`password` REMAIN nullable (they are present-as-null in config
  and represent no-auth SMTP; the "skip auth when no credentials" path depends on null
  being valid — do NOT make them throw).

## Risks & Mitigations
- **F1 generated-code shadowing:** a zero-arg constructor in the generated subclass
  would shadow the parent and break `newInstanceWithoutConstructor`. Mitigation:
  generator must NOT emit a constructor; tests assert plugged + non-plugged calls hit
  the real target and its constructed state.
- **F5 SKIP LOCKED portability:** SQLite (used in some tests) lacks `FOR UPDATE SKIP
  LOCKED`. Mitigation: gate the locking clause on the connection dialect; tests for
  the atomic path use a mysql/pgsql-style fake or are grouped accordingly, and the
  affected-rows guard provides correctness even without row locks.
- **F6 container-in-job:** serializing a container is impossible. Mitigation: inject
  the container after deserialization; never add it as a serialized property; assert
  the job is a no-args-from-payload reconstruction.
- **F7 real socket flakiness:** the live `StreamSocket` test depends on network.
  Mitigation: group it `integration-destructive`; protocol logic is fully covered by
  the fake socket so `composer test` stays deterministic and offline.
- **F8 write detection heuristic:** routing by SQL prefix can misclassify. Mitigation:
  keep `execute()`/`beginTransaction()`/`transaction()` as the authoritative
  write-sticky signals; only `RETURNING` (which must use `query()` to read rows) needs
  prefix detection, scoped narrowly to leading INSERT/UPDATE/DELETE.
- **F4/F5/F6 Tier-1 collision:** rebase onto Tier 1's hardened `unserialize` seam;
  do not reintroduce raw `unserialize`.
- **F9 contract addition:** adding a method to `ConnectionInterface` ripples to ~43
  implementers across NINE packages (database, database-mysql, database-pgsql,
  database-readwrite, search, session-database, queue-database, health, admin-auth).
  Mitigation: Task 009 enumerates every stub-bearing file and updates them in one cycle;
  Tasks 005 and 008 depend on 009 because they edit the same connection/stub files and
  would otherwise hit an abstract-method load error or clobber 009's edits.
- **F2 global-handler leak:** errors-advanced's `register()` installs real
  `set_error_handler`/`set_exception_handler`/`register_shutdown_function`; a test that
  registers without unregistering corrupts sibling tests in the parallel suite.
  Mitigation: every test calling `register()` (incl. the module-boot test) must
  `unregister()` in `afterEach`/`finally`; non-fatals are triggered via
  `trigger_error(E_USER_*)`, not real fatals.
- **F6 Worker contract addition:** adding `ContainerInterface` to `Worker::__construct`
  breaks ~10 `new Worker(...)` test call sites. Mitigation: Task 004 enumerates them
  (WorkerTest 7, Feature/IntegrationTest 3) and the existing resolver-based
  `AsyncObserverJobTest` is rewritten to `setContainer()`.
