<?php

namespace AdminPhp\Enums;

/**
 * A draft revision holds pending changes (one per content at most);
 * a published revision is a snapshot of what was published.
 */
enum RevisionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
