# Blog Module - End-User Testing Guide

This document outlines the new blog functionality to test from an end-user perspective.

## Prerequisites

Before testing, ensure:
- Database migrations have been run
- At least one author exists in the database
- Sample posts, categories, and tags have been created

---

## 1. Post Listing & Pagination

**URL:** `/blog` (or your configured route prefix)

### Test Cases
- [ ] View the blog homepage showing published posts
- [ ] Verify posts are ordered by publish date (newest first)
- [ ] Verify only published posts appear (not draft or scheduled)
- [ ] Navigate through pagination (if more than 10 posts exist)
- [ ] Verify "Previous" link is hidden on page 1
- [ ] Verify "Next" link is hidden on the last page
- [ ] Test invalid page numbers return 404 (e.g., `/blog?page=999`)

---

## 2. Single Post View

**URL:** `/blog/{post-slug}`

### Test Cases
- [ ] View a single published post
- [ ] Verify post title, content, author name display correctly
- [ ] Verify categories are listed with links to category archives
- [ ] Verify tags are listed with links to tag archives
- [ ] Verify draft posts return 404
- [ ] Verify scheduled (unpublished) posts return 404
- [ ] Verify non-existent slugs return 404

---

## 3. Author Archives

**URL:** `/blog/author/{author-slug}`

### Test Cases
- [ ] View an author's archive page
- [ ] Verify only that author's published posts appear
- [ ] Verify author name/bio displays correctly
- [ ] Test pagination if author has more than 10 posts
- [ ] Verify non-existent author slugs return 404

---

## 4. Category Archives

**URL:** `/blog/category/{category-slug}`

### Test Cases
- [ ] View a category archive page
- [ ] Verify only posts in that category appear
- [ ] Verify category name/description displays
- [ ] Test pagination if category has more than 10 posts
- [ ] Verify non-existent category slugs return 404
- [ ] Test hierarchical categories (parent/child relationships)

---

## 5. Tag Archives

**URL:** `/blog/tag/{tag-slug}`

### Test Cases
- [ ] View a tag archive page
- [ ] Verify only posts with that tag appear
- [ ] Verify tag name displays correctly
- [ ] Test pagination if tag has more than 10 posts
- [ ] Verify non-existent tag slugs return 404

---

## 6. Search Functionality

**URL:** `/blog/search?q={search-term}`

### Test Cases
- [ ] Search for a term that exists in post titles
- [ ] Search for a term that exists in post summaries
- [ ] Verify search results are paginated
- [ ] Test empty search query behavior
- [ ] Test search with no results
- [ ] Verify search bar is visible and functional

---

## 7. Comment System

**URL:** Comment form on `/blog/{post-slug}`

### Test Cases

#### Submitting a Comment
- [ ] Submit a comment with valid name, email, and content
- [ ] Verify success message appears after submission
- [ ] Verify comment is marked as "pending verification"
- [ ] Verify honeypot field is hidden (spam prevention)

#### Email Verification
- [ ] Check email inbox for verification email
- [ ] Click the verification link in the email
- [ ] Verify redirect to post with success message
- [ ] Verify comment now appears as verified

#### Cookie-Based Auto-Approval
- [ ] After verifying one comment, submit another comment with same email
- [ ] Verify second comment is auto-approved (no email verification needed)
- [ ] Clear cookies and submit again - should require verification

#### Threaded Comments (Replies)
- [ ] Reply to an existing comment
- [ ] Verify reply appears nested under parent comment
- [ ] Test maximum nesting depth (default: 5 levels)

#### Validation & Rate Limiting
- [ ] Submit comment without name - verify error
- [ ] Submit comment without email - verify error
- [ ] Submit comment without content - verify error
- [ ] Submit comment with invalid email format - verify error
- [ ] Submit two comments rapidly - verify rate limit message (default: 30 seconds)

---

## 8. SEO Features

### Test Cases
- [ ] View page source on post listing - verify canonical URL
- [ ] View page source on single post - verify canonical URL
- [ ] View page source - verify meta description tag
- [ ] View page source - verify `<title>` tag is descriptive
- [ ] View page source on paginated pages - verify rel="prev" and rel="next" links
- [ ] View page source - verify Open Graph tags:
  - `og:title`
  - `og:description`
  - `og:url`
  - `og:type` (should be "article" for posts, "website" for archives)

---

## 9. Scheduled Posts

### Test Cases
- [ ] Create a post with status "scheduled" and future `scheduled_at` date
- [ ] Verify scheduled post does NOT appear on frontend
- [ ] Run CLI command: `php bin/console blog:publish-scheduled`
- [ ] Verify post with past `scheduled_at` is now published
- [ ] Verify post now appears on frontend

---

## 10. CLI Commands

### Cleanup Command
```bash
php bin/console blog:cleanup
```

- [ ] Run cleanup command
- [ ] Verify expired verification tokens are removed
- [ ] Verify expired browser tokens are removed
- [ ] Check output shows count of deleted tokens

### Publish Scheduled Posts Command
```bash
php bin/console blog:publish-scheduled
```

- [ ] Run with no scheduled posts due - verify "No posts to publish" message
- [ ] Run with scheduled posts due - verify posts are published
- [ ] Verify PostPublished event is dispatched (check logs if event listeners exist)

---

## 11. Error Handling

### Test Cases
- [ ] Access `/blog/non-existent-slug` - verify 404 page
- [ ] Access `/blog/author/non-existent` - verify 404 page
- [ ] Access `/blog/category/non-existent` - verify 404 page
- [ ] Access `/blog/tag/non-existent` - verify 404 page
- [ ] Access `/blog?page=-1` - verify 404 page
- [ ] Submit comment on non-existent post - verify error handling

---

## Configuration Reference

Default configuration values (can be customized in `config/blog.php`):

| Setting | Default | Description |
|---------|---------|-------------|
| `posts_per_page` | 10 | Posts shown per page |
| `comment_max_depth` | 5 | Maximum comment nesting level |
| `comment_rate_limit_seconds` | 30 | Seconds between comments |
| `verification_token_expiry_days` | 7 | Days until email token expires |
| `verification_cookie_days` | 365 | Days browser cookie is valid |
| `route_prefix` | `/blog` | URL prefix for all blog routes |

---

## URL Structure Summary

| Page | URL Pattern |
|------|-------------|
| Post list | `/blog` |
| Single post | `/blog/{slug}` |
| Author archive | `/blog/author/{slug}` |
| Category archive | `/blog/category/{slug}` |
| Tag archive | `/blog/tag/{slug}` |
| Search results | `/blog/search?q={term}` |
| Comment submit | `POST /blog/{slug}/comment` |
| Verify comment | `/blog/comment/verify/{token}` |
