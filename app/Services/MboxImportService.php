<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\Email;
use App\Models\EmailAttachment;
use Illuminate\Support\Facades\Storage;

class MboxImportService
{
    /**
     * استيراد رسائل البريد من ملف mbox
     * @param string $mboxPath
     * @param int $emailAccountId
     * @return int عدد الرسائل المستوردة
     */
    public function importFromMbox(string $mboxPath, int $emailAccountId): int
    {
        $count = 0;
        if (!file_exists($mboxPath)) {
            throw new \Exception('Mbox file not found: ' . $mboxPath);
        }
        $handle = fopen($mboxPath, 'r');
        if (!$handle) {
            throw new \Exception('Unable to open mbox file.');
        }
        $message = '';
        while (($line = fgets($handle)) !== false) {
            if (substr($line, 0, 5) === 'From ') {
                if ($message) {
                    $this->parseAndStoreEmail($message, $emailAccountId);
                    $count++;
                }
                $message = $line;
            } else {
                $message .= $line;
            }
        }
        if ($message) {
            $this->parseAndStoreEmail($message, $emailAccountId);
            $count++;
        }
        fclose($handle);
        return $count;
    }

    /**
     * تحليل وتخزين رسالة بريد واحدة
     */
    private function parseAndStoreEmail(string $rawMessage, int $emailAccountId): void
    {
        // استخدم مكتبة خارجية لتحليل mbox بشكل أفضل في الإنتاج
        $headers = [];
        $body = '';
        $lines = preg_split('/\r?\n/', $rawMessage);
        $isHeader = true;
        foreach ($lines as $line) {
            if ($isHeader && trim($line) === '') {
                $isHeader = false;
                continue;
            }
            if ($isHeader) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[trim($parts[0])] = trim($parts[1]);
                }
            } else {
                $body .= $line . "\n";
            }
        }

        // دعم multipart: استخراج الجزء الأنسب (يفضل HTML)
        $decodedBody = $body;
        $contentType = $headers['Content-Type'] ?? '';
        if (stripos($contentType, 'multipart/') !== false) {
            // استخراج boundary
            if (preg_match('/boundary="?([^";]+)"?/i', $contentType, $m)) {
                $boundary = trim($m[1]);
                $parts = preg_split('/--' . preg_quote($boundary, '/') . '/', $body);
                $bestPart = '';
                $bestType = '';
                foreach ($parts as $part) {
                    if (stripos($part, 'Content-Type: text/html') !== false) {
                        $bestPart = $part;
                        $bestType = 'html';
                        break;
                    } elseif (stripos($part, 'Content-Type: text/plain') !== false && $bestType !== 'html') {
                        $bestPart = $part;
                        $bestType = 'plain';
                    }
                }
                if ($bestPart) {
                    // استخراج body من الجزء المناسب مع تجاهل رؤوس الجزء الفرعي
                    // فصل headers عن body بدقة
                    $headerBodySplit = preg_split('/\r?\n\r?\n/', $bestPart, 2);
                    $subHeadersRaw = $headerBodySplit[0] ?? '';
                    $subBody = $headerBodySplit[1] ?? '';
                    $subHeaders = [];
                    foreach (preg_split('/\r?\n/', $subHeadersRaw) as $subLine) {
                        $parts2 = explode(':', $subLine, 2);
                        if (count($parts2) === 2) {
                            $subHeaders[trim($parts2[0])] = trim($parts2[1]);
                        }
                    }
                    $encoding = strtolower($subHeaders['Content-Transfer-Encoding'] ?? '');
                    $charset = 'UTF-8';
                    $subContentType = $subHeaders['Content-Type'] ?? '';
                    if (preg_match('/charset=\"?([^;\"]+)/i', $subContentType, $m2)) {
                        $charset = strtoupper(trim($m2[1], '"'));
                    }
                    if ($encoding === 'base64') {
                        $subBody = base64_decode($subBody);
                    } elseif ($encoding === 'quoted-printable') {
                        $subBody = quoted_printable_decode($subBody);
                        $subBody = str_replace(["=C2=A0", "=\r\n", "=\n", "=\r"], '', $subBody);
                    }
                    if (strtoupper($charset) !== 'UTF-8') {
                        $subBody = @mb_convert_encoding($subBody, 'UTF-8', $charset);
                    }
                    $subBody = html_entity_decode($subBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $decodedBody = $subBody;
                }
            }
        } else {
            // معالجة الترميز للرسائل غير multipart
            // فصل headers عن body بدقة
            $headerBodySplit = preg_split('/\r?\n\r?\n/', $body, 2);
            $realBody = $headerBodySplit[1] ?? $body;
            $encoding = strtolower($headers['Content-Transfer-Encoding'] ?? '');
            $charset = 'UTF-8';
            if (preg_match('/charset=\"?([^;\"]+)/i', $contentType, $m)) {
                $charset = strtoupper(trim($m[1], '"'));
            }
            if ($encoding === 'base64') {
                $decodedBody = base64_decode($realBody);
            } elseif ($encoding === 'quoted-printable') {
                $decodedBody = quoted_printable_decode($realBody);
                $decodedBody = str_replace(["=C2=A0", "=\r\n", "=\n", "=\r"], '', $decodedBody);
            } else {
                $decodedBody = $realBody;
            }
            if (strtoupper($charset) !== 'UTF-8') {
                $decodedBody = @mb_convert_encoding($decodedBody, 'UTF-8', $charset);
            }
            $decodedBody = html_entity_decode($decodedBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $email = Email::create([
            'email_account_id' => $emailAccountId,
            'email_id' => $headers['Message-ID'] ?? uniqid('mbox_'),
            'subject' => $headers['Subject'] ?? null,
            'body' => $decodedBody,
            'from' => $headers['From'] ?? null,
            'to' => $headers['To'] ?? null,
            'received_at' => isset($headers['Date']) ? date('Y-m-d H:i:s', strtotime($headers['Date'])) : null,
            'labels' => null,
        ]);
        // معالجة المرفقات يمكن إضافتها لاحقاً
    }
}
