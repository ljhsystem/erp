<?php
namespace App\Services\Mail;

use App\Services\Concerns\LogsServiceOperations;
use App\Services\Mail\Mailer;
//use App\Services\Mail\MailToken;
use App\Services\Mail\AdminApprovalMail;
use App\Services\Mail\TwoFactorMail;
use App\Services\Mail\ContactMail;
use Core\LoggerFactory;

class MailService
{
    use LogsServiceOperations;
    private Mailer $mailer;
    private $logger;

    public function __construct(?Mailer $mailer = null)
    {
        $this->mailer = $mailer ?? new Mailer();
        $this->logger = LoggerFactory::getLogger('service-mail.MailService');
    }

    public function sendAdminApprovalMail(array $data): array
    {
        return $this->sendLogged('ADMIN_APPROVAL_MAIL_SEND','admin-approval',fn():array=>(new AdminApprovalMail($this->mailer))->send($data));
    }

    public function sendTwoFactorMail(array $data): array
    {
        return $this->sendLogged('TWO_FACTOR_MAIL_SEND','two-factor',fn():array=>(new TwoFactorMail($this->mailer))->send($data));
    }

    public function sendContactMail(array $data): array
    {
        return $this->sendLogged('CONTACT_MAIL_SEND','contact',fn():array=>(new ContactMail($this->mailer))->send($data));
    }

    private function sendLogged(string $event,string $action,callable $operation):array
    {
        return $this->runLoggedOperation($this->logger,'메일',$event,$action,[],$operation,'info',true,static function(array$result):string{if(!empty($result['success']))return'SUCCESS';return!empty($result['retryable'])?'FAILED':'BLOCKED';});
    }
}
