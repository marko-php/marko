<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

enum DispositionType: string
{
    case Inline = 'inline';
    case Attachment = 'attachment';
}
