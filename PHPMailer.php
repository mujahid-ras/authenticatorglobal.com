<?php
/**
 * PHPMailer SMTP Class for DRC Portal
 *
 * Provides SMTP email functionality using PHPMailer library
 * Falls back to native mail() if PHPMailer is not available
 */

if (!class_exists('PHPMailer')) {
    // Try to load PHPMailer from vendor if available
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    } elseif (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
        require_once __DIR__ . '/../../vendor/autoload.php';
    }
    // If still not available, we'll use native mail() as fallback
}

class PHPMailerHandler
{
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $smtp_encryption;
    private $smtp_auth;
    private $mailer;
    private $use_smtp;

    public function __construct()
    {
        // Load SMTP configuration from config.php
        $this->smtp_host = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
        $this->smtp_port = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $this->smtp_username = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $this->smtp_password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $this->smtp_encryption = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls';
        $this->smtp_auth = defined('SMTP_AUTH') ? SMTP_AUTH : true;
        $this->use_smtp = defined('USE_SMTP') ? USE_SMTP : true;

        // Initialize PHPMailer if available and SMTP is enabled
        if ($this->use_smtp && class_exists('PHPMailer')) {
            $this->mailer = new PHPMailer\PHPMailer\PHPMailer();
            $this->configureMailer();
        } else {
            $this->mailer = null;
        }
    }

    private function configureMailer()
    {
        if (!$this->mailer) {
            return;
        }

        $this->mailer->isSMTP();
        $this->mailer->Host = $this->smtp_host;
        $this->mailer->Port = $this->smtp_port;
        $this->mailer->SMTPAuth = $this->smtp_auth;
        $this->mailer->Username = $this->smtp_username;
        $this->mailer->Password = $this->smtp_password;

        if ($this->smtp_encryption === 'ssl') {
            $this->mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($this->smtp_encryption === 'tls') {
            $this->mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $this->mailer->SMTPSecure = '';
        }

        // Error handling
        $this->mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ];

        // Set email timeouts
        $this->mailer->Timeout = 30;
        $this->mailer->Helo = $this->smtp_host;

        // Set return path
        $this->mailer->ReturnPath = defined('MAIL_FROM') ? MAIL_FROM : '';

        // Set content type
        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';
    }

    public function setFrom($email, $name = '')
    {
        if ($this->mailer) {
            $this->mailer->setFrom($email, $name);
        }
    }

    public function addTo($email, $name = '')
    {
        if ($this->mailer) {
            $this->mailer->addAddress($email, $name);
        }
    }

    public function setSubject($subject)
    {
        if ($this->mailer) {
            $this->mailer->Subject = $subject;
        }
    }

    public function setBody($body)
    {
        if ($this->mailer) {
            $this->mailer->Body = $body;
        }
    }

    public function setAltBody($altBody)
    {
        if ($this->mailer) {
            $this->mailer->AltBody = $altBody;
        }
    }

    public function sendEmail()
    {
        try {
            if ($this->mailer) {
                $result = $this->mailer->send();
                if ($result) {
                    // Clear addresses for next use
                    $this->mailer->clearAddresses();
                    return true;
                } else {
                    error_log('PHPMailer error: ' . $this->mailer->ErrorInfo);
                    return false;
                }
            } else {
                // PHPMailer not available, fallback to native mail()
                return $this->fallbackSendMail();
            }
        } catch (Exception $e) {
            error_log('PHPMailer exception: ' . $e->getMessage());
            return false;
        }
    }

    private function fallbackSendMail()
    {
        // Fallback to native mail() function
        $to = '';
        $subject = '';
        $body = '';

        if ($this->mailer) {
            $to = implode(', ', $this->mailer->To);
            $subject = $this->mailer->Subject;
            $body = $this->mailer->Body;

            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . ($this->mailer->FromName ? $this->mailer->FromName . ' <' . $this->mailer->From . '>' : $this->mailer->From)
            ];

            if (defined('MAIL_FROM')) {
                $headers[] = 'Reply-To: ' . MAIL_FROM;
            }

            // Ensure MAIL_FROM is set for the -f parameter
            $mailFrom = defined('MAIL_FROM') ? MAIL_FROM : '';

            $result = mail($to, $subject, $body, implode("\r\n", $headers), '-f' . $mailFrom);

            if ($result) {
                // Clear addresses for next use
                if (isset($this->mailer->To)) {
                    $this->mailer->To = [];
                }
            }

            return $result;
        }

        return false;
    }

    public function __destruct()
    {
        // Cleanup
        if ($this->mailer) {
            $this->mailer->SmtpClose();
        }
    }
}

/**
 * Convenience function to send email using PHPMailer or native mail()
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $title For logging purposes
 * @return bool true on success, false on failure
 */
function send_mail_smtp($to, $subject, $body, $title = 'Email'): bool
{
    $email_handler = new PHPMailerHandler();

    // Set from address from config
    $from_email = defined('MAIL_FROM') ? MAIL_FROM : '';
    $from_name = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : '';

    if ($from_email) {
        $email_handler->setFrom($from_email, $from_name);
    }

    $email_handler->addTo($to);
    $email_handler->setSubject($subject);
    $email_handler->setBody($body);

    return $email_handler->sendEmail();
}
?>