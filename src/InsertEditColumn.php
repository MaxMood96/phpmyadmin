<?php

declare(strict_types=1);

namespace PhpMyAdmin;

use function date;
use function md5;
use function preg_match;
use function preg_replace;

final readonly class InsertEditColumn
{
    public string|null $default;
    public string $md5;
    /**
     * trueType contains only the type (stops at first bracket)
     */
    public string $trueType;
    public int $length;
    public bool $firstTimestamp;

    public function __construct(
        public string $field,
        public string $type,
        public bool $isNull,
        public string $key,
        string|null $default,
        public string $extra,
        int $columnLength,
        public bool $isBinary,
        public bool $isBlob,
        public bool $isChar,
        bool $insertMode,
    ) {
        if (
            $this->type === 'datetime'
            && ! $this->isNull
            && $default === null
            && $insertMode
        ) {
            $this->default = date('Y-m-d H:i:s');
        } else {
            $this->default = $default;
        }

        $this->md5 = md5($this->field);
        $this->trueType = preg_replace('@(\(.*)|(\s/.*)@s', '', $this->type);
        // length is unknown for geometry fields,
        // make enough space to edit very simple WKTs
        if ($columnLength === -1) {
            $columnLength = 30;
        }

        $this->length = preg_match('@float|double@', $this->type) === 1 ? 100 : $columnLength;
        /**
         * TODO: This property is useless at the moment.
         * It seems like a long time ago before refactoring into classes,
         * this kept track of how many timestamps are in the table.
         */
        $this->firstTimestamp = $this->trueType === 'timestamp';
    }

    public function getFractionalSecondsPrecision(): int
    {
        if ($this->trueType !== 'timestamp' && $this->trueType !== 'datetime' && $this->trueType !== 'time') {
            return 0;
        }

        $matches = [];
        if (preg_match('@\((\d+)\)@', $this->type, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    public function getDisplayType(): string
    {
        // Enum and set values, and any additional options within comments, are not displayed
        return match ($this->trueType) {
            'set', 'enum' => $this->trueType,
            default => preg_replace('@\s/.+@', '', $this->type),
        };
    }
}
