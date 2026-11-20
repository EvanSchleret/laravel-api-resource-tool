<?php

namespace Tests\Enums;

enum EntryStatus: string
{
    case Draft = 'draft';

    case Published = 'published';

    case Archived = 'archived';
}
