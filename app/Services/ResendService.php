<?php

namespace App\Services;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Resend\Client as ResendClient;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;

class ResendService
{
    protected string $apiKey;
    protected string $fromName;
    protected string $fromEmail;
    protected ?string $caCertPath;

    public function __construct()
    {
        $this->apiKey = (string) config('services.resend.key', '');
        $this->fromName = (string) config('services.resend.name', 'mrp-electrodesk-dev');
        $this->fromEmail = (string) config('services.resend.from_email', 'onboarding@resend.dev');

        $certPath = storage_path('certs/cacert.pem');
        $this->caCertPath = file_exists($certPath) ? $certPath : null;
    }

    /**
     * Get a configured instance of Resend\Client SDK.
     */
    public function client(): ResendClient
    {
        $options = [];
        if ($this->caCertPath) {
            $options['verify'] = $this->caCertPath;
        }

        $guzzle = new GuzzleClient($options);
        $transporter = new HttpTransporter(
            $guzzle,
            BaseUri::from('api.resend.com'),
            Headers::withAuthorization(ApiKey::from($this->apiKey))
        );

        return new ResendClient($transporter);
    }

    /**
     * Send an OTP email for password reset.
     *
     * @param string $toEmail
     * @param string $otp
     * @param string $userName
     * @return array{success: bool, id: ?string, error: ?string}
     */
    public function sendPasswordResetOtp(string $toEmail, string $otp, string $userName = 'User'): array
    {
        $from = "{$this->fromName} <{$this->fromEmail}>";
        $subject = 'Your Mr. Pride Password Reset OTP Code';
        $html = $this->buildOtpEmailHtml($userName, $otp);

        try {
            // First attempt with Resend SDK Client
            $resend = $this->client();
            $result = $resend->emails->send([
                'from' => $from,
                'to' => [$toEmail],
                'subject' => $subject,
                'html' => $html,
            ]);

            $id = $result->id ?? null;

            Log::info("Resend OTP email sent successfully to {$toEmail}", ['resend_id' => $id]);

            return [
                'success' => true,
                'id' => $id,
                'error' => null,
            ];
        } catch (\Throwable $sdkError) {
            Log::warning("Resend SDK call failed, attempting fallback HTTP request: " . $sdkError->getMessage());

            try {
                // Fallback to Laravel HTTP client
                $http = Http::asJson()->withToken($this->apiKey);
                if ($this->caCertPath) {
                    $http = $http->withOptions(['verify' => $this->caCertPath]);
                } else {
                    $http = $http->withoutVerifying();
                }

                $response = $http->post('https://api.resend.com/emails', [
                    'from' => $from,
                    'to' => [$toEmail],
                    'subject' => $subject,
                    'html' => $html,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $id = $data['id'] ?? null;
                    Log::info("Resend OTP email sent via HTTP fallback to {$toEmail}", ['resend_id' => $id]);

                    return [
                        'success' => true,
                        'id' => $id,
                        'error' => null,
                    ];
                }

                $errorBody = $response->body();
                Log::warning("Resend HTTP request failed ({$response->status()}): {$errorBody}");

                // Attempt Laravel native Mail fallback if configured (e.g. SMTP)
                try {
                    $defaultMailer = config('mail.default', 'log');
                    if ($defaultMailer && $defaultMailer !== 'log') {
                        Mail::html($html, function ($message) use ($toEmail, $subject) {
                            $message->to($toEmail)->subject($subject);
                        });

                        Log::info("OTP email successfully dispatched via Laravel Mail ({$defaultMailer}) to {$toEmail}");

                        return [
                            'success' => true,
                            'id' => 'mail-' . uniqid(),
                            'error' => null,
                        ];
                    }
                } catch (\Throwable $mailError) {
                    Log::error("Laravel Mail fallback failed: " . $mailError->getMessage());
                }

                return [
                    'success' => false,
                    'id' => null,
                    'error' => "Resend API error ({$response->status()}): {$errorBody}",
                ];
            } catch (\Throwable $httpError) {
                Log::error("Resend fallback also failed: " . $httpError->getMessage());

                return [
                    'success' => false,
                    'id' => null,
                    'error' => $httpError->getMessage(),
                ];
            }
        }
    }

    /**
     * Generate responsive HTML template for OTP email.
     */
    protected function buildOtpEmailHtml(string $userName, string $otp): string
    {
        $safeName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Password Reset OTP</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f5f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1f2937;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f5f7; padding: 40px 15px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" style="max-width: 520px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #e5e7eb;" cellspacing="0" cellpadding="0">
          <!-- Header -->
          <tr>
            <td style="background-color: #4b5320; padding: 28px 32px; text-align: center;">
              <h1 style="margin: 0; font-size: 22px; font-weight: 700; color: #ffffff; letter-spacing: 0.5px;">MR. PRIDE</h1>
              <p style="margin: 4px 0 0; font-size: 13px; color: #d1d5db; font-weight: 500;">ElectroDesk System</p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding: 36px 32px 24px;">
              <h2 style="margin: 0 0 16px; font-size: 18px; font-weight: 600; color: #111827;">Password Reset Verification</h2>
              <p style="margin: 0 0 20px; font-size: 14px; line-height: 22px; color: #4b5563;">
                Hello <strong>{$safeName}</strong>,
              </p>
              <p style="margin: 0 0 24px; font-size: 14px; line-height: 22px; color: #4b5563;">
                We received a request to reset your password for your Mr. Pride account. Use the One-Time Password (OTP) below to verify your identity and set a new password.
              </p>

              <!-- OTP Box -->
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin: 28px 0; background-color: #f9fafb; border: 2px dashed #4b5320; border-radius: 8px;">
                <tr>
                  <td align="center" style="padding: 24px;">
                    <div style="font-size: 12px; text-transform: uppercase; font-weight: 600; color: #6b7280; letter-spacing: 1.5px; margin-bottom: 8px;">Your Verification Code</div>
                    <div style="font-size: 34px; font-weight: 800; letter-spacing: 8px; color: #4b5320; font-family: monospace;">{$safeOtp}</div>
                    <div style="font-size: 12px; color: #9ca3af; margin-top: 8px;">Valid for 15 minutes</div>
                  </td>
                </tr>
              </table>

              <p style="margin: 0 0 16px; font-size: 13px; line-height: 20px; color: #6b7280;">
                Enter this code on the password reset screen along with your new password.
              </p>
              <p style="margin: 0; font-size: 13px; line-height: 20px; color: #9ca3af;">
                If you did not request this password reset, please ignore this email or contact your administrator immediately.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color: #f9fafb; padding: 20px 32px; border-top: 1px solid #f3f4f6; text-align: center;">
              <p style="margin: 0; font-size: 11px; color: #9ca3af;">
                &copy; 2026 Mr. Pride ElectroDesk. All rights reserved.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * Send an inquiry reply to a customer.
     *
     * @param string $toEmail
     * @param string $customerName
     * @param string $subject
     * @param string $replyContent
     * @param string $originalMessage
     * @return array{success: bool, id: ?string, error: ?string}
     */
    public function sendInquiryReply(
        string $toEmail,
        string $customerName,
        string $subject,
        string $replyContent,
        string $originalMessage = ''
    ): array {
        $from = "{$this->fromName} <{$this->fromEmail}>";
        $emailSubject = 'Re: ' . ($subject ?: 'Your inquiry with MR-Pride Electrical Supplies');
        $html = $this->buildInquiryReplyEmailHtml($customerName, $emailSubject, $replyContent, $originalMessage);

        try {
            if (! empty($this->apiKey)) {
                $resend = $this->client();
                $result = $resend->emails->send([
                    'from' => $from,
                    'to' => [$toEmail],
                    'subject' => $emailSubject,
                    'html' => $html,
                ]);

                $id = $result->id ?? null;
                Log::info("Inquiry reply email sent via Resend to {$toEmail}", ['resend_id' => $id]);

                return ['success' => true, 'id' => $id, 'error' => null];
            }
        } catch (\Throwable $sdkError) {
            Log::warning("Resend SDK reply send failed: " . $sdkError->getMessage());
        }

        // Try Laravel Mail fallback
        try {
            Mail::html($html, function ($msg) use ($toEmail, $emailSubject) {
                $msg->to($toEmail)->subject($emailSubject);
            });
            return ['success' => true, 'id' => 'mail-sent', 'error' => null];
        } catch (\Throwable $mailError) {
            Log::info("Mail delivery not available in local environment: " . $mailError->getMessage());
            return ['success' => false, 'id' => null, 'error' => $mailError->getMessage()];
        }
    }

    /**
     * Build customer reply email template.
     */
    protected function buildInquiryReplyEmailHtml(string $customerName, string $subject, string $replyContent, string $originalMessage): string
    {
        $safeName = htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeReply = nl2br(htmlspecialchars($replyContent, ENT_QUOTES, 'UTF-8'));
        $safeOriginal = nl2br(htmlspecialchars($originalMessage, ENT_QUOTES, 'UTF-8'));

        $originalSection = $originalMessage ? <<<HTML
          <div style="margin-top: 24px; padding: 16px; background-color: #f9fafb; border-left: 3px solid #4b5320; border-radius: 4px;">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #6b7280; letter-spacing: 1px; margin-bottom: 6px;">Your Original Message:</div>
            <div style="font-size: 13px; line-height: 20px; color: #4b5563;">{$safeOriginal}</div>
          </div>
HTML : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{$safeSubject}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f5f7; color: #1f2937;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f5f7; padding: 40px 15px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" style="max-width: 580px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #e5e7eb;" cellspacing="0" cellpadding="0">
          <tr>
            <td style="background-color: #4b5320; padding: 24px 32px; text-align: left;">
              <h1 style="margin: 0; font-size: 20px; font-weight: 800; color: #F8B803; letter-spacing: 0.5px;">MR-PRIDE</h1>
              <p style="margin: 4px 0 0; font-size: 12px; color: rgba(255,255,255,0.85);">Customer Support & Inquiries</p>
            </td>
          </tr>
          <tr>
            <td style="padding: 32px;">
              <p style="margin: 0 0 16px; font-size: 15px; color: #111827;">Hello <strong>{$safeName}</strong>,</p>
              <p style="margin: 0 0 20px; font-size: 14px; line-height: 24px; color: #374151;">
                Thank you for contacting MR-Pride Electrical Supplies. Here is our response to your inquiry:
              </p>
              <div style="margin: 20px 0; padding: 20px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; line-height: 24px; color: #1e293b;">
                {$safeReply}
              </div>
              {$originalSection}
              <p style="margin: 24px 0 0; font-size: 13px; line-height: 20px; color: #6b7280;">
                If you have further questions, feel free to reply directly to this email or call us at <strong>09397366907</strong> / <strong>022444939</strong>.
              </p>
            </td>
          </tr>
          <tr>
            <td style="background-color: #f9fafb; padding: 20px 32px; border-top: 1px solid #f3f4f6; text-align: center;">
              <p style="margin: 0; font-size: 11px; color: #9ca3af;">&copy; 2026 MR-Pride Electrical Supplies. All rights reserved.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }
}

