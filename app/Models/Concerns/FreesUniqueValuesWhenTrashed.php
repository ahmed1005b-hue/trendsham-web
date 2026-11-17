<?php

namespace App\Models\Concerns;

/**
 * مع الحذف المؤقت: يحرّر القيم الفريدة (مثل الرابط أو رمز الكوبون) عند الحذف
 * ليُعاد استعمالها، ويُرجعها عند الاسترجاع إن بقيت متاحة.
 *
 * يستعمله الموديل مع SoftDeletes ويعرّف الثابت UNIQUE_ON_TRASH بأسماء الأعمدة.
 */
trait FreesUniqueValuesWhenTrashed
{
    private const TRASH_MARK = '~del';

    public static function bootFreesUniqueValuesWhenTrashed(): void
    {
        static::softDeleted(function (self $model): void {
            $model->markUniqueValuesAsTrashed();
        });

        static::restoring(function (self $model): void {
            $model->reclaimUniqueValues();
        });
    }

    private function markUniqueValuesAsTrashed(): void
    {
        foreach (static::UNIQUE_ON_TRASH as $column) {
            $value = $this->getAttribute($column);
            if ($value !== null && $value !== '' && ! str_contains((string) $value, self::TRASH_MARK)) {
                $this->setAttribute($column, $value.self::TRASH_MARK.$this->getKey());
            }
        }

        $this->saveQuietly();
    }

    private function reclaimUniqueValues(): void
    {
        foreach (static::UNIQUE_ON_TRASH as $column) {
            $value = (string) $this->getAttribute($column);
            $mark = self::TRASH_MARK.$this->getKey();
            if (! str_ends_with($value, $mark)) {
                continue;
            }

            $original = substr($value, 0, -strlen($mark));
            $taken = static::query()->where($column, $original)->whereKeyNot($this->getKey())->exists();
            $this->setAttribute($column, $taken ? $original.'-'.$this->getKey() : $original);
        }
    }
}
