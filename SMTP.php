<?php
/**
 * SMTP Protocol Handler for DRC Portal
 *
 * Handles SMTP email sending using PHPMailer or native mail()
 * Used as a protocol layer between PHPMailer class and send_mail function
 */

class SMTPHandler
{
    private $mailer;
    private $use_smtp = true;

    public function __construct()
    {
        // Check if SMTP is enabled in config
        $this->use_smtp = defined('USE_SMTP') ? USE_SMTP : true;

        // Try to initialize PHPMailer if available and SMTP is enabled
        if ($this->use_smtp && class_exists('PHPMailer')) {
            require_once __DIR__ . '/PHPMailer.php';
            $this->mailer = new PHPMailerHandler();
        } else {
            $this->mailer = null;
        }
    }

    public function send($to, $subject, $body, $title = 'Email', $headers = [])
    {
        try {
            if ($this->mailer) {
                // Use PHPMailer
                $email_handler = $this->mailer;

                // Set from address
                $from_email = defined('MAIL_FROM') ? MAIL_FROM : '';
                $from_name = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : '';

                if ($from_email) {
                    $email_handler->setFrom($from_email, $from_name);
                }

                $email_handler->addTo($to);
                $email_handler->setSubject($subject);
                $email_handler->setBody($body);

                return $email_handler->sendEmail();
            } else {
                // Fallback to native mail()
                return $this->fallbackSend($to, $subject, $body, $headers);
            }
        } catch (Exception $e) {
            error_log('SMTPHandler error: ' . $e->getMessage());
            return false;
        }
    }

    private function fallbackSend($to, $subject, $body, $headers)
    {
        // Native mail() implementation
        $headers_str = '';
        if (is_array($headers)) {
            foreach ($headers as $header) {
                $headers_str .= $header . "\r\n";
            }
        } else {
            $headers_str = $headers;
        }

        // Ensure From header is set
        if (!preg_match('/^From:/im', $headers_str)) {
            $from_email = defined('MAIL_FROM') ? MAIL_FROM : '';
            $from_name = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : '';

            if ($from_email) {
                $headers_str .= "From: " . ($from_name ? $from_name . ' <' . $from_email . '>' : $from_email) . "\r\n";
            }
        }

        // Prepare message
        $message = $body;

        // Send with -f parameter if MAIL_FROM is set
        $mail_from = defined('MAIL_FROM') ? MAIL_FROM : '';
        if ($mail_from) {
            $result = mail($to, $subject, $message, $headers_str, '-f' . $mail_from);
        } else {
            $result = mail($to, $subject, $message, $headers_str);
        }

        return $result;
    }

    public function __destruct()
    {
        // Cleanup
        if ($this->mailer) {
            // PHPMailer cleanup handled by its destructor
        }
    }
}

/**
 * Convenience function to create SMTP handler instance
 *
 * @return SMTPHandler
 */
function get_smtp_handler()
{
    return new SMTPHandler();
}
?>