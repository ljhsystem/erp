<?php
// 경로: PROJECT_ROOT . '/app/services/mail/MailService.php'
namespace App\Services\Mail;

use App\Services\Mail\Mailer;
//use App\Services\Mail\MailToken;
use App\Services\Mail\AdminApprovalMail;
use App\Services\Mail\TwoFactorMail;
use App\Services\Mail\ContactMail;
use Core\LoggerFactory;

class MailService
{
    private Mailer $mailer;
    private $logger;

    public function __construct()
    {
        $this->mailer = new Mailer();
        $this->logger = LoggerFactory::getLogger('service-mail.MailService');    
    }

    public function sendAdminApprovalMail(array $data)
    {
        return (new AdminApprovalMail($this->mailer))->send($data);
    }

    public function sendTwoFactorMail(array $data)
    {
        return (new TwoFactorMail($this->mailer))->send($data);
    }

    public function sendContactMail(array $data)
    {
        return (new ContactMail($this->mailer))->send($data);
    }
}

