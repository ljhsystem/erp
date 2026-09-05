<?php

namespace App\Models\Ledger;

use PDO;

class VehicleLogModel
{
    public function __construct(private readonly PDO $db) {}

    public function list(array $filters = [], bool $trash = false): array
    {
        $sql = "SELECT trip.*,vehicle.vehicle_no,vehicle.vehicle_name,vehicle.ownership_type_code,
                       CASE WHEN vehicle.ownership_type_code='CORPORATE' THEN 1 ELSE 0 END is_tax_reportable,
                       team.team_name
                  FROM ledger_vehicle_trip_logs trip
                  JOIN ledger_vehicles vehicle ON vehicle.id=trip.vehicle_id
             LEFT JOIN system_work_teams team ON team.id=trip.work_team_id
                 WHERE " . ($trash ? 'trip.deleted_at IS NOT NULL' : 'trip.deleted_at IS NULL');
        $params = [];
        foreach (['company_id','vehicle_id','employee_id','work_team_id','purpose_type_code','source_type_code'] as $key) {
            if (trim((string) ($filters[$key] ?? '')) === '') continue;
            $sql .= " AND trip.{$key}=:{$key}";
            $params[":{$key}"] = $filters[$key];
        }
        if (trim((string) ($filters['ownership_type_code'] ?? '')) !== '') {
            $sql .= ' AND vehicle.ownership_type_code=:ownership_type_code';
            $params[':ownership_type_code'] = $filters['ownership_type_code'];
        }
        if (trim((string) ($filters['date_from'] ?? '')) !== '') {$sql .= ' AND trip.trip_date>=:date_from';$params[':date_from']=$filters['date_from'];}
        if (trim((string) ($filters['date_to'] ?? '')) !== '') {$sql .= ' AND trip.trip_date<=:date_to';$params[':date_to']=$filters['date_to'];}
        if (trim((string) ($filters['keyword'] ?? '')) !== '') {
            $sql .= ' AND (vehicle.vehicle_no LIKE :keyword OR vehicle.vehicle_name LIKE :keyword OR trip.driver_name_snapshot LIKE :keyword OR trip.purpose LIKE :keyword OR trip.origin_location LIKE :keyword OR trip.destination_location LIKE :keyword)';
            $params[':keyword'] = '%' . trim((string) $filters['keyword']) . '%';
        }
        $sql .= ' ORDER BY trip.trip_date DESC,COALESCE(trip.started_at,CONCAT(trip.trip_date,\' 00:00:00\')) DESC,trip.created_at DESC';
        return $this->all($sql, $params);
    }

    public function summary(array $filters = []): array
    {
        $rows = $this->list($filters);
        $summary=['trip_count'=>count($rows),'driving_day_count'=>0,'distance_km'=>0.0,'business_distance_km'=>0.0,'private_distance_km'=>0.0,'unclassified_count'=>0,'tax_reportable_count'=>0];
        $days=[];
        foreach($rows as $row){$days[$row['trip_date']]=true;$summary['distance_km']+=(float)$row['distance_km'];$summary['business_distance_km']+=(float)$row['business_distance_km'];$summary['private_distance_km']+=(float)$row['distance_km']-(float)$row['business_distance_km'];if($row['purpose_type_code']==='UNCLASSIFIED')$summary['unclassified_count']++;if((int)$row['is_tax_reportable']===1)$summary['tax_reportable_count']++;}
        $summary['driving_day_count']=count($days);$summary['business_use_rate']=$summary['distance_km']>0?round($summary['business_distance_km']/$summary['distance_km']*100,2):0;
        return $summary;
    }

    public function find(string $id, bool $lock = false): ?array
    { return $this->one('SELECT * FROM ledger_vehicle_trip_logs WHERE id=:id'.($lock?' FOR UPDATE':''),[':id'=>$id]); }
    public function vehicle(string $id, bool $lock = false): ?array
    { return $this->one('SELECT * FROM ledger_vehicles WHERE id=:id AND deleted_at IS NULL'.($lock?' FOR UPDATE':''),[':id'=>$id]); }
    public function vehicleAsset(string $id):?array
    { return $this->one("SELECT id,company_id,asset_category_code,asset_status_code FROM ledger_assets WHERE id=:id AND deleted_at IS NULL",[':id'=>$id]); }
    public function vehicleByNo(string $companyId,string $vehicleNo):?array
    { return $this->one('SELECT * FROM ledger_vehicles WHERE company_id=:company_id AND vehicle_no=:vehicle_no AND deleted_at IS NULL',[':company_id'=>$companyId,':vehicle_no'=>$vehicleNo]); }
    public function duplicateSource(string $source,string $sourceId,?string $exclude=null):bool
    {if($sourceId==='')return false;$sql='SELECT COUNT(*) FROM ledger_vehicle_trip_logs WHERE source_type_code=:source AND source_record_id=:source_id';$p=[':source'=>$source,':source_id'=>$sourceId];if($exclude){$sql.=' AND id<>:exclude';$p[':exclude']=$exclude;}return(int)$this->scalar($sql,$p)>0;}

    public function insertTrip(array $row):void{$this->execute('INSERT INTO ledger_vehicle_trip_logs (id,company_id,vehicle_id,employee_id,driver_name_snapshot,department_name_snapshot,position_name_snapshot,work_team_id,trip_date,started_at,ended_at,purpose_type_code,purpose,origin_location,destination_location,start_odometer_km,end_odometer_km,distance_km,business_distance_km,source_type_code,source_record_id,capture_status_code,note,created_at,created_by,updated_at,updated_by) VALUES (:id,:company_id,:vehicle_id,:employee_id,:driver_name_snapshot,:department_name_snapshot,:position_name_snapshot,:work_team_id,:trip_date,:started_at,:ended_at,:purpose_type_code,:purpose,:origin_location,:destination_location,:start_odometer_km,:end_odometer_km,:distance_km,:business_distance_km,:source_type_code,:source_record_id,:capture_status_code,:note,:created_at,:created_by,:updated_at,:updated_by)',$row);}
    public function updateTrip(string$id,array$row):void{$row[':id']=$id;$this->execute('UPDATE ledger_vehicle_trip_logs SET company_id=:company_id,vehicle_id=:vehicle_id,employee_id=:employee_id,driver_name_snapshot=:driver_name_snapshot,department_name_snapshot=:department_name_snapshot,position_name_snapshot=:position_name_snapshot,work_team_id=:work_team_id,trip_date=:trip_date,started_at=:started_at,ended_at=:ended_at,purpose_type_code=:purpose_type_code,purpose=:purpose,origin_location=:origin_location,destination_location=:destination_location,start_odometer_km=:start_odometer_km,end_odometer_km=:end_odometer_km,distance_km=:distance_km,business_distance_km=:business_distance_km,source_type_code=:source_type_code,source_record_id=:source_record_id,capture_status_code=:capture_status_code,note=:note,updated_at=:updated_at,updated_by=:updated_by WHERE id=:id AND deleted_at IS NULL',$row);}
    public function softDelete(string$id,string$actor):void{$this->execute('UPDATE ledger_vehicle_trip_logs SET deleted_at=NOW(),deleted_by=:deleted_by,updated_at=NOW(),updated_by=:updated_by WHERE id=:id AND deleted_at IS NULL',[':id'=>$id,':deleted_by'=>$actor,':updated_by'=>$actor]);}
    public function restore(string$id,string$actor):void{$this->execute('UPDATE ledger_vehicle_trip_logs SET deleted_at=NULL,deleted_by=NULL,restored_at=NOW(),restored_by=:restored_by,updated_at=NOW(),updated_by=:updated_by WHERE id=:id AND deleted_at IS NOT NULL',[':id'=>$id,':restored_by'=>$actor,':updated_by'=>$actor]);}

    public function insertVehicle(array$row):void{$this->execute('INSERT INTO ledger_vehicles (id,company_id,asset_id,vehicle_no,vehicle_name,ownership_type_code,default_employee_id,home_location,work_location,commute_distance_km,is_active,note,created_at,created_by,updated_at,updated_by) VALUES (:id,:company_id,:asset_id,:vehicle_no,:vehicle_name,:ownership_type_code,:default_employee_id,:home_location,:work_location,:commute_distance_km,:is_active,:note,:created_at,:created_by,:updated_at,:updated_by)',$row);}
    public function updateVehicle(string$id,array$row):void{$row[':id']=$id;$this->execute('UPDATE ledger_vehicles SET company_id=:company_id,asset_id=:asset_id,vehicle_no=:vehicle_no,vehicle_name=:vehicle_name,ownership_type_code=:ownership_type_code,default_employee_id=:default_employee_id,home_location=:home_location,work_location=:work_location,commute_distance_km=:commute_distance_km,is_active=:is_active,note=:note,updated_at=:updated_at,updated_by=:updated_by WHERE id=:id AND deleted_at IS NULL',$row);}
    public function vehicleNoExists(string$company,string$no,?string$exclude=null):bool{$sql='SELECT COUNT(*) FROM ledger_vehicles WHERE company_id=:company AND vehicle_no=:no AND deleted_at IS NULL';$p=[':company'=>$company,':no'=>$no];if($exclude){$sql.=' AND id<>:exclude';$p[':exclude']=$exclude;}return(int)$this->scalar($sql,$p)>0;}
    public function vehicleOptions():array{return$this->all("SELECT vehicle.*,company.company_name_ko company_name,employee.employee_name default_employee_name,asset.asset_name FROM ledger_vehicles vehicle JOIN system_company company ON company.id=vehicle.company_id LEFT JOIN user_employees employee ON employee.id=vehicle.default_employee_id LEFT JOIN ledger_assets asset ON asset.id=vehicle.asset_id WHERE vehicle.deleted_at IS NULL ORDER BY vehicle.is_active DESC,vehicle.vehicle_no");}
    public function companies():array{return$this->all('SELECT id,company_name_ko name FROM system_company ORDER BY company_name_ko');}
    public function employees():array{return$this->all("SELECT employee.id,employee.employee_name name,department.dept_name department_name,position.position_name FROM user_employees employee LEFT JOIN user_departments department ON department.id=employee.department_id LEFT JOIN user_positions position ON position.id=employee.position_id WHERE employee.employment_status<>'RETIRED' ORDER BY employee.sort_no,employee.employee_name");}
    public function teams():array{return$this->all('SELECT id,team_name name FROM system_work_teams WHERE deleted_at IS NULL AND is_active=1 ORDER BY sort_no,team_name');}
    public function vehicleAssets():array{return$this->all("SELECT id,company_id,CONCAT(asset_no,' ',asset_name) name FROM ledger_assets WHERE deleted_at IS NULL AND asset_category_code='VEHICLE' AND asset_status_code='ACTIVE' ORDER BY asset_no");}
    private function all(string$sql,array$params=[]):array{$s=$this->db->prepare($sql);$s->execute($params);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
    private function one(string$sql,array$params=[]):?array{$s=$this->db->prepare($sql);$s->execute($params);return$s->fetch(PDO::FETCH_ASSOC)?:null;}
    private function scalar(string$sql,array$params=[]):mixed{$s=$this->db->prepare($sql);$s->execute($params);return$s->fetchColumn();}
    private function execute(string$sql,array$params):void{$s=$this->db->prepare($sql);$s->execute($params);}
}
