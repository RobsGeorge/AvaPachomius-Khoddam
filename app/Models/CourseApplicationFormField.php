<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseApplicationFormField extends Model
{
    public const TYPE_SHORT_TEXT = 'short_text';

    public const TYPE_LONG_TEXT = 'long_text';

    public const TYPE_EMAIL = 'email';

    public const TYPE_PHONE = 'phone';

    public const TYPE_URL = 'url';

    public const TYPE_NUMBER = 'number';

    public const TYPE_DATE = 'date';

    public const TYPE_SINGLE_CHOICE = 'single_choice';

    public const TYPE_DROPDOWN = 'dropdown';

    public const TYPE_MULTISELECT = 'multiselect';

    public const TYPE_CHECKBOX = 'checkbox';

    public const TYPE_CHECKBOX_GROUP = 'checkbox_group';

    public const TYPE_FILE = 'file';

    public const TYPE_IMAGE = 'image';

    public const TYPE_SECTION_HEADING = 'section_heading';

    public const TYPE_PARAGRAPH = 'paragraph';

    /** @return list<string> */
    public static function inputTypes(): array
    {
        return [
            self::TYPE_SHORT_TEXT,
            self::TYPE_LONG_TEXT,
            self::TYPE_EMAIL,
            self::TYPE_PHONE,
            self::TYPE_URL,
            self::TYPE_NUMBER,
            self::TYPE_DATE,
            self::TYPE_SINGLE_CHOICE,
            self::TYPE_DROPDOWN,
            self::TYPE_MULTISELECT,
            self::TYPE_CHECKBOX,
            self::TYPE_CHECKBOX_GROUP,
            self::TYPE_FILE,
            self::TYPE_IMAGE,
        ];
    }

    /** @return list<string> */
    public static function layoutTypes(): array
    {
        return [
            self::TYPE_SECTION_HEADING,
            self::TYPE_PARAGRAPH,
        ];
    }

    /** @return list<string> */
    public static function allTypes(): array
    {
        return array_merge(self::inputTypes(), self::layoutTypes());
    }

    public function isInput(): bool
    {
        return in_array($this->type, self::inputTypes(), true);
    }

    public function isLayout(): bool
    {
        return in_array($this->type, self::layoutTypes(), true);
    }

    protected $fillable = [
        'step_id',
        'field_key',
        'type',
        'label',
        'help_text',
        'required',
        'order_index',
        'config',
    ];

    protected $casts = [
        'required' => 'boolean',
        'order_index' => 'integer',
        'config' => 'array',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(CourseApplicationFormStep::class, 'step_id');
    }
}
