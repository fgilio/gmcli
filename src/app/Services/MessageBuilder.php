<?php

namespace App\Services;

/**
 * Builds RFC2822 email messages for Gmail API.
 *
 * Creates properly formatted messages with:
 * - Headers (From, To, Cc, Bcc, Subject, etc.)
 * - Body (text/plain)
 * - Attachments (multipart/mixed)
 * - Reply-to threading (In-Reply-To, References)
 */
class MessageBuilder
{
    private string $from = '';

    private array $to = [];

    private array $cc = [];

    private array $bcc = [];

    private string $subject = '';

    private string $body = '';

    private array $attachments = [];

    private ?string $inReplyTo = null;

    private ?string $references = null;

    private ?string $threadId = null;

    public function from(string $email): self
    {
        $this->from = $email;

        return $this;
    }

    public function to(array $emails): self
    {
        $this->to = $emails;

        return $this;
    }

    public function cc(array $emails): self
    {
        $this->cc = $emails;

        return $this;
    }

    public function bcc(array $emails): self
    {
        $this->bcc = $emails;

        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Adds file attachment.
     */
    public function attach(string $path): self
    {
        if (! file_exists($path)) {
            throw new \RuntimeException("Attachment not found: {$path}");
        }

        $this->attachments[] = [
            'path' => $path,
            'filename' => basename($path),
            'mimeType' => $this->getMimeType($path),
        ];

        return $this;
    }

    /**
     * Sets reply-to headers for threading.
     */
    public function replyTo(string $messageId, ?string $references = null, ?string $threadId = null): self
    {
        $this->inReplyTo = $messageId;
        $this->references = $references ?? $messageId;
        $this->threadId = $threadId;

        return $this;
    }

    /**
     * Gets the thread ID if replying.
     */
    public function getThreadId(): ?string
    {
        return $this->threadId;
    }

    /**
     * Builds the raw RFC2822 message.
     *
     * @return string Base64url-encoded message
     */
    public function build(): string
    {
        if (empty($this->attachments)) {
            $message = $this->buildSimpleMessage();
        } else {
            $message = $this->buildMultipartMessage();
        }

        return $this->encodeBase64Url($message);
    }

    private function buildSimpleMessage(): string
    {
        $headers = $this->buildHeaders('text/plain; charset=utf-8');

        return $headers."\r\n".$this->body;
    }

    private function buildMultipartMessage(): string
    {
        $boundary = 'gmcli_'.bin2hex(random_bytes(16));

        $headers = $this->buildHeaders("multipart/mixed; boundary=\"{$boundary}\"");

        $parts = [];

        // Text body part
        $parts[] = "Content-Type: text/plain; charset=utf-8\r\n"
            ."Content-Transfer-Encoding: quoted-printable\r\n"
            ."\r\n"
            .$this->quotedPrintableEncode($this->body);

        // Attachment parts
        foreach ($this->attachments as $att) {
            $content = file_get_contents($att['path']);
            $encoded = chunk_split(base64_encode($content));

            $parts[] = "Content-Type: {$att['mimeType']}; name=\"{$att['filename']}\"\r\n"
                ."Content-Disposition: attachment; filename=\"{$att['filename']}\"\r\n"
                ."Content-Transfer-Encoding: base64\r\n"
                ."\r\n"
                .$encoded;
        }

        $body = "--{$boundary}\r\n"
            .implode("\r\n--{$boundary}\r\n", $parts)
            ."\r\n--{$boundary}--";

        return $headers."\r\n".$body;
    }

    private function buildHeaders(string $contentType): string
    {
        $headers = [];

        $headers[] = 'MIME-Version: 1.0';
        $headers[] = "From: {$this->from}";
        $headers[] = 'To: '.implode(', ', $this->to);

        if (! empty($this->cc)) {
            $headers[] = 'Cc: '.implode(', ', $this->cc);
        }

        if (! empty($this->bcc)) {
            $headers[] = 'Bcc: '.implode(', ', $this->bcc);
        }

        $headers[] = 'Subject: '.$this->encodeSubject($this->subject);
        $headers[] = "Content-Type: {$contentType}";

        if ($this->inReplyTo) {
            $headers[] = "In-Reply-To: {$this->inReplyTo}";
        }

        if ($this->references) {
            $headers[] = "References: {$this->references}";
        }

        return implode("\r\n", $headers)."\r\n";
    }

    private function encodeSubject(string $subject): string
    {
        // Only encode if contains non-ASCII
        if (preg_match('/[^\x20-\x7E]/', $subject)) {
            return '=?UTF-8?B?'.base64_encode($subject).'?=';
        }

        return $subject;
    }

    private function quotedPrintableEncode(string $text): string
    {
        return quoted_printable_encode($text);
    }

    private function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeMap = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'html' => 'text/html',
            'json' => 'application/json',
            'xml' => 'application/xml',
        ];

        return $mimeMap[$extension] ?? 'application/octet-stream';
    }

    private function encodeBase64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
