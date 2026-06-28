<?php

namespace App\Enums;

enum ReviewCommentDraftStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Posted = 'posted';
    case Failed = 'failed';

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }
}
