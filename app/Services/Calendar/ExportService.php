<?php
namespace App\Services\Calendar;

use PDO;
use Core\LoggerFactory;
use App\Services\Calendar\QueryService;

class ExportService
{
    private readonly PDO $pdo;
    private QueryService $query;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(PDO $pdo, ?QueryService $query = null)
    {
        $this->pdo   = $pdo;
        $this->query = $query ?: new QueryService($pdo);

        $this->logger = LoggerFactory::getLogger('service-calendar.CalendarExportService');
    }

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
            'id'        => $ev['id'] ?? '',

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
            'id',
            '캘린더ID',
            '컬렉션'
        ];
    }
}
