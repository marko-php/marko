<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp;

use Marko\Mail\Address;
use Marko\Mail\Attachment;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Exception\MessageException;
use Marko\Mail\Message;

class SmtpMailer implements MailerInterface
{
    public function __construct(
        private SmtpTransport $transport,
        private ?SmtpConfig $config = null,
    ) {}

    public function send(
        Message $message,
    ): bool {
        $from = $message->getFrom();
        $recipients = $this->getAllRecipients($message);

        // Validate recipients
        if ($recipients === []) {
            throw MessageException::noRecipients();
        }

        // Send envelope
        $this->transport->mailFrom($from->email);

        foreach ($recipients as $recipient) {
            $this->transport->rcptTo($recipient->email);
        }

        // Build and send message
        $rawMessage = $this->buildMessage($message);
        $this->transport->data($rawMessage);

        return true;
    }

    public function sendRaw(
        string $to,
        string $raw,
    ): bool {
        // Extract sender from raw message
        $from = $this->extractFromAddress($raw);

        // Send envelope
        $this->transport->mailFrom($from);
        $this->transport->rcptTo($to);

        // Send raw message as-is
        $this->transport->data($raw);

        return true;
    }

    private function extractFromAddress(
        string $raw,
    ): string {
        // Extract the From header from the raw message
        if (preg_match('/^From:\s*<?([^<>\r\n]+@[^<>\r\n]+)>?/mi', $raw, $matches)) {
            return trim($matches[1]);
        }

        // Default fallback
        return '';
    }

    /**
     * @return array<Address>
     */
    private function getAllRecipients(
        Message $message,
    ): array {
        return array_merge(
            $message->getTo(),
            $message->getCc(),
            $message->getBcc(),
        );
    }

    private function buildMessage(
        Message $message,
    ): string {
        $html = $message->getHtml();
        $text = $message->getText();
        $attachments = $message->getAttachments();

        // Separate inline from regular attachments
        $regularAttachments = array_filter($attachments, fn (Attachment $a) => $a->contentId === null);
        $inlineAttachments = array_filter($attachments, fn (Attachment $a) => $a->contentId !== null);

        $hasRegularAttachments = $regularAttachments !== [];
        $hasInlineAttachments = $inlineAttachments !== [];
        $isAlternative = $html !== null && $text !== null;

        // Determine boundaries
        $mixedBoundary = $hasRegularAttachments ? $this->generateBoundary() : null;
        $relatedBoundary = $hasInlineAttachments ? $this->generateBoundary() : null;
        $alternativeBoundary = $isAlternative ? $this->generateBoundary() : null;

        $headers = $this->buildHeaders(
            $message,
            $hasRegularAttachments,
            $hasInlineAttachments,
            $isAlternative,
            $mixedBoundary,
            $relatedBoundary,
            $alternativeBoundary,
        );
        $body = $this->buildBody(
            $message,
            $regularAttachments,
            $inlineAttachments,
            $hasRegularAttachments,
            $hasInlineAttachments,
            $isAlternative,
            $mixedBoundary,
            $relatedBoundary,
            $alternativeBoundary,
        );

        return $headers . "\r\n" . $body;
    }

    private function buildHeaders(
        Message $message,
        bool $hasRegularAttachments,
        bool $hasInlineAttachments,
        bool $isAlternative,
        ?string $mixedBoundary,
        ?string $relatedBoundary,
        ?string $alternativeBoundary,
    ): string {
        $headers = [];

        // From
        $from = $message->getFrom();
        if ($from !== null) {
            $headers[] = 'From: ' . $from->toString();
        }

        // To
        $toAddresses = $message->getTo();
        if ($toAddresses !== []) {
            $to = array_map(fn (Address $addr) => $addr->toString(), $toAddresses);
            $headers[] = 'To: ' . implode(', ', $to);
        }

        // Cc
        $ccAddresses = $message->getCc();
        if ($ccAddresses !== []) {
            $cc = array_map(fn (Address $addr) => $addr->toString(), $ccAddresses);
            $headers[] = 'Cc: ' . implode(', ', $cc);
        }

        // Reply-To
        $replyTo = $message->getReplyTo();
        if ($replyTo !== null) {
            $headers[] = 'Reply-To: ' . $replyTo->toString();
        }

        // Subject
        $subject = $message->getSubject();
        if ($subject !== null) {
            $headers[] = 'Subject: ' . $this->encodeHeader($subject);
        }

        // Priority
        $priority = $message->getPriority();
        if ($priority !== null) {
            $headers[] = 'X-Priority: ' . $priority;
        }

        // MIME Version
        $headers[] = 'MIME-Version: 1.0';

        // Content-Type depends on structure
        // Priority: mixed > related > alternative > single
        if ($hasRegularAttachments && $mixedBoundary !== null) {
            $headers[] = "Content-Type: multipart/mixed; boundary=\"$mixedBoundary\"";
        } elseif ($hasInlineAttachments && $relatedBoundary !== null) {
            $headers[] = "Content-Type: multipart/related; boundary=\"$relatedBoundary\"";
        } elseif ($isAlternative && $alternativeBoundary !== null) {
            $headers[] = "Content-Type: multipart/alternative; boundary=\"$alternativeBoundary\"";
        } elseif ($message->getHtml() !== null) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';
        }

        // Custom headers
        foreach ($message->getHeaders() as $name => $value) {
            $headers[] = "$name: $value";
        }

        return implode("\r\n", $headers);
    }

    /**
     * @param array<Attachment> $regularAttachments
     * @param array<Attachment> $inlineAttachments
     */
    private function buildBody(
        Message $message,
        array $regularAttachments,
        array $inlineAttachments,
        bool $hasRegularAttachments,
        bool $hasInlineAttachments,
        bool $isAlternative,
        ?string $mixedBoundary,
        ?string $relatedBoundary,
        ?string $alternativeBoundary,
    ): string {
        $html = $message->getHtml();
        $text = $message->getText();

        if ($hasRegularAttachments && $mixedBoundary !== null) {
            return $this->buildMixedBody(
                $text,
                $html,
                $regularAttachments,
                $inlineAttachments,
                $mixedBoundary,
                $relatedBoundary,
                $alternativeBoundary,
            );
        }

        if ($hasInlineAttachments && $relatedBoundary !== null) {
            return $this->buildRelatedBody($text, $html, $inlineAttachments, $relatedBoundary, $alternativeBoundary);
        }

        if ($isAlternative && $alternativeBoundary !== null) {
            return $this->buildAlternativeBody($text, $html, $alternativeBoundary);
        }

        if ($html !== null) {
            return quoted_printable_encode($html);
        }

        if ($text !== null) {
            return quoted_printable_encode($text);
        }

        return '';
    }

    /**
     * @param array<Attachment> $regularAttachments
     * @param array<Attachment> $inlineAttachments
     */
    private function buildMixedBody(
        ?string $text,
        ?string $html,
        array $regularAttachments,
        array $inlineAttachments,
        string $mixedBoundary,
        ?string $relatedBoundary,
        ?string $alternativeBoundary,
    ): string {
        $body = [];
        $hasInlineAttachments = $inlineAttachments !== [];

        // Content part
        $body[] = "--$mixedBoundary";

        if ($hasInlineAttachments && $relatedBoundary !== null) {
            // Nested related (for inline images)
            $body[] = "Content-Type: multipart/related; boundary=\"$relatedBoundary\"";
            $body[] = '';
            $body[] = $this->buildRelatedBody(
                $text,
                $html,
                $inlineAttachments,
                $relatedBoundary,
                $alternativeBoundary,
            );
        } elseif ($text !== null && $html !== null && $alternativeBoundary !== null) {
            // Nested alternative
            $body[] = "Content-Type: multipart/alternative; boundary=\"$alternativeBoundary\"";
            $body[] = '';
            $body[] = $this->buildAlternativeBody($text, $html, $alternativeBoundary);
        } elseif ($html !== null) {
            $body[] = 'Content-Type: text/html; charset=UTF-8';
            $body[] = 'Content-Transfer-Encoding: quoted-printable';
            $body[] = '';
            $body[] = quoted_printable_encode($html);
        } elseif ($text !== null) {
            $body[] = 'Content-Type: text/plain; charset=UTF-8';
            $body[] = 'Content-Transfer-Encoding: quoted-printable';
            $body[] = '';
            $body[] = quoted_printable_encode($text);
        }

        // Regular attachments
        foreach ($regularAttachments as $attachment) {
            $body[] = "--$mixedBoundary";
            $body[] = "Content-Type: $attachment->mimeType";
            $body[] = 'Content-Transfer-Encoding: base64';
            $body[] = "Content-Disposition: attachment; filename=\"$attachment->name\"";
            $body[] = '';
            $body[] = chunk_split(base64_encode($attachment->content), 76, "\r\n");
        }

        $body[] = "--$mixedBoundary--";

        return implode("\r\n", $body);
    }

    /**
     * @param array<Attachment> $inlineAttachments
     */
    private function buildRelatedBody(
        ?string $text,
        ?string $html,
        array $inlineAttachments,
        string $relatedBoundary,
        ?string $alternativeBoundary,
    ): string {
        $body = [];
        $isAlternative = $text !== null && $html !== null;

        // Content part (HTML or alternative)
        $body[] = "--$relatedBoundary";

        if ($isAlternative && $alternativeBoundary !== null) {
            $body[] = "Content-Type: multipart/alternative; boundary=\"$alternativeBoundary\"";
            $body[] = '';
            $body[] = $this->buildAlternativeBody($text, $html, $alternativeBoundary);
        } elseif ($html !== null) {
            $body[] = 'Content-Type: text/html; charset=UTF-8';
            $body[] = 'Content-Transfer-Encoding: quoted-printable';
            $body[] = '';
            $body[] = quoted_printable_encode($html);
        }

        // Inline attachments
        foreach ($inlineAttachments as $attachment) {
            $body[] = "--$relatedBoundary";
            $body[] = "Content-Type: $attachment->mimeType";
            $body[] = 'Content-Transfer-Encoding: base64';
            $body[] = "Content-ID: <$attachment->contentId>";
            $body[] = "Content-Disposition: inline; filename=\"$attachment->name\"";
            $body[] = '';
            $body[] = chunk_split(base64_encode($attachment->content), 76, "\r\n");
        }

        $body[] = "--$relatedBoundary--";

        return implode("\r\n", $body);
    }

    private function buildAlternativeBody(
        ?string $text,
        ?string $html,
        string $boundary,
    ): string {
        $body = [];

        // Text part
        if ($text !== null) {
            $body[] = "--$boundary";
            $body[] = 'Content-Type: text/plain; charset=UTF-8';
            $body[] = 'Content-Transfer-Encoding: quoted-printable';
            $body[] = '';
            $body[] = quoted_printable_encode($text);
        }

        // HTML part
        if ($html !== null) {
            $body[] = "--$boundary";
            $body[] = 'Content-Type: text/html; charset=UTF-8';
            $body[] = 'Content-Transfer-Encoding: quoted-printable';
            $body[] = '';
            $body[] = quoted_printable_encode($html);
        }

        // End boundary
        $body[] = "--$boundary--";

        return implode("\r\n", $body);
    }

    private function generateBoundary(): string
    {
        return '=_Part_' . bin2hex(random_bytes(16));
    }

    private function encodeHeader(
        string $value,
    ): string {
        // Check if the value contains non-ASCII characters
        if (!preg_match('/[^\x20-\x7E]/', $value)) {
            return $value;
        }

        // RFC 2047 Base64 encoding for non-ASCII headers
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
