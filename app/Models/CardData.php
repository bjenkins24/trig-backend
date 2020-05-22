<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCard;

class CardData extends BaseModel
{
    use BelongsToCard;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'title',
        'keyword',
        'author',
        'last_author',
        'comment',
        'language',
        'subject',
        'revisions',
        'created',
        'modified',
        'print_date',
        'save_date',
        'line_count',
        'page_count',
        'paragraph_count',
        'word_count',
        'character_count',
        'character_count_with_spaces',
        'width',
        'height',
        'copyright',
        'content',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created'           => 'datetime',
        'modified'          => 'datetime',
        'print_date'        => 'datetime',
        'save_date'         => 'datetime',
    ];
}
