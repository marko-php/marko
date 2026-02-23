<?php

declare(strict_types=1);

namespace Marko\Queue\Rabbitmq\Exchange;

enum ExchangeType: string
{
    case Direct = 'direct';
    case Fanout = 'fanout';
    case Topic = 'topic';
    case Headers = 'headers';
}
