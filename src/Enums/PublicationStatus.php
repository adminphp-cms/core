<?php

namespace Gmarineau\AdminPhp\Enums;

/**
 * Editorial status stored on a publishable model.
 */
enum PublicationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
