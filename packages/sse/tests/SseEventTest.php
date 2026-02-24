<?php

declare(strict_types=1);

use Marko\Sse\SseEvent;

it('formats event with all fields (event, id, retry, data)', function (): void {
    $event = new SseEvent(
        data: '{"user":"mark","text":"Hello"}',
        event: 'message',
        id: 42,
        retry: 3000,
    );

    expect($event->format())->toBe(
        "event: message\nid: 42\nretry: 3000\ndata: {\"user\":\"mark\",\"text\":\"Hello\"}\n\n"
    );
});

it('formats event with only string data', function (): void {
    $event = new SseEvent(data: 'hello world');

    expect($event->format())->toBe("data: hello world\n\n");
});

it('JSON-encodes array data', function (): void {
    $event = new SseEvent(data: ['user' => 'mark', 'text' => 'Hello']);

    expect($event->format())->toBe("data: {\"user\":\"mark\",\"text\":\"Hello\"}\n\n");
});

it('splits multi-line data into multiple data lines', function (): void {
    $event = new SseEvent(data: "line one\nline two");

    expect($event->format())->toBe("data: line one\ndata: line two\n\n");
});

it('includes retry field in milliseconds', function (): void {
    $event = new SseEvent(data: 'ping', retry: 5000);

    expect($event->format())->toBe("retry: 5000\ndata: ping\n\n");
});

it('omits null fields from output', function (): void {
    $event = new SseEvent(data: 'only data');

    expect($event->format())
        ->not->toContain('event:')
        ->not->toContain('id:')
        ->not->toContain('retry:')
        ->toBe("data: only data\n\n");
});

it('terminates frame with double newline', function (): void {
    $event = new SseEvent(data: 'test');

    expect($event->format())->toEndWith("\n\n");
});
