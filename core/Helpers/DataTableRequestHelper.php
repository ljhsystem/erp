<?php

namespace Core\Helpers;

final class DataTableRequestHelper
{
    /**
     * DataTables server-side 목록 입력을 form-urlencoded POST body에서 읽는다.
     * URL에 남겨 둔 짧은 화면 범위 값은 body보다 낮은 우선순위로 병합한다.
     */
    public static function input(?array $query = null, ?array $body = null): array
    {
        $query ??= $_GET;
        $body ??= $_POST;

        return array_replace($query, $body);
    }
}
