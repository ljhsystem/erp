<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Services\Concerns\LogsServiceOperations;
use Core\LoggerFactory;
use Psr\Log\LoggerInterface;

final class SystemLogService
{
    use LogsServiceOperations;
    private LoggerInterface $logger;

    public function __construct(private readonly string $logDirectory)
    {
        $this->logger = LoggerFactory::getLogger('service-system-log-management');
    }

    public function summary(): array
    {
        $files = [];$totalSize = 0;
        if (is_dir($this->logDirectory)) {
            foreach (scandir($this->logDirectory) ?: [] as $name) {
                if (!$this->isAllowedName($name)) continue;
                $path = $this->logDirectory . DIRECTORY_SEPARATOR . $name;
                if (!is_file($path)) continue;
                $size = (int) filesize($path);$totalSize += $size;
                $files[] = ['name'=>$name,'size'=>$size,'size_label'=>$this->formatSize($size),'modified_at'=>date('Y-m-d H:i:s',(int)filemtime($path))];
            }
        }
        usort($files,static fn(array $a,array $b):int=>strcmp($b['modified_at'],$a['modified_at']));
        return ['files'=>$files,'total_count'=>count($files),'total_size'=>$totalSize,'total_size_label'=>$this->formatSize($totalSize)];
    }

    public function view(string $name,int $maxBytes=51200):array
    {
        $path=$this->resolve($name);$size=(int)filesize($path);$handle=fopen($path,'rb');
        if($handle===false)throw new \RuntimeException('로그 파일을 열 수 없습니다.');
        try{if($size>$maxBytes)fseek($handle,-$maxBytes,SEEK_END);$content=(string)fread($handle,$maxBytes);}finally{fclose($handle);}
        return ['file'=>basename($path),'content'=>$this->userReadableContent($content),'partial'=>$size>$maxBytes,'technical_content_available'=>true];
    }

    public function delete(string $name,string $actor):void
    {
        $this->runLoggedOperation($this->logger,'시스템 로그','SYSTEM_LOG_DELETE','delete',['actor'=>$actor,'target_id'=>basename($name)],function()use($name):bool{$path=$this->resolve($name);if(!unlink($path))throw new \RuntimeException('로그 파일을 삭제하지 못했습니다.');return true;},'warning',false);
    }

    public function deleteAll(string $actor):int
    {
        return$this->runLoggedOperation($this->logger,'시스템 로그','SYSTEM_LOG_DELETE_ALL','delete-all',['actor'=>$actor],function():int{$count=0;foreach($this->summary()['files'] as$file){$path=$this->resolve((string)$file['name']);if(!unlink($path))throw new \RuntimeException('로그 파일을 일괄 삭제하지 못했습니다.');$count++;}return$count;},'warning',false);
    }

    public function downloadPath(string $name):string{return$this->resolve($name);}

    private function resolve(string $name):string
    {
        $input=trim($name);$name=basename($input);if($input!==$name||!$this->isAllowedName($name))throw new \InvalidArgumentException('로그 파일명을 확인해 주세요.');
        $path=$this->logDirectory.DIRECTORY_SEPARATOR.$name;if(!is_file($path))throw new \RuntimeException('로그 파일을 찾을 수 없습니다.');return$path;
    }
    private function isAllowedName(string $name):bool{return$name!==''&&preg_match('/^[a-zA-Z0-9._-]+\.log$/',$name)===1;}
    private function formatSize(int $bytes):string
    {
        if($bytes>=1073741824)return number_format($bytes/1073741824,2).' GB';if($bytes>=1048576)return number_format($bytes/1048576,2).' MB';if($bytes>=1024)return number_format($bytes/1024,1).' KB';return number_format($bytes).' B';
    }

    private function userReadableContent(string $content):string
    {
        $rows=[];$legacyReported=false;
        foreach(preg_split('/\R/u',$content)?:[] as$line){$line=trim($line);if($line==='')continue;$entry=json_decode($line,true);
            if(!is_array($entry)){if(!$legacyReported){$rows[]='[기존 형식 로그] 상세 기술정보는 다운로드 파일에서 확인해 주세요.';$legacyReported=true;}continue;}
            $context=is_array($entry['context']??null)?$entry['context']:[];$level=strtoupper((string)($entry['level_name']??$entry['level']??'INFO'));$levelName=match($level){'ERROR','CRITICAL','ALERT','EMERGENCY'=>'오류','WARNING'=>'주의','DEBUG'=>'진단',default=>'정보'};
            $time=(string)($entry['datetime']??$entry['timestamp']??'');$message=trim((string)($entry['message']??'로그가 기록되었습니다.'));$meta=[];
            foreach(['event_code'=>'사건','result'=>'결과','request_id'=>'요청','correlation_id'=>'추적']as$key=>$label){$value=trim((string)($context[$key]??''));if($value!=='')$meta[]=$label.': '.$value;}
            $rows[]=trim(($time!==''?'['.$time.'] ':'').'['.$levelName.'] '.$message.($meta!==[]?' ('.implode(', ',$meta).')':''));
        }
        return$rows!==[]?implode(PHP_EOL,$rows):'표시할 사용자용 로그가 없습니다.';
    }
}
