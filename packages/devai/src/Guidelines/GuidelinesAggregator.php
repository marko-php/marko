<?php

declare(strict_types=1);

namespace Marko\DevAi\Guidelines;

use Marko\CodeIndexer\Module\ModuleWalker;
use Marko\Config\ConfigRepositoryInterface;
use Marko\DevAi\Exceptions\DevAiInstallException;

readonly class GuidelinesAggregator
{
    private const string GUIDELINES_REL_PATH = '/resources/ai/guidelines.md';

    private const string FIRST_PARTY_PREFIX = 'marko/';

    private const string ALLOW_PACKAGES_KEY = 'devai.guidelines.allow_packages';

    private string $devaiPackageRoot;

    public function __construct(
        private ModuleWalker $walker,
        ?string $devaiPackageRoot = null,
        private ?ConfigRepositoryInterface $config = null,
    ) {
        $this->devaiPackageRoot = $devaiPackageRoot ?? dirname(__DIR__, 2);
    }

    /**
     * Whether a package ships with Marko itself. Guidelines from any other package are
     * third-party: they are labelled as such in the rendered output and are subject
     * to the devai.guidelines.allow_packages allowlist.
     */
    public static function isFirstParty(
        string $packageName,
    ): bool {
        return str_starts_with($packageName, self::FIRST_PARTY_PREFIX);
    }

    /**
     * @return array<string, string> packageName => guidelines markdown
     * @throws DevAiInstallException when devai.guidelines.allow_packages is neither null nor a list of package names
     */
    public function aggregate(): array
    {
        $allowPackages = $this->allowPackages();

        $guidelines = [];

        $coreGuidelinesPath = $this->devaiPackageRoot . '/resources/ai/guidelines/core.md';
        if (is_file($coreGuidelinesPath)) {
            $guidelines['marko/core'] = (string) file_get_contents($coreGuidelinesPath);
        }

        // Discover and aggregate per-package guidelines
        foreach ($this->walker->walk() as $module) {
            if (!$this->isAllowed($module->name, $allowPackages)) {
                continue;
            }

            $path = $module->path . self::GUIDELINES_REL_PATH;
            if (is_file($path)) {
                $guidelines[$module->name] = (string) file_get_contents($path);
            }
        }

        // Sort deterministically: core first, then alphabetical
        $sorted = [];
        if (isset($guidelines['marko/core'])) {
            $sorted['marko/core'] = $guidelines['marko/core'];
            unset($guidelines['marko/core']);
        }
        ksort($guidelines);

        return $sorted + $guidelines;
    }

    /**
     * @param list<string>|null $allowPackages
     */
    private function isAllowed(
        string $packageName,
        ?array $allowPackages,
    ): bool {
        return $allowPackages === null
            || self::isFirstParty($packageName)
            || in_array($packageName, $allowPackages, true);
    }

    /**
     * @return list<string>|null null when every package is allowed
     * @throws DevAiInstallException
     */
    private function allowPackages(): ?array
    {
        if ($this->config?->has(self::ALLOW_PACKAGES_KEY) !== true) {
            return null;
        }

        $value = $this->config->get(self::ALLOW_PACKAGES_KEY);

        if ($value === null) {
            return null;
        }

        if (
            !is_array($value)
            || !array_is_list($value)
            || array_filter($value, 'is_string') !== $value
        ) {
            throw DevAiInstallException::invalidConfig(
                self::ALLOW_PACKAGES_KEY,
                'a list of package names (e.g. [\'acme/blog\'])',
                $value,
            );
        }

        return $value;
    }
}
