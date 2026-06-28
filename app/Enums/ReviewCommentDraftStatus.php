<?php

namespace App\Enums;

enum ReviewCommentDraftStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Posted = 'posted';
    case Failed = 'failed';
}
