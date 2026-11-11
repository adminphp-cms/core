<?php

namespace Gmarineau\AdminPhp\Enums;

/**
 * What the site shows right now, derived from the status and the publication window.
 */
enum Visibility: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Expired = 'expired';
}
