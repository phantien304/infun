<?php

namespace App\Data\Cms;

use App\Models\Entities\ReviewReply;
use Spatie\LaravelData\Data;

class ReviewReplyItemData extends Data
{
    public function __construct(
        public int $id,
        public ?int $parent_reply_id,
        public int $author_type,
        public string $author_name,
        public string $text,
        public bool $is_publish,
        public ?string $created_at,
    ) {
    }

    public static function fromModel(ReviewReply $m): self
    {
        return new self(
            id: (int) $m->id,
            parent_reply_id: $m->parent_reply_id ? (int) $m->parent_reply_id : null,
            author_type: (int) $m->author_type,
            author_name: (string) ($m->user?->full_name ?? 'Người dùng'),
            text: (string) $m->text,
            is_publish: (bool) $m->is_publish,
            created_at: $m->created_at?->toDateTimeString(),
        );
    }
}
