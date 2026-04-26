<?php
namespace App\Services\System;

use App\Models\System\WorkTeamMemberModel;
use App\Models\System\WorkTeamModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;

class WorkTeamService
{
    private readonly PDO $pdo;
    private WorkTeamModel $model;
    private WorkTeamMemberModel $memberModel;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new WorkTeamModel($pdo);
        $this->memberModel = new WorkTeamMemberModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.WorkTeamService');
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

    public function getById(string $id): ?array
    {
        try {
            return $this->model->getById($id);
        } catch (\Throwable $e) {
            $this->logger->error('getById() failed', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function save(array $data, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            $this->pdo->beginTransaction();

            $id = trim((string)($data['id'] ?? ''));
            $data = $this->normalize($data);

            if ($id !== '') {
                $before = $this->model->getById($id);
                if (!$before) {
                    throw new \Exception('작업팀을 찾을 수 없습니다.');
                }

                $data['updated_by'] = $actor;
                $data['sort_no'] = (int)($before['sort_no'] ?? 0);
                unset($data['id']);

                if (!$this->model->updateById($id, $data)) {
                    throw new \Exception('작업팀 수정에 실패했습니다.');
                }

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id' => $id,
                    'sort_no' => $data['sort_no'] ?? ($before['sort_no'] ?? null),
                ];
            }

            $newId = UuidHelper::generate();
            $newSortNo = SequenceHelper::next('system_work_teams', 'sort_no');

            $insertData = array_merge($data, [
                'id' => $newId,
                'sort_no' => $newSortNo,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            if (!$this->model->create($insertData)) {
                throw new \Exception('작업팀 등록에 실패했습니다.');
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
                return ['success' => false, 'message' => '작업팀을 찾을 수 없습니다.'];
            }

            return ['success' => $this->model->deleteById($id, $actor)];
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
            return ['success' => $this->model->restoreById($id, $actor)];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
        $count = 0;

        foreach ($ids as $id) {
            if ($this->model->restoreById((string)$id, $actor)) {
                $count++;
            }
        }

        return ['success' => true, 'message' => "복원 완료 ({$count}건)"];
    }

    public function restoreAll(string $actorType = 'USER'): array
    {
        return $this->restoreBulk(array_column($this->model->getDeleted(), 'id'), $actorType);
    }

    public function purge(string $id): array
    {
        try {
            return ['success' => $this->model->hardDeleteById($id)];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function purgeBulk(array $ids): array
    {
        $count = 0;

        foreach ($ids as $id) {
            if ($this->model->hardDeleteById((string)$id)) {
                $count++;
            }
        }

        return ['success' => true, 'message' => "영구삭제 완료 ({$count}건)"];
    }

    public function purgeAll(): array
    {
        return $this->purgeBulk(array_column($this->model->getDeleted(), 'id'));
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
        $data['team_name'] = trim((string)($data['team_name'] ?? ''));
        $data['team_leader_client_id'] = $this->blankToNull($data['team_leader_client_id'] ?? null);
        $data['note'] = $this->blankToNull($data['note'] ?? null);
        $data['memo'] = $this->blankToNull($data['memo'] ?? null);
        $data['is_active'] = (int)($data['is_active'] ?? 1);
        return $data;
    }

    public function downloadTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('작업팀 업로드');

        $sheet->fromArray(['팀명', '팀장', '비고', '메모', '사용여부'], null, 'A1');
        $sheet->fromArray([['시공팀', '홍길동 거래처', '현장 작업팀', '관리자 메모', '1']], null, 'A2');

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="work_team_template.xlsx"');
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
                    'team_name' => trim((string)($row[$map['팀명'] ?? -1] ?? '')),
                    'team_leader_client_id' => $this->resolveTeamLeaderClientId(
                        trim((string)($row[$map['팀장'] ?? -1] ?? ($row[$map['팀장 거래처 ID'] ?? -1] ?? '')))
                    ),
                    'note' => trim((string)($row[$map['비고'] ?? -1] ?? '')),
                    'memo' => trim((string)($row[$map['메모'] ?? -1] ?? '')),
                    'is_active' => $this->parseActiveValue($row[$map['사용여부'] ?? -1] ?? '1'),
                ];

                if ($payload['team_name'] === '') {
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
        $sheet->setTitle('작업팀 목록');
        $sheet->fromArray(['순번', '팀명', '팀장', '비고', '메모', '사용여부'], null, 'A1');

        $rowNo = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([[
                $row['sort_no'] ?? '',
                $row['team_name'] ?? '',
                $row['team_leader_client_name'] ?? '',
                $row['note'] ?? '',
                $row['memo'] ?? '',
                (string)($row['is_active'] ?? '1') === '1' ? '사용' : '미사용',
            ]], null, 'A' . $rowNo);
            $rowNo++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="work_team_list.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    private function parseActiveValue(mixed $value): int
    {
        $normalized = mb_strtolower(trim((string)$value), 'UTF-8');
        return in_array($normalized, ['1', 'true', 'yes', 'y', 'use', 'active', '사용'], true) ? 1 : 0;
    }

    private function resolveTeamLeaderClientId(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM system_clients
            WHERE deleted_at IS NULL
              AND (id = :id_value OR client_name = :name_value)
            ORDER BY CASE WHEN id = :order_value THEN 0 ELSE 1 END, sort_no ASC
            LIMIT 1
        ");
        $stmt->execute([
            ':id_value' => $value,
            ':name_value' => $value,
            ':order_value' => $value,
        ]);

        $id = $stmt->fetchColumn();
        return $id ? (string)$id : null;
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
