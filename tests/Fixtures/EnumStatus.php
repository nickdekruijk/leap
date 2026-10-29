<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

/**
 * A string-backed enum for EnumModel, the way a project casts a status column.
 */
enum EnumStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
}
