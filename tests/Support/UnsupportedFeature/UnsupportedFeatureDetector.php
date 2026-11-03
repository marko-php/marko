<?php

declare(strict_types=1);

namespace Marko\Tests\Support\UnsupportedFeature;

use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;
use PhpToken;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Throwable;
use UnexpectedValueException;

/**
 * Keeps driver-capability exceptions on the unsupported-feature standard.
 *
 * Rule A: a public static factory whose name says unsupported must return a type that
 * implements UnsupportedFeatureExceptionInterface.
 * Rule C: so must a factory whose body says "does not support" / "not supported" / "unsupported".
 * Rule B: every class named Unsupported*Exception implements the interface.
 *
 * Only methods declared on the class itself are checked, so a subclass is not blamed for
 * (or double-reports) the factories it inherits. Classes are read from tokens, then loaded
 * through the autoloader; a class that fails to load is a violation, never skipped.
 */
readonly class UnsupportedFeatureDetector
{
    private const string NAME_PATTERN = '/[Nn]ot[Ss]upported|[Uu]nsupport|[Dd]oes[Nn]ot[Ss]upport|NotSupport/';

    private const string MESSAGE_PATTERN = '/\b(does not|doesn\'t|do not|cannot) support\b|\bnot supported\b|\bunsupported\b/i';

    private const string CLASS_NAME_PATTERN = '/Unsupported\w*Exception$/';

    /**
     * @param list<string> $files
     * @return array<class-string, string> class => file
     */
    public function declaredClasses(array $files): array
    {
        $classes = [];

        foreach ($files as $file) {
            foreach ($this->classNamesIn($file) as $class) {
                $classes[$class] = $file;
            }
        }

        return $classes;
    }

    /**
     * @param list<string> $files
     * @param list<string> $allowlist FQCN::method entries exempt from Rules A and C
     * @return list<array{subject: string, rule: string, message: string}>
     */
    public function scanFactories(
        array $files,
        array $allowlist,
    ): array {
        $violations = [];
        $hits = [];

        foreach ($this->declaredClasses($files) as $class => $file) {
            if (!$this->isLoadable($class)) {
                $violations[] = $this->loadFailure($class, $file);

                continue;
            }

            $reflection = new ReflectionClass($class);

            if (!$reflection->implementsInterface(Throwable::class)) {
                continue;
            }

            foreach ($reflection->getMethods() as $method) {
                if (!$method->isPublic() || !$method->isStatic() || $method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                $subject = $class . '::' . $method->getName();
                $rules = $this->matchedRules($method);

                if ($rules === []) {
                    continue;
                }

                $hits[$subject] = true;

                if (in_array($subject, $allowlist, true) || $this->returnsUnsupportedFeature($method)) {
                    continue;
                }

                $violations[] = $this->factoryViolation($subject, $rules);
            }
        }

        foreach ($allowlist as $entry) {
            if (!isset($hits[$entry])) {
                $violations[] = [
                    'subject' => $entry,
                    'rule' => 'allowlist',
                    'message' => "Stale allowlist entry $entry: the method no longer exists or no longer matches "
                        . 'Rule A or C. Remove the entry from the allowlist.',
                ];
            }
        }

        return $violations;
    }

    /**
     * @param list<string> $files
     * @return list<array{subject: string, rule: string, message: string}>
     */
    public function scanClassNames(array $files): array
    {
        $violations = [];

        foreach ($this->declaredClasses($files) as $class => $file) {
            if (!$this->isLoadable($class)) {
                $violations[] = $this->loadFailure($class, $file);

                continue;
            }

            $shortName = (new ReflectionClass($class))->getShortName();

            if (preg_match(self::CLASS_NAME_PATTERN, $shortName) === 1
                && !is_a($class, UnsupportedFeatureExceptionInterface::class, true)) {
                $violations[] = [
                    'subject' => $class,
                    'rule' => 'B',
                    'message' => "Rule B: $class is named Unsupported*Exception but does not implement "
                        . UnsupportedFeatureExceptionInterface::class . '. Implement the interface '
                        . '(see Marko\Core\Exceptions\UnsupportedFeatureException for the canonical shape).',
                ];
            }
        }

        return $violations;
    }

    /**
     * @param list<array{subject: string, rule: string, message: string}> $violations
     */
    public function describe(array $violations): string
    {
        return implode("\n", array_column($violations, 'message'));
    }

    /**
     * @return list<string>
     */
    private function matchedRules(ReflectionMethod $method): array
    {
        $rules = [];

        if (preg_match(self::NAME_PATTERN, $method->getName()) === 1) {
            $rules[] = 'A';
        }

        if (preg_match(self::MESSAGE_PATTERN, $this->bodyOf($method)) === 1) {
            $rules[] = 'C';
        }

        return $rules;
    }

    private function bodyOf(ReflectionMethod $method): string
    {
        $file = $method->getFileName();
        $lines = $file === false ? false : file($file);

        if ($lines === false) {
            throw new UnexpectedValueException(
                "Cannot read the source of $method->class::$method->name(), so its body cannot be checked",
            );
        }

        $start = (int) $method->getStartLine();
        $length = (int) $method->getEndLine() - $start + 1;

        return implode('', array_slice($lines, $start - 1, $length));
    }

    private function returnsUnsupportedFeature(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        if ($type === null) {
            return false;
        }

        return $this->typeImplementsInterface($type, $method->getDeclaringClass()->getName());
    }

    private function typeImplementsInterface(
        ReflectionType $type,
        string $declaringClass,
    ): bool {
        if ($type instanceof ReflectionUnionType) {
            return array_all(
                $type->getTypes(),
                fn (ReflectionType $member): bool => $this->typeImplementsInterface($member, $declaringClass),
            );
        }

        if ($type instanceof ReflectionIntersectionType) {
            return array_any(
                $type->getTypes(),
                fn (ReflectionType $member): bool => $this->typeImplementsInterface($member, $declaringClass),
            );
        }

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        $name = in_array($type->getName(), ['self', 'static'], true) ? $declaringClass : $type->getName();

        return is_a($name, UnsupportedFeatureExceptionInterface::class, true);
    }

    /**
     * @param list<string> $rules
     * @return array{subject: string, rule: string, message: string}
     */
    private function factoryViolation(
        string $subject,
        array $rules,
    ): array {
        $rule = implode('+', $rules);

        return [
            'subject' => $subject,
            'rule' => $rule,
            'message' => "Rule $rule: $subject says a driver does not support something but does not return an "
                . UnsupportedFeatureExceptionInterface::class . '. Fix it by returning the package\'s '
                . 'Unsupported{Thing}FeatureException, or use UnsupportedFeatureException::forDriver(). '
                . 'If it is not driver capability (environment requirement, framework-wide limit or input '
                . 'validation), add "' . $subject . '" to the allowlist with a reason.',
        ];
    }

    /**
     * @return array{subject: string, rule: string, message: string}
     */
    private function loadFailure(
        string $class,
        string $file,
    ): array {
        return [
            'subject' => $class,
            'rule' => 'load',
            'message' => "Could not load $class declared in $file, so it was not checked. Fix the autoloading.",
        ];
    }

    private function isLoadable(string $class): bool
    {
        return class_exists($class);
    }

    /**
     * @return list<class-string>
     */
    private function classNamesIn(string $file): array
    {
        $code = file_get_contents($file);

        if ($code === false) {
            throw new UnexpectedValueException("Cannot read $file, so its exception classes cannot be checked");
        }

        $tokens = array_values(array_filter(
            PhpToken::tokenize($code),
            fn (PhpToken $token): bool => !$token->isIgnorable(),
        ));
        $namespace = '';
        $classes = [];

        foreach ($tokens as $index => $token) {
            $next = $tokens[$index + 1] ?? null;

            if ($token->id === T_NAMESPACE && $next !== null && in_array(
                $next->id,
                [T_STRING, T_NAME_QUALIFIED],
                true,
            )) {
                $namespace = $next->text;
            }

            $previous = $tokens[$index - 1] ?? null;

            if ($token->id === T_CLASS
                && $next?->id === T_STRING
                && !in_array($previous?->id, [T_DOUBLE_COLON, T_NEW], true)) {
                /** @var class-string $class */
                $class = ($namespace === '' ? '' : $namespace . '\\') . $next->text;
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
