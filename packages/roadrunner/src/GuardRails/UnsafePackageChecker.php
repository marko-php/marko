<?php

declare(strict_types=1);

namespace Marko\Roadrunner\GuardRails;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Roadrunner\Exceptions\UnsafePackageException;

readonly class UnsafePackageChecker
{
    private const string SSE_PACKAGE = 'marko/sse';

    private const string DEBUGBAR_PACKAGE = 'marko/debugbar';

    private const string ACKNOWLEDGED_UNSAFE_PACKAGES_CONFIG_KEY = 'roadrunner.acknowledged_unsafe_packages';

    public function __construct(
        private ModuleRepositoryInterface $moduleRepository,
        private ConfigRepositoryInterface $configRepository,
    ) {}

    /**
     * @return array<string> Warning messages for unsafe packages that were acknowledged rather than refused
     * @throws UnsafePackageException|ConfigNotFoundException
     */
    public function check(): array
    {
        $installedPackageNames = array_map(
            fn ($module): string => $module->name,
            $this->moduleRepository->all(),
        );

        $warnings = [];

        if (in_array(self::SSE_PACKAGE, $installedPackageNames, true)) {
            $warnings[] = $this->checkSse();
        }

        if (in_array(self::DEBUGBAR_PACKAGE, $installedPackageNames, true)) {
            $warnings[] = $this->warnDebugbar();
        }

        return $warnings;
    }

    /**
     * @throws UnsafePackageException|ConfigNotFoundException
     */
    private function checkSse(): string
    {
        $acknowledged = $this->configRepository->getArray(self::ACKNOWLEDGED_UNSAFE_PACKAGES_CONFIG_KEY);

        if (!in_array(self::SSE_PACKAGE, $acknowledged, true)) {
            throw UnsafePackageException::incompatibleWithWorkerMode(
                package: self::SSE_PACKAGE,
                reason: 'it streams a response for the life of the connection via ob_end_flush()/flush(), which is incompatible with a request/response worker that must return to the accept loop',
                configKey: self::ACKNOWLEDGED_UNSAFE_PACKAGES_CONFIG_KEY,
            );
        }

        return sprintf(
            "Package '%s' was acknowledged via config key '%s' and is allowed to boot, but it remains incompatible with a long-running worker: it streams a response for the life of the connection via ob_end_flush()/flush(). "
                . 'The boot-time check is only a courtesy — the real protection is the per-request bridge, which throws when a StreamingResponse reaches it under this worker. Expect a loud 500 on any SSE route, not a silently truncated stream.',
            self::SSE_PACKAGE,
            self::ACKNOWLEDGED_UNSAFE_PACKAGES_CONFIG_KEY,
        );
    }

    private function warnDebugbar(): string
    {
        return sprintf(
            "Package '%s' is installed. It is a dev-only tool that reads \$_SERVER directly and calls ob_start() once at boot, which does not fit a long-running worker cleanly. Booting anyway — do not enable it in a worker-served production environment.",
            self::DEBUGBAR_PACKAGE,
        );
    }
}
