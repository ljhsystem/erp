<?php
// 경로: PROJECT_ROOT . '/core/Helpers/DataHelper.php'
namespace Core\Helpers;

class DataHelper
{


    public static function resequenceCoverImageCodes($pdo): void
    {
        $pdo->beginTransaction();

        try {

            // 1️⃣ 전체 조회 (삭제 포함)
            $stmt = $pdo->query("
                SELECT id, deleted_at, sort_no
                FROM system_coverimage_assets
            ");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 2️⃣ 임시 이동 (충돌 방지)
            $pdo->exec("
                UPDATE system_coverimage_assets
                SET sort_no = sort_no + 100000
            ");

            // 3️⃣ 정렬 (🔥 핵심: 활성 먼저, 휴지통 뒤)
            usort($rows, function ($a, $b) {

                $aDeleted = empty($a['deleted_at']) ? 0 : 1;
                $bDeleted = empty($b['deleted_at']) ? 0 : 1;

                if ($aDeleted !== $bDeleted) {
                    return $aDeleted <=> $bDeleted;
                }

                return (int)$a['sort_no'] <=> (int)$b['sort_no'];
            });

            // 4️⃣ 재부여
            $seq = 1;

            foreach ($rows as $row) {

                $stmt = $pdo->prepare("
                    UPDATE system_coverimage_assets
                    SET sort_no = :sort_no
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':sort_no' => $seq++,
                    ':id'   => $row['id']
                ]);
            }

            $pdo->commit();

        } catch (\Throwable $e) {

            $pdo->rollBack();
            throw $e;
        }
    }

    public static function normalizeClient(array $data): array
    {
        return [

            'id' => isset($data['id']) ? trim($data['id']) : null,

            'client_name' => $data['client_name'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'registration_date' => $data['registration_date'] ?? null,
            'client_grade' => $data['client_grade'] ?? null,

            'business_number' => isset($data['business_number'])
                ? preg_replace('/[^0-9]/', '', $data['business_number'])
                : null,

            'rrn' => isset($data['rrn'])
                ? preg_replace('/[^0-9]/', '', $data['rrn'])
                : null,

            'business_type' => $data['business_type'] ?? null,
            'business_category' => $data['business_category'] ?? null,
            'business_status' => $data['business_status'] ?? null,

            'ceo_name' => $data['ceo_name'] ?? null,
            'ceo_phone' => $data['ceo_phone'] ?? null,
            'manager_name' => $data['manager_name'] ?? null,
            'manager_phone' => $data['manager_phone'] ?? null,

            'phone' => $data['phone'] ?? null,
            'fax' => $data['fax'] ?? null,
            'email' => $data['email'] ?? null,

            'address' => $data['address'] ?? null,
            'address_detail' => $data['address_detail'] ?? null,
            'homepage' => $data['homepage'] ?? null,

            'client_category' => $data['client_category'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,

            'trade_category' => $data['trade_category'] ?? null,
            'client_type' => $data['client_type'] ?? null,
            'tax_type' => $data['tax_type'] ?? null,

            'payment_term' => $data['payment_term'] ?? null,
            'item_category' => $data['item_category'] ?? null,

            'note' => $data['note'] ?? null,
            'memo' => $data['memo'] ?? null,

            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ];
    }

}
