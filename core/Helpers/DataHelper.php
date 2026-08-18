<?php
// 경로: PROJECT_ROOT . '/core/Helpers/DataHelper.php'
namespace Core\Helpers;

use App\Models\System\CoverModel;

class DataHelper
{


    public static function resequenceCoverImageCodes($pdo): void
    {
        (new CoverModel($pdo))->resequenceCodes();
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
            'default_account_id' => $data['default_account_id'] ?? null,
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
