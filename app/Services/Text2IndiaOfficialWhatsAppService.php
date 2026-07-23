<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Text2IndiaOfficialWhatsAppService
{
    private const BASE_URL = 'https://app.text2indiaofficial.com/api/sendtemplate.php';

    private const LICENSE_NUMBER = '76404369648';

    private const API_KEY = 'K6wTQU4DeGcIubSg0BfEZWk8x';

    /** Query key for recipient WhatsApp number (per Text2India Official API). */
    private const CONTACT_PARAM = 'Contact';

    private const TEMPLATE_PARAM = 'Template';

    /**
     * Normalize to digits with India country code (91XXXXXXXXXX).
     */
    public function normalizeIndianMobile(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 10) {
            return '91' . $digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '91' . substr($digits, 1);
        }

        return strlen($digits) >= 12 ? $digits : null;
    }

    /**
     * Send an approved WhatsApp template that has a Document header.
     * The document URL must be publicly accessible (https).
     *
     * @param  string  $mobile       91XXXXXXXXXX
     * @param  string  $templateKey  approved template key with document header
     * @param  string  $documentUrl  public HTTPS URL of the PDF/document
     * @param  list<string>  $bodyParams  values for {{1}}, {{2}}, … body variables
     * @return array{ok: bool, status: int|null, body: string}
     */
    public function sendDocumentTemplate(string $mobile, string $templateKey, string $documentUrl, array $bodyParams = []): array
    {
        $paramSegment = implode(',', array_map(static fn ($v) => rawurlencode((string) $v), $bodyParams));

        $query = http_build_query([
            'LicenseNumber' => self::LICENSE_NUMBER,
            'APIKey'        => self::API_KEY,
            self::TEMPLATE_PARAM => $templateKey,
            self::CONTACT_PARAM  => $mobile,
            'MediaUrl'      => $documentUrl,
        ]);

        $url = rtrim(self::BASE_URL, '?&') . '?' . $query;
        if ($paramSegment !== '') {
            $url .= '&Param=' . $paramSegment;
        }

        $response = Http::timeout(45)->acceptJson()->get($url);

        $body = $response->body();
        if (!$response->successful()) {
            Log::warning('Text2India Official WhatsApp document HTTP error', [
                'status'      => $response->status(),
                'body'        => mb_substr($body, 0, 500),
                'mobile'      => $mobile,
                'template'    => $templateKey,
                'documentUrl' => $documentUrl,
            ]);
        }

        return [
            'ok'     => $response->successful(),
            'status' => $response->status(),
            'body'   => $body,
        ];
    }

    /**
     * Send one approved WhatsApp template message.
     *
     * @param  string  $mobile  91XXXXXXXXXX (sent as Contact=)
     * @param  string  $templateKey  e.g. registration_successful, holiday_close_open_info
     * @param  list<string>  $bodyParams  values for {{1}}, {{2}}, … in order (comma-separated Param=)
     * @return array{ok: bool, status: int|null, body: string}
     */
    public function sendTemplate(string $mobile, string $templateKey, array $bodyParams): array
    {
        $paramSegment = implode(',', array_map(static fn ($v) => rawurlencode((string) $v), $bodyParams));

        $query = http_build_query([
            'LicenseNumber' => self::LICENSE_NUMBER,
            'APIKey' => self::API_KEY,
            self::TEMPLATE_PARAM => $templateKey,
            self::CONTACT_PARAM => $mobile,
        ]);

        $url = rtrim(self::BASE_URL, '?&') . '?' . $query . '&Param=' . $paramSegment;

        $response = Http::timeout(45)->acceptJson()->get($url);

        $body = $response->body();
        if (!$response->successful()) {
            Log::warning('Text2India Official WhatsApp HTTP error', [
                'status' => $response->status(),
                'body' => mb_substr($body, 0, 500),
                'mobile' => $mobile,
                'template' => $templateKey,
            ]);
        }

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $body,
        ];
    }
}
