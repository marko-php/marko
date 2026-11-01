<?php

declare(strict_types=1);

use Marko\Testing\Http\TestClient;

use function Marko\Testing\Tests\httpAppPath;

use function Marko\Testing\Tests\removeHttpAppSessions;

afterAll(function (): void {
    removeHttpAppSessions();
});

describe('TestClient with streamed responses', function (): void {
    it('reads a streamed body through streamedContent() and assertSee()', function (): void {
        $response = TestClient::boot(httpAppPath())->get('/export.csv');

        expect($response->body())->toBe('')
            ->and($response->streamedContent())->toBe("id,name\n1,Ada\n");
        $response->assertOk()->assertSee('1,Ada')->assertDontSee('Grace');
    });

    it('serves a file with a range through the router', function (): void {
        $fixture = (string) file_get_contents(httpAppPath() . '/app/web/composer.json');

        $response = TestClient::boot(httpAppPath())->get('/download', headers: ['Range' => 'bytes=0-9']);

        $response->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-9/' . strlen($fixture))
            ->assertHeader('Content-Disposition', 'attachment; filename="fixture.json"');
        expect($response->streamedContent())->toBe(substr($fixture, 0, 10));
    });

    it('renders an unsatisfiable range as 416 with Content-Range', function (): void {
        $size = filesize(httpAppPath() . '/app/web/composer.json');

        TestClient::boot(httpAppPath())
            ->get('/download', headers: ['Range' => 'bytes=999999-'])
            ->assertStatus(416)
            ->assertHeader('Content-Range', "bytes */$size");
    });

    it('gives an empty streamed body for HEAD', function (): void {
        $response = TestClient::boot(httpAppPath())->head('/download');

        $response->assertOk();
        expect($response->streamedContent())->toBe('');
    });
});
