# Local Marko Blog Setup Guide

**For contributors and local development using unpublished packages**

This guide sets up a new Marko blog project at `~/Sites/myblog` that references local packages from `~/Sites/marko`.

---

## Prerequisites

- PHP 8.5+
- Composer
- MySQL or PostgreSQL running locally
- The Marko monorepo cloned at `~/Sites/marko`

---

## Step 1: Create Project Structure

```bash
mkdir -p ~/Sites/myblog/{public,config,app,modules}
cd ~/Sites/myblog
```

---

## Step 2: Create composer.json

```bash
cat > composer.json << 'EOF'
{
    "name": "myblog/app",
    "description": "My Marko Blog",
    "type": "project",
    "minimum-stability": "dev",
    "prefer-stable": true,
    "require": {
        "php": "^8.5",
        "marko/core": "dev-develop as 0.1.0",
        "marko/routing": "dev-develop as 0.1.0",
        "marko/database": "dev-develop as 0.1.0",
        "marko/database-mysql": "dev-develop as 0.1.0",
        "marko/blog": "*",
        "marko/errors": "dev-develop as 0.1.0",
        "marko/errors-simple": "dev-develop as 0.1.0",
        "marko/cli": "dev-develop as 0.1.0"
    },
    "repositories": [
        { "type": "path", "url": "../marko/packages/core" },
        { "type": "path", "url": "../marko/packages/routing" },
        { "type": "path", "url": "../marko/packages/database" },
        { "type": "path", "url": "../marko/packages/database-mysql" },
        { "type": "path", "url": "../marko/packages/blog" },
        { "type": "path", "url": "../marko/packages/errors" },
        { "type": "path", "url": "../marko/packages/errors-simple" },
        { "type": "path", "url": "../marko/packages/cli" }
    ],
    "config": {
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    }
}
EOF
```

The `"dev-develop as 0.1.0"` syntax tells Composer to treat the dev branch as version 0.1.0, satisfying the `^0.1` constraints in the packages.

> **For PostgreSQL:** Replace `marko/database-mysql` with `marko/database-pgsql` and update the repository URL accordingly.

---

## Step 3: Create Database Config

```bash
cat > config/database.php << 'EOF'
<?php

declare(strict_types=1);

return [
    'driver' => 'mysql',  // or 'pgsql' for PostgreSQL
    'host' => '127.0.0.1',
    'port' => 3306,       // or 5432 for PostgreSQL
    'database' => 'myblog',
    'username' => 'root',
    'password' => '',
];
EOF
```

Adjust credentials for your local database.

---

## Step 4: Create Entry Point

```bash
cat > public/index.php << 'EOF'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Marko\Routing\Http\Request;

$app = (require __DIR__ . '/../vendor/marko/core/bootstrap.php')(
    vendorPath: __DIR__ . '/../vendor',
    modulesPath: __DIR__ . '/../modules',
    appPath: __DIR__ . '/../app',
);

// Create Request from globals
$request = Request::fromGlobals();

// Route request through Router
$response = $app->router->handle($request);

// Send Response to client
$response->send();
EOF
```

---

## Step 5: Install Dependencies

```bash
cd ~/Sites/myblog
composer install
```

This creates symlinks to your local `~/Sites/marko/packages/*` directories, so any changes you make to the framework are immediately reflected.

---

## Step 6: Create the Database

**MySQL:**
```bash
mysql -u root -e "CREATE DATABASE myblog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**PostgreSQL:**
```bash
createdb myblog
```

---

## Step 7: Run Migrations

Create the `posts` table:

```bash
# From ~/Sites/myblog
./vendor/bin/marko db:migrate
```

> **Note:** If the CLI isn't set up yet, you may need to create the table manually:

**MySQL:**
```sql
CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    content TEXT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**PostgreSQL:**
```sql
CREATE TABLE posts (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    content TEXT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

## Step 8: Add Sample Data

```sql
INSERT INTO posts (title, slug, content, created_at) VALUES
('Hello World', 'hello-world', 'Welcome to my Marko blog!', NOW()),
('Second Post', 'second-post', 'This is another post.', NOW());
```

---

## Step 9: Start the Server

```bash
cd ~/Sites/myblog
php -S localhost:9000 -t public
```

---

## Step 10: Test It

Open in browser:

- **Blog index:** http://localhost:9000/blog
- **Single post:** http://localhost:9000/blog/hello-world
- **404 test:** http://localhost:9000/blog/nonexistent

---

## Final Directory Structure

```
~/Sites/myblog/
├── app/                    # Your custom modules (empty for now)
├── config/
│   └── database.php        # Database credentials
├── modules/                # Third-party modules (empty for now)
├── public/
│   └── index.php           # Web entry point
├── vendor/                 # Symlinked to ../marko/packages/*
│   ├── marko/
│   │   ├── core -> ../../../marko/packages/core
│   │   ├── routing -> ../../../marko/packages/routing
│   │   ├── blog -> ../../../marko/packages/blog
│   │   └── ...
│   └── autoload.php
└── composer.json
```

---

## Development Workflow

### Making Framework Changes

1. Edit files in `~/Sites/marko/packages/*`
2. Changes are immediately available in `~/Sites/myblog` (symlinked)
3. Refresh browser to test
4. Run tests from the monorepo: `cd ~/Sites/marko && ./vendor/bin/pest`

### Switching Branches

```bash
cd ~/Sites/marko
git checkout feature-branch

# Rebuild myblog dependencies if composer.json changed
cd ~/Sites/myblog
composer update
```

### Adding App Customizations

Create a custom module to override blog behavior:

```bash
mkdir -p ~/Sites/myblog/app/myblog/src/Controllers
```

**app/myblog/composer.json:**
```json
{
    "name": "app/myblog",
    "autoload": {
        "psr-4": {
            "App\\MyBlog\\": "src/"
        }
    }
}
```

**app/myblog/src/Controllers/CustomPostController.php:**
```php
<?php

declare(strict_types=1);

namespace App\MyBlog\Controllers;

use Marko\Blog\Controllers\PostController;
use Marko\Blog\Repositories\PostRepository;
use Marko\Core\Attributes\Preference;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

#[Preference(replaces: PostController::class)]
class CustomPostController extends PostController
{
    #[Get('/blog')]
    public function index(): Response
    {
        $posts = $this->repository->findAll();
        $html = '<h1>My Custom Blog</h1><ul>';
        foreach ($posts as $post) {
            $html .= "<li><a href=\"/blog/{$post->slug}\">{$post->title}</a></li>";
        }
        $html .= '</ul>';
        return new Response($html);
    }
}
```

---

## Troubleshooting

### "Class not found" errors
```bash
composer dump-autoload
```

### Database connection errors
- Verify `config/database.php` credentials
- Ensure MySQL/PostgreSQL is running
- Check the database exists

### Changes not reflecting
- Composer path repositories create symlinks - verify with `ls -la vendor/marko/`
- If not symlinked, run `composer update --prefer-source`

### Permission errors
```bash
chmod -R 755 ~/Sites/myblog
```

---

## Quick Reference

| Command                           | Purpose              |
|-----------------------------------|----------------------|
| `php -S localhost:9000 -t public` | Start dev server     |
| `composer update`                 | Refresh dependencies |
| `composer dump-autoload`          | Rebuild autoloader   |
| `./vendor/bin/marko db:migrate`   | Run migrations       |

---

This setup gives you a fully functional local Marko blog that's directly linked to your framework development. Any changes to `~/Sites/marko/packages/*` are immediately testable in `~/Sites/myblog`.
