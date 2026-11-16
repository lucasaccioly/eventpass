<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
