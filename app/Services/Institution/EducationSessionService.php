<?php

namespace App\Services\Institution;

use App\Models\Institution\EducationModel;
use App\Models\Institution\EducationSessionModel;
use App\Services\System\NotificationService;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class EducationSessionService
{
    private EducationSessionModel $sessions;
    private EducationModel $records;
    private NotificationService $notifications;
    private LoggerInterface $logger;

    public function __construct(private readonly PDO $db)
    {
        $this->sessions = new EducationSessionModel($db);
        $this->records = new EducationModel($db);
        $this->notifications = new NotificationService($db);
        $this->logger = LoggerFactory::getLogger('service-institution-education-session');
    }

    public function list(array $query): array
    {
        $page = $this->sessions->page($query);
        return ['success' => true, 'data' => $page['rows'], 'draw' => (int) ($query['draw'] ?? 0), 'recordsTotal' => $page['total'], 'recordsFiltered' => $page['total']];
    }

    public function targetList(string $sessionId, array $query): array
    {
        $this->requiredId($sessionId, '교육회차');
        $page = $this->sessions->targetPage($sessionId, $query);
        return ['success' => true, 'data' => $page['rows'], 'draw' => (int) ($query['draw'] ?? 0), 'recordsTotal' => $page['total'], 'recordsFiltered' => $page['total']];
    }

    public function detail(string $id): array
    {
        $row = $this->sessions->detail($this->requiredId($id, '교육회차'));
        if (!$row) throw new \InvalidArgumentException('교육회차를 찾을 수 없습니다.');
        return ['success' => true, 'data' => $row];
    }

    public function save(array $input): array
    {
        $id = trim((string) ($input['id'] ?? '')); $actor = ActorHelper::user(); $now = date('Y-m-d H:i:s');
        $courseId = $this->required($input, 'course_id', '교육과정'); $course = $this->sessions->course($courseId);
        if (!$course || !(int) $course['is_active']) throw new \InvalidArgumentException('사용 가능한 교육과정을 선택해 주세요.');
        $startsAt = $this->datetime($input, 'starts_at', '교육 시작일시'); $endsAt = $this->datetime($input, 'ends_at', '교육 종료일시');
        if (strtotime($endsAt) <= strtotime($startsAt)) throw new \InvalidArgumentException('교육 종료일시는 시작일시보다 늦어야 합니다.');
        $organizer = $this->nullable($input, 'organizer_employee_id');
        if ($organizer !== null && !$this->sessions->employee($organizer)) throw new \InvalidArgumentException('교육 주관 직원을 찾을 수 없습니다.');
        $data = ['course_id' => $courseId, 'title' => $this->required($input, 'title', '교육회차명'), 'starts_at' => $startsAt, 'ends_at' => $endsAt,
            'location_name' => $this->nullable($input, 'location_name'), 'organizer_employee_id' => $organizer, 'instructor_name' => $this->nullable($input, 'instructor_name'),
            'note' => $this->nullable($input, 'note'), 'updated_at' => $now, 'updated_by' => $actor];
        $events = [];
        $this->db->beginTransaction();
        try {
            if ($id === '') {
                $id = UuidHelper::generate(); $data += ['id' => $id, 'status_code' => 'DRAFT', 'request_key' => $this->requestKey($input), 'created_at' => $now, 'created_by' => $actor];
                $this->sessions->createSession($data); $after = $this->sessions->detail($id); $this->audit('SESSION', $id, null, 'CREATE', null, $after, $input, $actor);
            } else {
                $before = $this->sessions->detail($id, true); if (!$before) throw new \InvalidArgumentException('교육회차를 찾을 수 없습니다.');
                if (!in_array($before['status_code'], ['DRAFT','SCHEDULED'], true)) throw new \InvalidArgumentException('완료 또는 취소된 교육회차는 수정할 수 없습니다.');
                $this->sessions->updateSession($id, $data); $after = $this->sessions->detail($id); $this->audit('SESSION', $id, null, 'UPDATE', $before, $after, $input, $actor);
                if ($before['status_code'] === 'SCHEDULED' && $this->importantChanged($before, $after)) {
                    $events[] = $this->notifyTraining('TRAINING_UPDATED', $after, $this->sessions->activeTargets($id), '교육 일정 또는 주요 내용이 변경되었습니다.', 'TRAINING_UPDATED:' . $id . ':' . $this->importantRevision($after), $actor);
                }
            }
            $this->db->commit(); return ['success' => true, 'data' => $after, 'events' => $events, 'message' => '저장되었습니다.'];
        } catch (\Throwable $e) { $this->db->rollBack(); $this->logOperationException($e); throw $e; }
    }

    public function transition(array $input): array
    {
        $id = $this->required($input, 'id', '교육회차'); $action = strtoupper($this->required($input, 'action', '처리유형'));
        $actor = ActorHelper::user(); $events = [];
        $this->db->beginTransaction();
        try {
            $before = $this->sessions->detail($id, true); if (!$before) throw new \InvalidArgumentException('교육회차를 찾을 수 없습니다.');
            if ($action === 'SCHEDULE') {
                if ($before['status_code'] !== 'DRAFT') throw new \InvalidArgumentException('작성중 교육회차만 일정을 확정할 수 있습니다.');
                if ($this->sessions->activeTargets($id) === []) throw new \InvalidArgumentException('대상자를 한 명 이상 지정해 주세요.');
                $status = 'SCHEDULED';
            } elseif ($action === 'CANCEL') {
                if (!in_array($before['status_code'], ['DRAFT','SCHEDULED'], true)) throw new \InvalidArgumentException('취소할 수 없는 교육회차입니다.');
                $this->required($input, 'reason', '취소 사유'); $status = 'CANCELLED';
            } elseif ($action === 'COMPLETE') {
                if ($before['status_code'] !== 'SCHEDULED') throw new \InvalidArgumentException('일정확정 교육회차만 완료할 수 있습니다.');
                $targets = $this->sessions->activeTargets($id, true); if ($targets === []) throw new \InvalidArgumentException('교육 대상자가 없습니다.');
                foreach ($targets as $target) {
                    if ($target['attendance_status_code'] === 'NOT_RECORDED') throw new \InvalidArgumentException('참석 결과가 입력되지 않은 대상자가 있습니다.');
                    if ($target['attendance_status_code'] === 'ATTENDED' && $target['completion_status_code'] === 'PENDING') throw new \InvalidArgumentException('이수 결과가 입력되지 않은 참석자가 있습니다.');
                    if ($target['attendance_status_code'] === 'ABSENT') continue;
                    $this->createEmployeeRecord($before, $target, $actor);
                }
                $status = 'COMPLETED';
            } else throw new \InvalidArgumentException('지원하지 않는 상태 전환입니다.');
            $this->sessions->updateSession($id, ['status_code' => $status, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $actor]);
            $after = $this->sessions->detail($id); $this->audit('SESSION', $id, null, $action, $before, $after, $input, $actor);
            if ($action === 'SCHEDULE') $events[] = $this->notifyTraining('TRAINING_ASSIGNED', $after, $this->sessions->activeTargets($id), '교육 일정과 대상자가 확정되었습니다.', 'TRAINING_ASSIGNED:' . $id . ':SCHEDULED', $actor);
            if ($action === 'CANCEL') $events[] = $this->notifyTraining('TRAINING_CANCELLED', $after, $this->sessions->activeTargets($id), '교육 일정이 취소되었습니다.', 'TRAINING_CANCELLED:' . $id, $actor);
            $this->db->commit(); return ['success' => true, 'data' => $after, 'events' => $events, 'message' => '처리되었습니다.'];
        } catch (\Throwable $e) { $this->db->rollBack(); $this->logOperationException($e); throw $e; }
    }

    public function addTargets(array $input): array
    {
        $sessionId = $this->required($input, 'session_id', '교육회차'); $ids = array_values(array_unique(array_filter(array_map('strval', (array) ($input['employee_ids'] ?? [])))));
        if ($ids === []) throw new \InvalidArgumentException('대상 직원을 선택해 주세요.');
        $source = strtoupper(trim((string) ($input['assignment_source_code'] ?? 'INDIVIDUAL'))); $actor = ActorHelper::user(); $events = [];
        $this->db->beginTransaction();
        try {
            $session = $this->sessions->detail($sessionId, true); if (!$session) throw new \InvalidArgumentException('교육회차를 찾을 수 없습니다.');
            if (!in_array($session['status_code'], ['DRAFT','SCHEDULED'], true)) throw new \InvalidArgumentException('현재 상태에서는 대상자를 추가할 수 없습니다.');
            if ($session['status_code'] === 'SCHEDULED') $this->required($input, 'reason', '대상 변경 사유');
            foreach ($ids as $employeeId) {
                if (!$this->sessions->employee($employeeId)) throw new \InvalidArgumentException('대상 직원을 찾을 수 없습니다.');
                $now = date('Y-m-d H:i:s'); $id = UuidHelper::generate();
                $data = ['id' => $id, 'session_id' => $sessionId, 'employee_id' => $employeeId, 'assignment_source_code' => $source,
                    'acknowledged_at' => null, 'attendance_status_code' => 'NOT_RECORDED', 'completion_status_code' => 'PENDING',
                    'removed_at' => null, 'removed_by' => null, 'request_key' => $this->requestKey($input) . '-' . $employeeId,
                    'created_at' => $now, 'created_by' => $actor, 'updated_at' => $now, 'updated_by' => $actor];
                $targetId = $this->sessions->addTarget($data); $after = $this->sessions->target($targetId); $this->audit('SESSION_TARGET', $targetId, $employeeId, 'ADD', null, $after, $input, $actor);
                if ($session['status_code'] === 'SCHEDULED') {
                    $target = $this->sessions->employee($employeeId);
                    $events[] = $this->notifyTraining('TRAINING_ASSIGNED', $session, [[
                        'employee_id' => $employeeId,
                        'user_id' => $target['user_id'] ?? null,
                    ]], '교육 대상자로 추가되었습니다.', 'TRAINING_ASSIGNED:' . $sessionId . ':' . $employeeId . ':' . (string) $data['request_key'], $actor);
                }
            }
            $this->db->commit(); return ['success' => true, 'events' => $events, 'message' => '대상자를 추가했습니다.'];
        } catch (\Throwable $e) { $this->db->rollBack(); $this->logOperationException($e); throw $e; }
    }

    public function removeTarget(array $input): array
    {
        $id = $this->required($input, 'id', '교육대상'); $actor = ActorHelper::user();
        $this->db->beginTransaction();
        try {
            $before = $this->sessions->target($id, true); if (!$before || $before['removed_at'] !== null) throw new \InvalidArgumentException('교육대상을 찾을 수 없습니다.');
            $session = $this->sessions->detail((string) $before['session_id'], true); if (!$session || !in_array($session['status_code'], ['DRAFT','SCHEDULED'], true)) throw new \InvalidArgumentException('현재 상태에서는 대상자를 제외할 수 없습니다.');
            if ($session['status_code'] === 'SCHEDULED') $this->required($input, 'reason', '대상 변경 사유');
            $this->sessions->updateTarget($id, ['removed_at' => date('Y-m-d H:i:s'), 'removed_by' => $actor, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $actor]);
            $after = $this->sessions->target($id); $this->audit('SESSION_TARGET', $id, (string) $before['employee_id'], 'REMOVE', $before, $after, $input, $actor);
            $this->db->commit(); return ['success' => true, 'message' => '대상자에서 제외했습니다.'];
        } catch (\Throwable $e) { $this->db->rollBack(); $this->logOperationException($e); throw $e; }
    }

    public function updateOutcome(array $input): array
    {
        $id = $this->required($input, 'id', '교육대상'); $attendance = strtoupper($this->required($input, 'attendance_status_code', '참석상태')); $completion = strtoupper($this->required($input, 'completion_status_code', '이수상태'));
        if (!in_array($attendance, ['NOT_RECORDED','ATTENDED','ABSENT'], true) || !in_array($completion, ['PENDING','COMPLETED','NOT_COMPLETED'], true)) throw new \InvalidArgumentException('참석 또는 이수 상태가 올바르지 않습니다.');
        if ($attendance === 'ABSENT') $completion = 'PENDING'; if ($attendance === 'NOT_RECORDED' && $completion !== 'PENDING') throw new \InvalidArgumentException('참석 미처리 상태에서는 이수 결과를 입력할 수 없습니다.');
        $actor = ActorHelper::user(); $this->db->beginTransaction();
        try {
            $before = $this->sessions->target($id, true); if (!$before || $before['removed_at'] !== null) throw new \InvalidArgumentException('교육대상을 찾을 수 없습니다.');
            $session = $this->sessions->detail((string) $before['session_id'], true); if (!$session || $session['status_code'] !== 'SCHEDULED') throw new \InvalidArgumentException('일정확정 교육회차에서만 결과를 입력할 수 있습니다.');
            $this->sessions->updateTarget($id, ['attendance_status_code' => $attendance, 'completion_status_code' => $completion, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $actor]);
            $after = $this->sessions->target($id); $action = $before['attendance_status_code'] !== $attendance ? 'ATTENDANCE' : 'COMPLETION'; $this->audit('SESSION_TARGET', $id, (string) $before['employee_id'], $action, $before, $after, $input, $actor);
            $this->db->commit(); return ['success' => true, 'data' => $after, 'message' => '교육 결과를 저장했습니다.'];
        } catch (\Throwable $e) { $this->db->rollBack(); $this->logOperationException($e); throw $e; }
    }

    public function acknowledge(string $targetId, string $employeeId, array $input): array
    {
        $actor = ActorHelper::user(); $this->db->beginTransaction();
        try {
            $before = $this->sessions->target($this->requiredId($targetId, '교육대상'), true);
            if (!$before || $before['removed_at'] !== null || (string) $before['employee_id'] !== $employeeId) throw new \RuntimeException('본인의 교육대상만 확인할 수 있습니다.');
            if ($before['acknowledged_at'] === null) $this->sessions->updateTarget($targetId, ['acknowledged_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $actor]);
            $after = $this->sessions->target($targetId); $this->audit('SESSION_TARGET', $targetId, $employeeId, 'ACKNOWLEDGE', $before, $after, $input, $actor);
            $this->db->commit(); return ['success' => true, 'data' => $after, 'message' => '교육내용을 확인했습니다.'];
        } catch (\Throwable $e) { $this->db->rollBack(); $this->logOperationException($e); throw $e; }
    }

    private function createEmployeeRecord(array $session, array $target, string $actor): void
    {
        if ($this->sessions->recordForTarget((string) $session['id'], (string) $target['employee_id'])) return;
        $now = date('Y-m-d H:i:s'); $id = UuidHelper::generate(); $minutes = max(1, (int) round((strtotime($session['ends_at']) - strtotime($session['starts_at'])) / 60));
        $record = ['id' => $id, 'employee_id' => $target['employee_id'], 'course_id' => $session['course_id'], 'session_id' => $session['id'], 'education_name' => $session['title'],
            'institution_name' => $session['default_institution_name'], 'education_start_at' => $session['starts_at'], 'education_end_at' => $session['ends_at'], 'education_minutes' => $minutes,
            'attendance_status_code' => 'ATTENDED', 'completion_status_code' => $target['completion_status_code'], 'completion_number' => null, 'valid_from' => null, 'valid_to' => null,
            'renewal_due_date' => null, 'attachment_path' => null, 'attachment_name' => null, 'note' => '교육회차 완료로 생성',
            'request_key' => 'EDUCATION-SESSION-RECORD-' . $session['id'] . '-' . $target['employee_id'], 'created_at' => $now, 'created_by' => $actor, 'updated_at' => $now, 'updated_by' => $actor,
            'deleted_at' => null, 'deleted_by' => null];
        $this->records->createRecord($record); $this->records->audit($this->auditRow('EMPLOYEE_RECORD', $id, (string) $target['employee_id'], 'CREATE', null, $record, ['reason' => '교육회차 결과 확정'], $actor));
    }

    private function audit(string $source, string $target, ?string $employee, string $action, ?array $before, ?array $after, array $input, string $actor): void { $this->sessions->audit($this->auditRow($source, $target, $employee, $action, $before, $after, $input, $actor)); $this->logger->info('교육회차 업무 처리를 완료했습니다.', ['event_code' => 'EDUCATION_SESSION_' . $action, 'result' => 'SUCCESS', 'source_type' => $source, 'target_id' => $target, 'employee_id' => $employee, 'actor' => $actor]); }
    private function logOperationException(\Throwable $exception): void
    {
        $blocked = $exception instanceof \InvalidArgumentException || $exception instanceof \DomainException;
        $level = $blocked ? 'warning' : 'error';
        $this->logger->{$level}($blocked ? '교육회차 업무 처리가 차단되었습니다.' : '교육회차 업무 처리에 실패했습니다.', [
            'event_code' => $blocked ? 'EDUCATION_SESSION_BLOCKED' : 'EDUCATION_SESSION_FAILED',
            'result' => $blocked ? 'BLOCKED' : 'FAILED',
            'error_code' => get_class($exception),
            'error' => $exception,
        ]);
    }
    private function auditRow(string $source, string $target, ?string $employee, string $action, ?array $before, ?array $after, array $input, string $actor): array { return ['id' => UuidHelper::generate(), 'target_id' => $target, 'employee_id' => $employee, 'action_type_code' => $action, 'source_type_code' => $source, 'reason' => trim((string) ($input['reason'] ?? '교육 운영 처리')) ?: '교육 운영 처리', 'request_key' => $this->requestKey($input) . '-AUDIT-' . UuidHelper::generate(), 'before_data' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, 'after_data' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, 'processed_by' => $actor, 'processed_at' => date('Y-m-d H:i:s')]; }
    private function notifyTraining(string $type, array $session, array $targets, string $reason, string $eventKey, string $actor): array
    {
        $userIds = array_values(array_unique(array_filter(array_map(static fn(array $target): string => trim((string) ($target['user_id'] ?? '')), $targets))));
        if ($userIds === []) return ['event_type' => $type, 'event_key' => $eventKey, 'recipient_count' => 0, 'unlinked_employee_count' => count($targets)];
        $title = match ($type) { 'TRAINING_ASSIGNED' => '[교육 배정] ' . (string) $session['title'], 'TRAINING_UPDATED' => '[교육 변경] ' . (string) $session['title'], 'TRAINING_CANCELLED' => '[교육 취소] ' . (string) $session['title'], default => '[교육 알림] ' . (string) $session['title'] };
        $eventId = $this->notifications->createEvent([
            'event_type_code' => $type, 'source_domain_code' => 'EDUCATION_SESSION', 'source_id' => (string) $session['id'],
            'event_key' => $eventKey, 'title' => $title, 'message' => $reason,
            'payload' => ['starts_at' => $session['starts_at'] ?? null, 'ends_at' => $session['ends_at'] ?? null, 'location_name' => $session['location_name'] ?? null],
            'delivery_policy_code' => 'MANDATORY', 'importance_code' => $type === 'TRAINING_CANCELLED' ? 'HIGH' : 'NORMAL',
            'action_page_key' => 'web.institution.human_resources.qualification_education', 'action_entity_type_code' => 'EDUCATION_SESSION', 'action_entity_id' => (string) $session['id'],
            'action_params' => ['tab' => 'education-status', 'session_id' => (string) $session['id']],
            'action_url_fallback' => '/institution/human-resources/qualification-education?tab=education-status&session_id=' . rawurlencode((string) $session['id']),
            'created_by' => $actor,
        ], $userIds);
        return ['event_type' => $type, 'event_key' => $eventKey, 'event_id' => $eventId, 'recipient_count' => count($userIds), 'unlinked_employee_count' => count($targets) - count($userIds)];
    }
    private function importantRevision(array $session): string { $values=[]; foreach (['title','starts_at','ends_at','location_name','instructor_name'] as $key) $values[$key]=$session[$key]??null; return substr(hash('sha256',json_encode($values,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),0,24); }
    private function importantChanged(array $before, array $after): bool { foreach (['starts_at','ends_at','location_name','instructor_name'] as $key) if ((string) ($before[$key] ?? '') !== (string) ($after[$key] ?? '')) return true; return false; }
    private function required(array $input, string $key, string $label): string { $value = trim((string) ($input[$key] ?? '')); if ($value === '') throw new \InvalidArgumentException($label . ' 항목은 필수입니다.'); return $value; }
    private function requiredId(string $value, string $label): string { $value = trim($value); if ($value === '') throw new \InvalidArgumentException($label . ' ID가 필요합니다.'); return $value; }
    private function nullable(array $input, string $key): ?string { $value = trim((string) ($input[$key] ?? '')); return $value === '' ? null : $value; }
    private function datetime(array $input, string $key, string $label): string { $value = $this->required($input, $key, $label); $time = strtotime($value); if ($time === false) throw new \InvalidArgumentException($label . ' 형식이 올바르지 않습니다.'); return date('Y-m-d H:i:s', $time); }
    private function requestKey(array $input): string { return trim((string) ($input['request_key'] ?? '')) ?: 'EDUCATION-SESSION-' . UuidHelper::generate(); }
}
