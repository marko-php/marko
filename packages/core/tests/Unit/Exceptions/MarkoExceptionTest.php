<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;

describe('inferPackageName', function (): void {
    it('maps simple Marko namespaces to package names', function (): void {
        expect(MarkoException::inferPackageName('Marko\Core\Exceptions\MarkoException'))
            ->toBe('marko/core')
            ->and(MarkoException::inferPackageName('Marko\Routing\Router'))
            ->toBe('marko/routing')
            ->and(MarkoException::inferPackageName('Marko\Hashing\HashManager'))
            ->toBe('marko/hashing');
    });

    it('converts CamelCase namespaces to kebab-case package names', function (): void {
        expect(MarkoException::inferPackageName('Marko\AdminAuth\Attributes\RequiresPermission'))
            ->toBe('marko/admin-auth')
            ->and(MarkoException::inferPackageName('Marko\AdminPanel\Dashboard'))
            ->toBe('marko/admin-panel')
            ->and(MarkoException::inferPackageName('Marko\ErrorsAdvanced\Handler'))
            ->toBe('marko/errors-advanced');
    });

    it('maps nested namespaces to compound package names', function (): void {
        expect(MarkoException::inferPackageName('Marko\Database\MySql\Connection'))
            ->toBe('marko/database-my-sql')
            ->and(MarkoException::inferPackageName('Marko\Cache\File\FileStore'))
            ->toBe('marko/cache-file')
            ->and(MarkoException::inferPackageName('Marko\View\Latte\LatteEngine'))
            ->toBe('marko/view-latte');
    });

    it('skips structural directories when inferring package name', function (): void {
        expect(MarkoException::inferPackageName('Marko\Admin\Contracts\AdminSectionInterface'))
            ->toBe('marko/admin')
            ->and(MarkoException::inferPackageName('Marko\Routing\Attributes\Route'))
            ->toBe('marko/routing')
            ->and(MarkoException::inferPackageName('Marko\Core\Exceptions\MarkoException'))
            ->toBe('marko/core')
            ->and(MarkoException::inferPackageName('Marko\Core\Events\SomeEvent'))
            ->toBe('marko/core')
            ->and(MarkoException::inferPackageName('Marko\Cli\Commands\ListCommand'))
            ->toBe('marko/cli');
    });

    it('returns null for non-Marko namespaces', function (): void {
        expect(MarkoException::inferPackageName('App\Models\User'))
            ->toBeNull()
            ->and(MarkoException::inferPackageName('Illuminate\Database\Connection'))
            ->toBeNull();
    });

    it('returns null for single-segment names', function (): void {
        expect(MarkoException::inferPackageName('SomeClass'))
            ->toBeNull();
    });

    it('handles leading backslash', function (): void {
        expect(MarkoException::inferPackageName('\Marko\Admin\Contracts\SomeInterface'))
            ->toBe('marko/admin');
    });
});

describe('extractMissingClass', function (): void {
    it('extracts class name from class not found error', function (): void {
        $error = new Error('Class "Marko\Admin\Contracts\AdminSectionInterface" not found');
        expect(MarkoException::extractMissingClass($error))
            ->toBe('Marko\Admin\Contracts\AdminSectionInterface');
    });

    it('extracts class name from interface not found error', function (): void {
        $error = new Error('Interface "Marko\Session\Contracts\SessionInterface" not found');
        expect(MarkoException::extractMissingClass($error))
            ->toBe('Marko\Session\Contracts\SessionInterface');
    });

    it('extracts class name from trait not found error', function (): void {
        $error = new Error('Trait "Marko\Core\Traits\HasEvents" not found');
        expect(MarkoException::extractMissingClass($error))
            ->toBe('Marko\Core\Traits\HasEvents');
    });

    it('extracts class name from enum not found error', function (): void {
        $error = new Error('Enum "Marko\Core\Status" not found');
        expect(MarkoException::extractMissingClass($error))
            ->toBe('Marko\Core\Status');
    });

    it('extracts class name from attribute class not found error', function (): void {
        $error = new Error('Attribute class "Marko\AdminAuth\Attributes\RequiresPermission" not found');
        expect(MarkoException::extractMissingClass($error))
            ->toBe('Marko\AdminAuth\Attributes\RequiresPermission');
    });

    it('returns null for non-matching error messages', function (): void {
        $error = new Error('Call to undefined method foo()');
        expect(MarkoException::extractMissingClass($error))
            ->toBeNull();
    });
});
