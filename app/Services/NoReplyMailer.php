<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class NoReplyMailer
{
    public function sendWithZip(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $zipPath = null,
        ?string $zipFileName = null
    ): bool {
        $mail = new PHPMailer(true);

        try {
            // $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;

            // Workspace user + app password
            $mail->Username   =  'no-reply@mazingbusiness.com';
            $mail->Password   =  'bpxayqdfktlrhmkk';

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('no-reply@mazingbusiness.com', 'Mazing Business');
            $mail->addAddress($toEmail, $toName);

             $mail->SMTPOptions = array (
            'ssl' => array(
                'verify_peer'  => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true));

            // ZIP attach
            if (!empty($zipPath) && file_exists($zipPath)) {
                $displayName = $zipFileName ?: basename($zipPath);
                $mail->addAttachment($zipPath, $displayName);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            return $mail->send();
        } catch (Exception $e) {
            \Log::error('PHPMailer CI/PL ZIP Mail error: ' . $e->getMessage());
            return false;
        }
    }
}
