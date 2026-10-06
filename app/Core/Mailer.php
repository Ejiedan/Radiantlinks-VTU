<?php

declare(strict_types=1);

namespace App\Core;

final class Mailer
{
    public function send(string $to, string $subject, string $html, array $settings = []): bool
    {
        $driver = $settings['mail_driver'] ?? Env::get('MAIL_DRIVER', 'php');

        if ($driver === 'smtp') {
            return $this->sendSmtp($to, $subject, $html, $settings);
        }

        $from = $settings['mail_from_address'] ?? Env::get('MAIL_FROM_ADDRESS', 'no-reply@example.com');
        $name = $settings['mail_from_name'] ?? Env::get('MAIL_FROM_NAME', 'Radiantlinks VTU');
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $name . ' <' . $from . '>',
        ];

        return @mail($to, $subject, $html, implode("\r\n", $headers));
    }

    private function sendSmtp(string $to, string $subject, string $html, array $settings): bool
    {
        $host = (string) ($settings['smtp_host'] ?? Env::get('SMTP_HOST', ''));
        $port = (int) ($settings['smtp_port'] ?? Env::get('SMTP_PORT', 587));
        $username = (string) ($settings['smtp_username'] ?? Env::get('SMTP_USERNAME', ''));
        $password = (string) ($settings['smtp_password'] ?? Env::get('SMTP_PASSWORD', ''));
        $from = (string) ($settings['mail_from_address'] ?? Env::get('MAIL_FROM_ADDRESS', 'no-reply@example.com'));
        $fromName = (string) ($settings['mail_from_name'] ?? Env::get('MAIL_FROM_NAME', 'Radiantlinks VTU'));
        $encryption = (string) ($settings['smtp_encryption'] ?? Env::get('SMTP_ENCRYPTION', 'tls'));

        if ($host === '') {
            return false;
        }

        $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 20);
        if (!$socket) {
            error_log("SMTP connect failed: {$errno} {$errstr}");
            return false;
        }

        $read = fn(): string => (string) fgets($socket, 515);
        $write = function (string $line) use ($socket, $read): string {
            fwrite($socket, $line . "\r\n");
            return $read();
        };

        $read();
        $write('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));

        if ($encryption === 'tls') {
            $write('STARTTLS');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return false;
            }
            $write('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        }

        if ($username !== '') {
            $write('AUTH LOGIN');
            $write(base64_encode($username));
            $write(base64_encode($password));
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $subject,
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $html;

        $write('MAIL FROM:<' . $from . '>');
        $write('RCPT TO:<' . $to . '>');
        $write('DATA');
        fwrite($socket, $message . "\r\n.\r\n");
        $result = $read();
        $write('QUIT');
        fclose($socket);

        return str_starts_with($result, '250');
    }
}
