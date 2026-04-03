<?php
// 경로: PROJECT_ROOT . '/app/services/calendar/CalendarExportService.php'
namespace App\Services\Calendar;

use PDO;
use Core\LoggerFactory;

/**
 * =========================================================
 * CalendarExportService
 * - 캘린더 데이터 "내보내기 전용"
 * - Excel / CSV / (PDF 예정)
 * - ❌ CalDAV 직접 호출 없음
 * =========================================================
 */
class CalendarExportService
{
    private readonly PDO $pdo;
    private CalendarQueryService $query;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(PDO $pdo, ?CalendarQueryService $query = null)
    {
        $this->pdo   = $pdo;
        $this->query = $query ?: new CalendarQueryService($pdo);

        $this->logger = LoggerFactory::getLogger('service-calendar.CalendarExportService');
    }

    /* =========================================================
     * Excel Export
     * ========================================================= */

    public function exportExcel(string $dept, ?string $from, ?string $to): array
    {
        $events = $this->query->getEventsFull($dept, $from, $to);

        $rows = [];
        foreach ($events as $ev) {
            $rows[] = $this->mapEventToRow($ev);
        }

        return [
            'headers' => $this->excelHeaders(),
            'rows'    => $rows,
            'meta'    => [
                'department' => $dept,
                'from' => $from,
                'to'   => $to,
                'count' => count($rows),
            ]
        ];
    }

    /* =========================================================
     * CSV Export
     * ========================================================= */

    public function exportCsv(string $dept, ?string $from, ?string $to): string
    {
        $data = $this->exportExcel($dept, $from, $to);

        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $data['headers']);

        foreach ($data['rows'] as $row) {
            fputcsv($fp, $row);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }

    /* =========================================================
     * Row Mapping
     * ========================================================= */

    private function mapEventToRow(array $ev): array
    {
        return [
            'calendar'    => $ev['calendar_name'] ?? '',
            'title'       => $ev['title'] ?? '',
            'start'       => $ev['start'] ?? '',
            'end'         => $ev['end'] ?? '',
            'all_day'     => !empty($ev['allDay']) ? 'Y' : 'N',

            'creator'    => $ev['creator_displayname']
                            ?? $ev['creator_account']
                            ?? '',

            'location'   => $ev['location'] ?? '',
            'status'     => $ev['status'] ?? '',
            'component'  => $ev['component'] ?? '',
            'uid'        => $ev['uid'] ?? '',

            'calendar_id' => $ev['calendar_id'] ?? '',
            'collection'  => $ev['collection_href'] ?? '',
        ];
    }

    private function excelHeaders(): array
    {
        return [
            '캘린더',
            '제목',
            '시작',
            '종료',
            '종일',
            '작성자',
            '장소',
            '상태',
            '유형',
            'UID',
            '캘린더ID',
            '컬렉션'
        ];
    }
}
