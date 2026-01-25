# Task 049: Database Migrations

**Status**: pending
**Depends on**: 003, 004, 005, 007, 008, 009, 011, 012
**Retry count**: 0

## Description
Create database migrations for all blog module tables. Migrations define the schema for authors, categories, tags, posts (updated), comments, verification tokens, and pivot tables.

## Context
- Related files: `packages/blog/database/migrations/`
- Patterns to follow: Marko migration pattern using `marko/database` migration system
- Migrations run via `marko db:migrate` command
- Each migration is a timestamped PHP file

## Requirements (Test Descriptions)
- [ ] `it creates authors table with correct columns`
- [ ] `it creates categories table with self-referential parent_id`
- [ ] `it creates tags table with correct columns`
- [ ] `it updates posts table with new columns`
- [ ] `it creates comments table with self-referential parent_id`
- [ ] `it creates verification_tokens table`
- [ ] `it creates post_categories pivot table`
- [ ] `it creates post_tags pivot table`
- [ ] `it adds foreign key constraints`
- [ ] `it adds indexes for frequently queried columns`
- [ ] `it can rollback all migrations cleanly`

## Migration Files

### 001_create_authors_table.php
```php
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->text('bio')->nullable();
            $table->string('slug')->unique();
            $table->timestamps();

            $table->index('slug');
            $table->index('email');
        });
    }

    public function down(): void
    {
        $this->schema->drop('authors');
    }
};
```

### 002_create_categories_table.php
```php
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();

            $table->index('slug');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        $this->schema->drop('categories');
    }
};
```

### 003_create_tags_table.php
```php
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();

            $table->index('slug');
        });
    }

    public function down(): void
    {
        $this->schema->drop('tags');
    }
};
```

### 004_update_posts_table.php
```php
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->table('posts', function (Blueprint $table) {
            $table->foreignId('author_id')->constrained('authors');
            $table->text('summary')->nullable()->after('content');
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->index('status');
            $table->index('author_id');
            $table->index('published_at');
            $table->index(['status', 'scheduled_at']); // For scheduled posts query
        });
    }

    public function down(): void
    {
        $this->schema->table('posts', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropColumn(['author_id', 'summary', 'status', 'scheduled_at', 'published_at']);
        });
    }
};
```

### 005_create_comments_table.php
```php
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->string('author_name');
            $table->string('author_email');
            $table->text('content');
            $table->string('status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at');

            $table->index('post_id');
            $table->index('parent_id');
            $table->index('status');
            $table->index('author_email');
            $table->index(['post_id', 'status']); // For fetching verified comments
        });
    }

    public function down(): void
    {
        $this->schema->drop('comments');
    }
};
```

### 006_create_verification_tokens_table.php
```php
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('email');
            $table->foreignId('comment_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->string('type'); // 'email' or 'browser'
            $table->timestamp('created_at');
            $table->timestamp('expires_at');

            $table->index('token');
            $table->index('email');
            $table->index('type');
            $table->index('expires_at'); // For cleanup query
        });
    }

    public function down(): void
    {
        $this->schema->drop('verification_tokens');
    }
};
```

### 007_create_post_categories_table.php
```php
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('post_categories', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();

            $table->primary(['post_id', 'category_id']);
            $table->index('category_id'); // For category archive queries
        });
    }

    public function down(): void
    {
        $this->schema->drop('post_categories');
    }
};
```

### 008_create_post_tags_table.php
```php
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('post_tags', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();

            $table->primary(['post_id', 'tag_id']);
            $table->index('tag_id'); // For tag archive queries
        });
    }

    public function down(): void
    {
        $this->schema->drop('post_tags');
    }
};
```

## Cascade Behavior

| Parent Table | Child Table | On Delete |
|--------------|-------------|-----------|
| posts | comments | CASCADE (comments deleted with post) |
| posts | post_categories | CASCADE (pivot rows deleted) |
| posts | post_tags | CASCADE (pivot rows deleted) |
| comments | comments (children) | CASCADE (nested replies deleted) |
| comments | verification_tokens | CASCADE (tokens deleted with comment) |
| categories | post_categories | CASCADE (pivot rows deleted) |
| tags | post_tags | CASCADE (pivot rows deleted) |
| categories | categories (children) | SET NULL (children become root) |

**Note:** Authors, categories, and tags cannot be deleted while they have associated posts. This is enforced at the application level (see Tasks 003, 004, 005), not via foreign key CASCADE.

## Acceptance Criteria
- All requirements have passing tests
- All migrations can be run in sequence without errors
- All migrations can be rolled back cleanly
- Foreign key constraints properly defined
- Indexes added for query performance
- Migration files follow naming convention
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
