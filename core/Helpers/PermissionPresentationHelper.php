<?php

namespace Core\Helpers;

final class PermissionPresentationHelper
{
    private const EXACT_PERMISSIONS = [
        'work_team.view' => ['조회', '팀 조회·다운로드', '팀 목록·상세·휴지통을 조회하고 양식과 엑셀 자료를 내려받을 수 있습니다.', 'normal'],
        'work_team.save' => ['작성·수정', '팀 저장·복구·업로드', '팀 자료를 저장·복구·정렬하고 엑셀 자료를 업로드할 수 있습니다.', 'important'],
        'work_team.delete' => ['삭제·복구', '팀 삭제·영구삭제', '팀 자료를 삭제하고 휴지통 자료를 영구삭제할 수 있습니다.', 'danger'],
        'code.view' => ['조회', '코드 조회', '코드 목록·상세·그룹·참조내역을 조회할 수 있습니다.', 'normal'],
        'code.save' => ['작성·수정', '코드 저장·정렬', '코드 자료를 저장하고 표시 순서를 변경할 수 있습니다.', 'important'],
        'code.delete' => ['삭제·복구', '코드 삭제', '참조되지 않는 코드 자료를 삭제할 수 있습니다.', 'danger'],
        'api.ledger.voucher.list' => ['조회', '전표 조회', '전표입력과 전표검토에 필요한 전표 목록 및 상세 내용을 조회할 수 있습니다.', 'normal'],
        'api.ledger.voucher.review' => ['승인·상태처리', '전표 검토처리', '전표 검토완료 또는 반려 처리를 수행할 수 있습니다.', 'important'],
        'api.institution.human_resources.qualification_education.education_all_list' => ['조회', '전체 교육운영 조회', '전체 직원의 교육이력·교육일정·교육대상과 상세 내용을 조회할 수 있습니다.', 'normal'],
        'api.institution.human_resources.qualification_education.education_list' => ['조회', '본인 교육 조회', '본인의 교육이력과 교육내용을 조회할 수 있습니다.', 'normal'],
        'api.institution.human_resources.qualification_education.education_manage' => ['작성·수정', '교육운영 관리', '교육이수·교육일정·교육대상·참석이수 상태를 등록하거나 변경할 수 있습니다.', 'important'],
    ];

    private const ACTIONS = [
        'list' => ['조회', '목록 조회', '목록을 조회할 수 있습니다.', 'normal'],
        'detail' => ['조회', '상세 조회', '상세 내용을 조회할 수 있습니다.', 'normal'],
        'view' => ['조회', '화면 조회', '화면과 기본 정보를 조회할 수 있습니다.', 'normal'],
        'options' => ['조회', '선택항목 조회', '입력에 필요한 선택항목을 조회할 수 있습니다.', 'normal'],
        'search-picker' => ['조회', '선택목록 검색', '선택창에서 사용할 자료를 검색할 수 있습니다.', 'normal'],
        'metadata' => ['조회', '화면 설정 조회', '화면 구성에 필요한 설정을 조회할 수 있습니다.', 'normal'],
        'resolve' => ['조회', '적용기준 조회', '기준일에 적용되는 업무 기준을 조회할 수 있습니다.', 'normal'],
        'source-file' => ['파일', '근거파일 조회', '업무 기준의 원본 근거파일을 조회할 수 있습니다.', 'normal'],
        'revision-chain' => ['조회', '개정이력 조회', '업무 기준의 개정 연결 이력을 조회할 수 있습니다.', 'normal'],
        'correct-revision' => ['작성·수정', '확정기준 정정', '확정된 업무 기준을 원본 이력을 보존하며 정정할 수 있습니다.', 'danger'],
        'revise' => ['작성·수정', '규정 개정', '기존 규정의 이력을 보존하며 새 개정본을 작성할 수 있습니다.', 'important'],
        'copy' => ['작성·수정', '복사 생성', '기존 자료를 기준으로 새 자료를 복사 생성할 수 있습니다.', 'normal'],
        'formats' => ['조회', '양식 목록 조회', '사용 가능한 자료 양식 목록을 조회할 수 있습니다.', 'normal'],
        'data-types' => ['조회', '자료유형 조회', '업로드 가능한 자료유형을 조회할 수 있습니다.', 'normal'],
        'tree' => ['조회', '구조 조회', '자료의 계층 구조를 조회할 수 있습니다.', 'normal'],
        'actions' => ['조회', '가능 작업 조회', '현재 상태에서 실행할 수 있는 작업을 조회할 수 있습니다.', 'normal'],
        'split' => ['작성·수정', '자료 분할', '처리 자료를 여러 항목으로 분할할 수 있습니다.', 'important'],
        'structure-create' => ['작성·수정', '자료구조 생성', '처리 자료의 구조를 생성할 수 있습니다.', 'important'],
        'structure-update' => ['작성·수정', '자료구조 수정', '처리 자료의 구조를 수정할 수 있습니다.', 'important'],
        'recommend-transactions' => ['조회', '거래 추천 조회', '원본자료를 기준으로 연결 가능한 거래를 추천받을 수 있습니다.', 'normal'],
        'recommend-voucher-lines' => ['조회', '전표분개 추천 조회', '원본자료를 기준으로 전표 분개 후보를 추천받을 수 있습니다.', 'normal'],
        'seed-rows' => ['작성·수정', '원본자료 생성', '업로드 자료에서 원본 행을 생성할 수 있습니다.', 'important'],
        'create-bundled-voucher' => ['작성·수정', '묶음전표 생성', '선택 자료를 묶어 전표를 생성할 수 있습니다.', 'important'],
        'number' => ['조회', '전표번호 조회', '전표 작성에 사용할 번호를 조회할 수 있습니다.', 'normal'],
        'databasebackup' => ['시스템관리', '데이터백업 화면 접근', '데이터백업 관리 화면에 접근할 수 있습니다.', 'danger'],
        'calculate' => ['계산', '금액 계산', '업무 기준에 따라 금액을 계산할 수 있습니다.', 'normal'],
        'preflight' => ['상태처리', '처리 전 검증', '저장 또는 상태 변경 전에 필수 조건을 검증할 수 있습니다.', 'normal'],
        'save' => ['작성·수정', '저장', '자료를 새로 작성하거나 수정하여 저장할 수 있습니다.', 'normal'],
        'create' => ['작성·수정', '신규 작성', '새 자료를 작성할 수 있습니다.', 'normal'],
        'update' => ['작성·수정', '수정', '기존 자료를 수정할 수 있습니다.', 'normal'],
        'submit' => ['승인·상태처리', '결재 요청', '작성한 자료를 결재 요청할 수 있습니다.', 'important'],
        'withdraw' => ['승인·상태처리', '결재 요청 회수', '진행 중인 결재 요청을 회수할 수 있습니다.', 'important'],
        'approve' => ['승인·상태처리', '승인', '요청된 자료를 승인할 수 있습니다.', 'important'],
        'reject' => ['승인·상태처리', '반려', '요청된 자료를 반려할 수 있습니다.', 'important'],
        'status' => ['승인·상태처리', '상태 변경', '자료의 업무 상태를 변경할 수 있습니다.', 'important'],
        'activate' => ['승인·상태처리', '사용 활성화', '선택한 자료를 업무에 사용할 수 있도록 활성화합니다.', 'important'],
        'deactivate' => ['승인·상태처리', '사용 비활성화', '선택한 자료가 업무에 사용되지 않도록 비활성화합니다.', 'important'],
        'cancel' => ['승인·상태처리', '처리 취소', '완료되거나 진행 중인 업무 처리를 취소할 수 있습니다.', 'important'],
        'history' => ['조회', '이력 조회', '자료의 변경 및 처리 이력을 조회할 수 있습니다.', 'normal'],
        'delete' => ['삭제·복구', '삭제', '자료를 휴지통으로 이동하거나 삭제할 수 있습니다.', 'important'],
        'trash' => ['삭제·복구', '휴지통 조회', '삭제된 자료를 조회할 수 있습니다.', 'normal'],
        'restore' => ['삭제·복구', '복구', '삭제된 자료를 복구할 수 있습니다.', 'important'],
        'restore-bulk' => ['삭제·복구', '선택 복구', '선택한 삭제 자료를 일괄 복구할 수 있습니다.', 'important'],
        'restore-all' => ['삭제·복구', '전체 복구', '삭제된 자료 전체를 복구할 수 있습니다.', 'danger'],
        'purge' => ['삭제·복구', '영구삭제', '삭제된 자료를 복구할 수 없도록 영구삭제할 수 있습니다.', 'danger'],
        'purge-bulk' => ['삭제·복구', '선택 영구삭제', '선택한 자료를 복구할 수 없도록 영구삭제할 수 있습니다.', 'danger'],
        'purge-all' => ['삭제·복구', '전체 영구삭제', '삭제된 자료 전체를 복구할 수 없도록 영구삭제할 수 있습니다.', 'danger'],
        'reorder' => ['정렬', '순서 변경', '목록의 표시 순서를 변경할 수 있습니다.', 'normal'],
        'template' => ['엑셀', '엑셀 양식 다운로드', '업로드용 엑셀 양식을 내려받을 수 있습니다.', 'normal'],
        'excel' => ['엑셀', '엑셀 다운로드', '조회 자료를 엑셀 파일로 내려받을 수 있습니다.', 'normal'],
        'download' => ['파일', '파일 다운로드', '업무 파일을 내려받을 수 있습니다.', 'normal'],
        'excel-upload' => ['엑셀', '엑셀 업로드', '엑셀 파일로 자료를 일괄 등록할 수 있습니다.', 'important'],
        'excel-upload-preview' => ['엑셀', '엑셀 업로드 사전검증', '엑셀 자료를 저장하기 전에 오류와 반영 내용을 확인할 수 있습니다.', 'normal'],
        'upload' => ['파일', '파일 업로드', '업무 파일을 업로드할 수 있습니다.', 'important'],
        'lock' => ['계정관리', '계정 잠금', '선택한 사용자 계정을 잠금 처리할 수 있습니다.', 'danger'],
        'unlock' => ['계정관리', '계정 잠금해제', '잠긴 사용자 계정을 다시 사용할 수 있도록 해제할 수 있습니다.', 'danger'],
    ];

    public static function decorate(array $permission, string $pageLabel): array
    {
        $permissionKey = trim((string) ($permission['permission_key'] ?? ''));
        $exact = self::EXACT_PERMISSIONS[$permissionKey] ?? null;
        $action = self::actionFromKey($permissionKey);
        $meta = self::ACTIONS[$action] ?? null;
        $name = trim((string) ($permission['permission_name'] ?? ''));
        $description = trim((string) ($permission['description'] ?? ''));

        if ($exact !== null) {
            [$group, $name, $description, $riskLevel] = $exact;
            $meta = [$group, $name, $description, $riskLevel];
        } elseif ($meta !== null && (in_array($action, ['lock', 'unlock'], true) || self::isTechnicalText($name, $action))) {
            $name = $meta[1];
        }
        if ($exact === null && $meta !== null && (in_array($action, ['lock', 'unlock'], true) || self::isTechnicalDescription($description, $action))) {
            $description = $pageLabel . '에서 ' . $meta[2];
        }

        return [
            'permission_name' => $name !== '' ? $name : ($meta[1] ?? '권한 정보 확인 필요'),
            'description' => $description !== '' ? $description : ($pageLabel . ' 권한의 상세 설명을 확인해 주세요.'),
            'capability_group' => $meta[0] ?? '기타',
            'risk_level' => $meta[3] ?? 'normal',
            'metadata_status' => ($meta === null && self::isTechnicalText($name, $action)) ? 'REVIEW_REQUIRED' : 'NORMAL',
        ];
    }

    private static function actionFromKey(string $key): string
    {
        $normalized = str_replace(['_', '/'], ['-', '.'], strtolower($key));
        foreach (array_keys(self::ACTIONS) as $action) {
            if ($normalized === $action || str_ends_with($normalized, '.' . $action)) {
                return $action;
            }
        }
        $parts = explode('.', $normalized);
        return (string) end($parts);
    }

    private static function isTechnicalText(string $text, string $action): bool
    {
        return $text === ''
            || strtolower(str_replace('_', '-', $text)) === $action
            || preg_match('/^[A-Za-z0-9_.\- ]+$/', $text) === 1;
    }

    private static function isTechnicalDescription(string $text, string $action): bool
    {
        if ($text === '') {
            return true;
        }
        $normalized = strtolower(str_replace('_', '-', $text));
        return str_ends_with($normalized, ' ' . $action)
            || preg_match('/[A-Za-z]{3,}[\-_][A-Za-z]{2,}/', $text) === 1;
    }
}
