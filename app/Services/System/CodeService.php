<?php
namespace App\Services\System;

use App\Models\System\CodeModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;

class CodeService
{
    private readonly PDO $pdo;
    private CodeModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new CodeModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.CodeService');
    }

    public function getList(array $filters = []): array
    {
        try {
            return $this->model->getList($filters);
        } catch (\Throwable $e) {
            $this->logger->error('getList() failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getOptionsByGroup(string $codeGroup): array
    {
        $codeGroup = trim($codeGroup);

        if ($codeGroup === '') {
            return [];
        }

        try {
            return $this->model->getOptionsByGroup($codeGroup);
        } catch (\Throwable $e) {
            $this->logger->error('getOptionsByGroup() failed', [
                'code_group' => $codeGroup,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getById(string $id): ?array
    {
        try {
            return $this->model->getById($id);
        } catch (\Throwable $e) {
            $this->logger->error('getById() failed', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function getGroups(): array
    {
        try {
            return $this->model->getGroups();
        } catch (\Throwable $e) {
            $this->logger->error('getGroups() failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function save(array $data, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            $this->pdo->beginTransaction();

            $id = trim((string)($data['id'] ?? ''));
            $sortNoProvided = array_key_exists('sort_no', $data) && $data['sort_no'] !== '' && $data['sort_no'] !== null;
            $data = $this->normalize($data);
            $duplicateExcludeId = $id !== '' ? $id : null;

            if ($this->model->existsByGroupAndCode($data['code_group'], $data['code'], $duplicateExcludeId)) {
                throw new \Exception('이미 등록된 코드입니다.');
            }

            if ($id !== '') {
                $before = $this->model->getById($id);
                if (!$before) {
                    throw new \Exception('기준정보를 찾을 수 없습니다.');
                }

                if (!$sortNoProvided) {
                    $data['sort_no'] = (int)($before['sort_no'] ?? 0);
                }

                $data['updated_by'] = $actor;
                unset($data['id']);

                if (!$this->model->updateById($id, $data)) {
                    throw new \Exception('기준정보 수정에 실패했습니다.');
                }

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id' => $id,
                    'sort_no' => $data['sort_no'] ?? ($before['sort_no'] ?? null),
                ];
            }

            $newId = UuidHelper::generate();
            $newSortNo = $sortNoProvided
                ? (int)$data['sort_no']
                : SequenceHelper::next('system_codes', 'sort_no');

            $insertData = array_merge($data, [
                'id' => $newId,
                'sort_no' => $newSortNo,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            if (!$this->model->create($insertData)) {
                throw new \Exception('기준정보 등록에 실패했습니다.');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $newId,
                'sort_no' => $newSortNo,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('save() failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function delete(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            if (!$this->model->getById($id)) {
                return ['success' => false, 'message' => '기준정보를 찾을 수 없습니다.'];
            }

            return [
                'success' => $this->model->deleteById($id, $actor),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTrashList(): array
    {
        try {
            return $this->model->getDeleted();
        } catch (\Throwable $e) {
            $this->logger->error('getTrashList() failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function restore(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            return [
                'success' => $this->model->restoreById($id, $actor),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
        $count = 0;

        if (empty($ids)) {
            return ['success' => false, 'message' => '복원할 항목을 선택하세요.'];
        }

        foreach ($ids as $id) {
            if ($this->model->restoreById((string)$id, $actor)) {
                $count++;
            }
        }

        return ['success' => true, 'message' => "복원 완료 ({$count}건)"];
    }

    public function restoreAll(string $actorType = 'USER'): array
    {
        $ids = array_column($this->model->getDeleted(), 'id');
        return $this->restoreBulk($ids, $actorType);
    }

    public function purge(string $id, string $actorType = 'USER'): array
    {
        ActorHelper::resolve($actorType);

        try {
            return ['success' => $this->model->hardDeleteById($id)];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        ActorHelper::resolve($actorType);
        $count = 0;

        if (empty($ids)) {
            return ['success' => false, 'message' => '삭제할 항목을 선택하세요.'];
        }

        foreach ($ids as $id) {
            if ($this->model->hardDeleteById((string)$id)) {
                $count++;
            }
        }

        return ['success' => true, 'message' => "영구삭제 완료 ({$count}건)"];
    }

    public function purgeAll(string $actorType = 'USER'): array
    {
        return $this->purgeBulk(array_column($this->model->getDeleted(), 'id'), $actorType);
    }

    public function reorder(array $changes): bool
    {
        if (empty($changes)) {
            return true;
        }

        $this->pdo->beginTransaction();

        try {
            foreach ($changes as $row) {
                $this->model->updateSortNo((string)$row['id'], (string)((int)$row['newSortNo'] + 1000000));
            }

            foreach ($changes as $row) {
                $this->model->updateSortNo((string)$row['id'], (string)(int)$row['newSortNo']);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function normalize(array $data): array
    {
        $data['code_group'] = strtoupper(preg_replace('/\s+/', '', trim((string)($data['code_group'] ?? ''))));
        $data['code'] = strtoupper(trim((string)($data['code'] ?? '')));
        $data['code_name'] = trim((string)($data['code_name'] ?? ''));
        $data['note'] = $this->blankToNull($data['note'] ?? null);
        $data['memo'] = $this->blankToNull($data['memo'] ?? null);
        $data['extra_data'] = $this->blankToNull($data['extra_data'] ?? null);
        $data['is_active'] = (int)($data['is_active'] ?? 1);
        $data['sort_no'] = isset($data['sort_no']) && $data['sort_no'] !== ''
            ? (int)$data['sort_no']
            : null;

        if ($data['code_group'] === '' || !preg_match('/^[A-Z_]+$/', $data['code_group'])) {
            throw new \InvalidArgumentException('코드그룹은 영문 대문자와 _만 사용할 수 있습니다.');
        }

        return $data;
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    public function downloadTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('기준정보 업로드');

        $headers = ['코드그룹', '코드', '코드명', '비고', '메모', '사용여부', '추가속성'];
        $sample = ['CLIENT_TYPE', 'SUPPLIER', '매입처', '거래유형 예시', '관리자 메모', '1', '{}'];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([$sample], null, 'A2');

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="code_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    public function saveFromExcelFile(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);

            if (count($rows) < 2) {
                return ['success' => false, 'message' => '업로드할 데이터가 없습니다.'];
            }

            $header = array_map(fn($value) => trim((string)$value), array_shift($rows));
            $map = array_flip($header);
            $count = 0;

            foreach ($rows as $row) {
                if (count(array_filter($row, fn($value) => trim((string)$value) !== '')) === 0) {
                    continue;
                }

                $payload = [
                    'code_group' => trim((string)($row[$map['코드그룹'] ?? -1] ?? '')),
                    'code' => trim((string)($row[$map['코드'] ?? -1] ?? '')),
                    'code_name' => trim((string)($row[$map['코드명'] ?? -1] ?? '')),
                    'note' => trim((string)($row[$map['비고'] ?? -1] ?? ($row[$map['설명'] ?? -1] ?? ''))),
                    'memo' => trim((string)($row[$map['메모'] ?? -1] ?? '')),
                    'is_active' => $this->parseActiveValue($row[$map['사용여부'] ?? -1] ?? '1'),
                    'extra_data' => trim((string)($row[$map['추가속성'] ?? -1] ?? '')),
                ];

                if ($payload['code_group'] === '' || $payload['code'] === '' || $payload['code_name'] === '') {
                    continue;
                }

                $result = $this->save($payload, 'SYSTEM');
                if (!empty($result['success'])) {
                    $count++;
                }
            }

            $spreadsheet->disconnectWorksheets();

            return ['success' => true, 'message' => "{$count}건 업로드되었습니다."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function downloadExcel(): void
    {
        $rows = $this->model->getList();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('기준정보 목록');

        $sheet->fromArray(['순번', '코드그룹', '코드', '코드명', '비고', '메모', '사용여부', '추가속성'], null, 'A1');

        $rowNo = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([[
                $row['sort_no'] ?? '',
                $row['code_group'] ?? '',
                $row['code'] ?? '',
                $row['code_name'] ?? '',
                $row['note'] ?? '',
                $row['memo'] ?? '',
                (string)($row['is_active'] ?? '1') === '1' ? '사용' : '미사용',
                $row['extra_data'] ?? '',
            ]], null, 'A' . $rowNo);
            $rowNo++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="code_list.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    public function downloadMigrationTemplate(): void
    {
        $this->downloadTemplate();
    }

    public function saveFromMigrationExcelFile(string $filePath): array
    {
        return $this->saveFromExcelFile($filePath);
    }

    public function downloadMigrationExcel(): void
    {
        $this->downloadExcel();
    }

    private function parseActiveValue(mixed $value): int
    {
        $normalized = mb_strtolower(trim((string)$value), 'UTF-8');

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'use', 'active', '사용'], true) ? 1 : 0;
    }
}
