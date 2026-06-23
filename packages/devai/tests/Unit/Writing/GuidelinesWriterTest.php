<?php

declare(strict_types=1);

use Marko\DevAi\Writing\GuidelinesWriter;
use Marko\DevAi\Writing\WriteOutcome;

it('creates the file with wrapped markers when the path does not exist', function (): void {
    $tmpDir = devaiTempDir();
    $path = $tmpDir . '/GUIDELINES.md';

    GuidelinesWriter::write($path, 'Generated content here');

    $contents = (string) file_get_contents($path);

    expect(file_exists($path))->toBeTrue()
        ->and($contents)->toContain(GuidelinesWriter::MARKER_BEGIN)
        ->and($contents)->toContain(GuidelinesWriter::MARKER_END)
        ->and($contents)->toContain('Generated content here');

    devaiRemoveDir($tmpDir);
});

it('returns created outcome when it writes a new file', function (): void {
    $tmpDir = devaiTempDir();
    $path = $tmpDir . '/GUIDELINES.md';

    $outcome = GuidelinesWriter::write($path, 'Some generated content');

    expect($outcome)->toBe(WriteOutcome::Created);

    devaiRemoveDir($tmpDir);
});

it('replaces only the content between existing markers and returns updated', function (): void {
    $tmpDir = devaiTempDir();
    $path = $tmpDir . '/GUIDELINES.md';

    $initial = GuidelinesWriter::MARKER_BEGIN . "\nOld content\n" . GuidelinesWriter::MARKER_END . "\n";
    file_put_contents($path, $initial);

    $outcome = GuidelinesWriter::write($path, 'New generated content');

    $contents = (string) file_get_contents($path);

    expect($outcome)->toBe(WriteOutcome::Updated)
        ->and($contents)->toContain('New generated content')
        ->and($contents)->not->toContain('Old content');

    devaiRemoveDir($tmpDir);
});

it('preserves user content outside the markers byte for byte when updating', function (): void {
    $tmpDir = devaiTempDir();
    $path = $tmpDir . '/GUIDELINES.md';

    $userHeader = "# My Custom Header\n\nSome user intro text.\n\n";
    $userFooter = "\n## User Section\n\nUser added notes here.\n";
    $initial = $userHeader
        . GuidelinesWriter::MARKER_BEGIN . "\nOld generated content\n" . GuidelinesWriter::MARKER_END
        . $userFooter;
    file_put_contents($path, $initial);

    GuidelinesWriter::write($path, 'New generated content');

    $contents = (string) file_get_contents($path);

    expect($contents)->toStartWith($userHeader)
        ->and($contents)->toContain('New generated content')
        ->and($contents)->toEndWith($userFooter);

    devaiRemoveDir($tmpDir);
});

it('leaves the file completely untouched and returns skipped when markers are absent', function (): void {
    $tmpDir = devaiTempDir();
    $path = $tmpDir . '/GUIDELINES.md';

    $originalContent = "# My File\n\nNo markers here at all.\n";
    file_put_contents($path, $originalContent);

    // Drain any prior notices from other tests
    GuidelinesWriter::takeNotices();

    $outcome = GuidelinesWriter::write($path, 'New generated content');

    $contents = (string) file_get_contents($path);

    expect($outcome)->toBe(WriteOutcome::SkippedNoMarkers)
        ->and($contents)->toBe($originalContent);

    devaiRemoveDir($tmpDir);
});

it('does not append a second marker pair when markers already exist', function (): void {
    $tmpDir = devaiTempDir();
    $path = $tmpDir . '/GUIDELINES.md';

    $initial = GuidelinesWriter::MARKER_BEGIN . "\nFirst content\n" . GuidelinesWriter::MARKER_END . "\n";
    file_put_contents($path, $initial);

    GuidelinesWriter::write($path, 'Updated content');
    GuidelinesWriter::write($path, 'Updated content again');

    $contents = (string) file_get_contents($path);

    expect(substr_count($contents, GuidelinesWriter::MARKER_BEGIN))->toBe(1)
        ->and(substr_count($contents, GuidelinesWriter::MARKER_END))->toBe(1);

    devaiRemoveDir($tmpDir);
});

it('wraps arbitrary generated content not just the guidelines body', function (): void {
    $tmpDir = devaiTempDir();
    $path = $tmpDir . '/arbitrary.md';

    $arbitraryContent = "## Skills\n\n- skill-a\n- skill-b\n\n## Tools\n\n- tool-x\n";

    GuidelinesWriter::write($path, $arbitraryContent);

    $contents = (string) file_get_contents($path);

    expect($contents)->toContain(GuidelinesWriter::MARKER_BEGIN)
        ->and($contents)->toContain(GuidelinesWriter::MARKER_END)
        ->and($contents)->toContain($arbitraryContent);

    devaiRemoveDir($tmpDir);
});

it('returns skipped and leaves the file untouched when a begin marker exists but the end marker is missing', function (): void {
    $tmpDir = devaiTempDir();
    $path = $tmpDir . '/GUIDELINES.md';

    $malformed = "# Header\n" . GuidelinesWriter::MARKER_BEGIN . "\nOrphaned content with no end marker.\n";
    file_put_contents($path, $malformed);

    // Drain any prior notices
    GuidelinesWriter::takeNotices();

    $outcome = GuidelinesWriter::write($path, 'New content');

    $contents = (string) file_get_contents($path);

    expect($outcome)->toBe(WriteOutcome::SkippedNoMarkers)
        ->and($contents)->toBe($malformed);

    devaiRemoveDir($tmpDir);
});

it('embeds the marko devai:update regenerate hint inside the wrapped region', function (): void {
    $tmpDir = devaiTempDir();
    $path = $tmpDir . '/GUIDELINES.md';

    GuidelinesWriter::write($path, 'Some generated content');

    $contents = (string) file_get_contents($path);

    $beginPos = strpos($contents, GuidelinesWriter::MARKER_BEGIN);
    $endPos = strpos($contents, GuidelinesWriter::MARKER_END);
    $wrappedRegion = substr($contents, (int) $beginPos, (int) $endPos - (int) $beginPos + strlen(GuidelinesWriter::MARKER_END));

    expect($wrappedRegion)->toContain('marko devai:update');

    devaiRemoveDir($tmpDir);
});

it('records a loud notice retrievable via takeNotices when it skips a marker-stripped file', function (): void {
    $tmpDir = devaiTempDir();
    $path = $tmpDir . '/GUIDELINES.md';

    file_put_contents($path, "# No markers here\n");

    // Drain prior notices
    GuidelinesWriter::takeNotices();

    GuidelinesWriter::write($path, 'Some content');

    $notices = GuidelinesWriter::takeNotices();

    expect($notices)->not->toBeEmpty()
        ->and($notices[0])->toContain($path);

    devaiRemoveDir($tmpDir);
});

it('clears recorded notices after takeNotices is called', function (): void {
    $tmpDir = devaiTempDir();
    $path = $tmpDir . '/GUIDELINES.md';

    file_put_contents($path, "# No markers here\n");

    // Drain prior notices
    GuidelinesWriter::takeNotices();

    GuidelinesWriter::write($path, 'Some content');

    // First call should have the notice
    $firstCall = GuidelinesWriter::takeNotices();

    // Second call should be empty
    $secondCall = GuidelinesWriter::takeNotices();

    expect($firstCall)->not->toBeEmpty()
        ->and($secondCall)->toBeEmpty();

    devaiRemoveDir($tmpDir);
});
