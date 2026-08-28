# marko/roadrunner

RoadRunner application server driver for Marko --- serves your application from one long-running PHP worker process instead of spawning a new process per request.

## Installation

```bash
composer require marko/roadrunner
composer require spiral/roadrunner-cli --dev
./vendor/bin/rr get-binary
```

## Quick Example

```bash
marko rr:serve
```

The first run generates a `.rr.yaml` pointing at `vendor/marko/roadrunner/worker.php`, then starts the server.

## Documentation

Full usage, the reset lifecycle, unsupported packages, and API reference: [marko/roadrunner](https://marko.build/docs/packages/roadrunner/)
