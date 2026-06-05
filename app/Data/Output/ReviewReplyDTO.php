<?php

namespace App\Data\Output;

use App\Models\Entities\ReviewReply;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class ReviewReplyDTO extends Data
{
    public function __construct(
        public int $id,
        public ?int $parentReplyId,
        public int $authorType,
        public string $authorName,
        public string $text,
        public ?Carbon $createdAt,
    ) {}

    public static function fromModel(ReviewReply $m): self
    {
        return new self(
            id:             (int) $m->id,
            parentReplyId:  $m->parent_reply_id ? (int) $m->parent_reply_id : null,
            authorType:     (int) $m->author_type,
            authorName:     (string) ($m->user?->full_name ?? 'Người dùng'),
            text:           (string) $m->text,
            createdAt:      $m->created_at,
        );
    }
}
