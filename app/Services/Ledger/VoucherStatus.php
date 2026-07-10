<?php

namespace App\Services\Ledger;

final class VoucherStatus
{
    public const DRAFT = 'DRAFT';
    public const REVIEW_REQUESTED = 'REVIEW_REQUESTED';
    public const REVIEWED = 'REVIEWED';
    public const POSTED = 'POSTED';
    public const CLOSED = 'CLOSED';
    public const DELETED = 'DELETED';

    private const VALUES = [
        self::DRAFT,
        self::REVIEW_REQUESTED,
        self::REVIEWED,
        self::POSTED,
        self::CLOSED,
        self::DELETED,
    ];

    private const EDITABLE_VALUES = [
        self::DRAFT,
    ];

    private const PICKER_VALUES = [
        self::DRAFT,
        self::REVIEW_REQUESTED,
        self::REVIEWED,
    ];

    private const LOCKED_VALUES = [
        self::REVIEW_REQUESTED,
        self::REVIEWED,
        self::POSTED,
        self::CLOSED,
        self::DELETED,
    ];

    private const REVIEW_LIST_VALUES = [
        self::REVIEW_REQUESTED,
        self::REVIEWED,
        self::POSTED,
        self::CLOSED,
    ];

    private const TRANSITIONS = [
        self::DRAFT => self::REVIEW_REQUESTED,
        self::REVIEW_REQUESTED => self::REVIEWED,
        self::REVIEWED => self::POSTED,
        self::POSTED => self::CLOSED,
    ];

    public static function values(): array
    {
        return self::VALUES;
    }

    public static function editableValues(): array
    {
        return self::EDITABLE_VALUES;
    }

    public static function pickerValues(): array
    {
        return self::PICKER_VALUES;
    }

    public static function lockedValues(): array
    {
        return self::LOCKED_VALUES;
    }

    public static function reviewListValues(): array
    {
        return self::REVIEW_LIST_VALUES;
    }

    public static function normalize(mixed $value, ?string $fallback = self::DRAFT): ?string
    {
        $normalized = strtoupper(trim((string) ($value ?? '')));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        if ($normalized === '') {
            return $fallback;
        }

        return in_array($normalized, self::VALUES, true) ? $normalized : $fallback;
    }

    public static function isEditable(mixed $value): bool
    {
        return in_array(self::normalize($value, ''), self::EDITABLE_VALUES, true);
    }

    public static function equals(mixed $value, string $expected): bool
    {
        return self::normalize($value, '') === $expected;
    }

    public static function isDraft(mixed $value): bool
    {
        return self::equals($value, self::DRAFT);
    }

    public static function isReviewRequested(mixed $value): bool
    {
        return self::equals($value, self::REVIEW_REQUESTED);
    }

    public static function isReviewed(mixed $value): bool
    {
        return self::equals($value, self::REVIEWED);
    }

    public static function isPosted(mixed $value): bool
    {
        return self::equals($value, self::POSTED);
    }

    public static function isClosed(mixed $value): bool
    {
        return self::equals($value, self::CLOSED);
    }

    public static function isDeleted(mixed $value): bool
    {
        return self::equals($value, self::DELETED);
    }

    public static function isLocked(mixed $value): bool
    {
        return in_array(self::normalize($value, ''), self::LOCKED_VALUES, true);
    }

    public static function isPostedOrClosed(mixed $value): bool
    {
        return self::isPosted($value) || self::isClosed($value);
    }

    public static function next(string $currentStatus): ?string
    {
        return self::TRANSITIONS[$currentStatus] ?? null;
    }
}
