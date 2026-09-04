<?php

declare(strict_types=1);
namespace App\Services\Mail;


class ContactMail
{
    private Mailer $mailer;
    private string $fromName = '';
    private string $fromEmail = '';
    private string $subject = '';
    private string $message = '';

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function send(array $data): array
    {
        $this->fromName  = trim((string)($data['fromName'] ?? $data['from_name'] ?? ''));
        $this->fromEmail = trim((string)($data['fromEmail'] ?? $data['from_email'] ?? ''));
        $this->subject   = trim((string)($data['subject'] ?? ''));
        $this->message   = trim((string)($data['message'] ?? $data['messageBody'] ?? ''));

        $built = $this->build();

        $result = $this->mailer->sendToAdmin(
            $built['subject'],
            $built['html'],
            $built['text'],
            Mailer::SENDER_SUKHYANG_APP_ADMIN,
            $this->fromEmail,
            $this->fromName
        );

        return $result;
    }

    public function build(): array
    {
        $senderClean = preg_replace("/[\r\n]+/", ' ', strip_tags($this->fromName));
        $subjClean = preg_replace("/[\r\n]+/", ' ', strip_tags($this->subject));
        $subjClean = $subjClean !== '' ? $subjClean : '무제목';

        $subject = $senderClean !== '' ? '[문의] ' . $senderClean . ' : ' . $subjClean : '[문의] ' . $subjClean;

        $mailto = '';
        if (!empty($this->fromEmail) && filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL)) {
            $mailtoSubject = rawurlencode("RE: {$subjClean}");
            $mailtoBody = rawurlencode("안녕하세요 {$senderClean}님,\n\n(여기에 답변 내용을 입력하세요)\n\n---\n원문:\n" . $this->message);
            $mailto = 'mailto:' . $this->fromEmail . '?subject=' . $mailtoSubject . '&body=' . $mailtoBody;
        }

        $safeName  = htmlspecialchars($this->fromName, ENT_QUOTES);
        $safeEmail = htmlspecialchars($this->fromEmail, ENT_QUOTES);

        $emailAnchor = $mailto !== ''
            ? '<a href="' . htmlspecialchars($mailto, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer">' . $safeEmail . '</a>'
            : $safeEmail;

        $html =
            "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>" .
            "<h3>📩 새 문의가 도착했습니다</h3>" .
            "<p><b>보낸이:</b> " . $safeName . " &lt;" . $emailAnchor . "&gt;</p>" .
            "<p><b>제목:</b> " . htmlspecialchars($this->subject, ENT_QUOTES) . "</p>" .
            "<hr style='margin:15px 0;'>" .
            "<p><b>내용:</b></p>" .
            "<div style='white-space:pre-wrap;line-height:1.6;font-size:14px;background:#f8f9fa;"
            . " padding:15px;border-radius:8px;border:1px solid #e1e5ea;'>"
            . nl2br(htmlspecialchars($this->message, ENT_QUOTES)) .
            "</div>";

        $text =
            "새 문의가 도착했습니다.\n\n" .
            "보낸이: {$this->fromName} <{$this->fromEmail}>\n" .
            "제목: {$this->subject}\n\n" .
            "내용:\n{$this->message}";

        return [
            'subject'    => $subject,
            'html'       => $html,
            'text'       => $text,
            'fromName'   => $this->fromName,
            'fromEmail'  => $this->fromEmail
        ];
    }

    public static function dispatch(string $fromName, string $fromEmail, string $subject, string $message): array
    {
        $mailer = new Mailer();
        return (new self($mailer))->send([
            'fromName' => $fromName,
            'fromEmail' => $fromEmail,
            'subject' => $subject,
            'message' => $message
        ]);
    }

    private function recipientDomain(string $recipient): string
    {
        $position = strrpos($recipient, '@');
        return $position === false ? '' : strtolower(substr($recipient, $position + 1));
    }
}
