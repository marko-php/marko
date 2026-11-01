<?php

declare(strict_types=1);

namespace Marko\Testing\Tests\HttpApp\Http\Controllers;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Exceptions\ContentDispositionException;
use Marko\Routing\Exceptions\FileResponseException;
use Marko\Routing\Exceptions\RangeNotSatisfiableException;
use Marko\Routing\File\LocalFileSource;
use Marko\Routing\Http\ContentDisposition;
use Marko\Routing\Http\FileResponse;
use Marko\Routing\Http\OutputStreamer;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\StreamedResponse;

/**
 * Streamed and file responses.
 *
 * @noinspection PhpUnused Route actions are invoked via reflection by the Router.
 */
class DownloadController
{
    #[Get('/export.csv')]
    public function export(): StreamedResponse
    {
        return new StreamedResponse(
            callback: function (OutputStreamer $outputStreamer): void {
                $outputStreamer->write("id,name\n");
                $outputStreamer->write("1,Ada\n");
            },
            headers: ['Content-Type' => 'text/csv'],
        );
    }

    /**
     * @throws ContentDispositionException|FileResponseException|RangeNotSatisfiableException
     */
    #[Get('/download')]
    public function download(
        Request $request,
    ): FileResponse {
        return FileResponse::fromSource($request, new LocalFileSource(dirname(__DIR__, 3) . '/composer.json'))
            ->withDisposition(ContentDisposition::attachment('fixture.json'));
    }
}
