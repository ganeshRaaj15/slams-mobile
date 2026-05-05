<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Email extends BaseConfig
{
    public string $fromEmail  = 'no-reply@fkmp-smartlab.local';
    public string $fromName   = 'FKMP Smart Lab';
    public string $recipients = '';
    public string $userAgent = 'CodeIgniter';
    public string $protocol = 'mail';
    public string $mailPath = '/usr/sbin/sendmail';
    public string $SMTPHost = '';
    public string $SMTPUser = '';
    public string $SMTPPass = '';
    public int $SMTPPort = 25;
    public int $SMTPTimeout = 5;
    public bool $SMTPKeepAlive = false;
    public string $SMTPCrypto = 'tls';
    public bool $wordWrap = true;
    public int $wrapChars = 76;
    public string $mailType = 'html';
    public string $charset = 'UTF-8';
    public bool $validate = false;
    public int $priority = 3;
    public string $CRLF = "\r\n";
    public string $newline = "\r\n";
    public bool $BCCBatchMode = false;
    public int $BCCBatchSize = 200;
    public bool $DSN = false;

    public function __construct()
    {
        parent::__construct();

        $this->fromEmail = (string) env('email.fromEmail', $this->fromEmail);
        $this->fromName = (string) env('email.fromName', $this->fromName);
        $this->protocol = (string) env('email.protocol', $this->protocol);
        $this->SMTPHost = (string) env('email.SMTPHost', $this->SMTPHost);
        $this->SMTPUser = (string) env('email.SMTPUser', $this->SMTPUser);
        $this->SMTPPass = (string) env('email.SMTPPass', $this->SMTPPass);
        $this->SMTPPort = (int) env('email.SMTPPort', (string) $this->SMTPPort);
        $this->SMTPCrypto = (string) env('email.SMTPCrypto', $this->SMTPCrypto);
        $this->mailType = (string) env('email.mailType', $this->mailType);
    }
}
