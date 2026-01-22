<?php

declare(strict_types=1);

namespace Marko\Mail;

class Message
{
    /** @var array<Address> */
    private array $to = [];

    /** @var array<Address> */
    private array $cc = [];

    /** @var array<Address> */
    private array $bcc = [];

    private ?Address $from = null;

    private ?Address $replyTo = null;

    private ?string $subject = null;

    private ?string $html = null;

    private ?string $text = null;

    /** @var array<Attachment> */
    private array $attachments = [];

    /** @var array<string, string> */
    private array $headers = [];

    private ?int $priority = null;

    public static function create(): self
    {
        return new self();
    }

    public function to(
        string $email,
        ?string $name = null,
    ): self {
        $this->to[] = new Address($email, $name);

        return $this;
    }

    /** @return array<Address> */
    public function getTo(): array
    {
        return $this->to;
    }

    public function cc(
        string $email,
        ?string $name = null,
    ): self {
        $this->cc[] = new Address($email, $name);

        return $this;
    }

    /** @return array<Address> */
    public function getCc(): array
    {
        return $this->cc;
    }

    public function bcc(
        string $email,
        ?string $name = null,
    ): self {
        $this->bcc[] = new Address($email, $name);

        return $this;
    }

    /** @return array<Address> */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function from(
        string $email,
        ?string $name = null,
    ): self {
        $this->from = new Address($email, $name);

        return $this;
    }

    public function getFrom(): ?Address
    {
        return $this->from;
    }

    public function replyTo(
        string $email,
        ?string $name = null,
    ): self {
        $this->replyTo = new Address($email, $name);

        return $this;
    }

    public function getReplyTo(): ?Address
    {
        return $this->replyTo;
    }

    public function subject(
        string $subject,
    ): self {
        $this->subject = $subject;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function html(
        string $html,
    ): self {
        $this->html = $html;

        return $this;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }

    public function text(
        string $text,
    ): self {
        $this->text = $text;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function attach(
        string $path,
        ?string $name = null,
        ?string $mimeType = null,
    ): self {
        $this->attachments[] = Attachment::fromPath($path, $name, $mimeType);

        return $this;
    }

    /** @return array<Attachment> */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function embed(
        string $path,
        string $contentId,
        ?string $name = null,
        ?string $mimeType = null,
    ): self {
        $this->attachments[] = Attachment::inline($path, $contentId, $name, $mimeType);

        return $this;
    }

    public function header(
        string $name,
        string $value,
    ): self {
        $this->headers[$name] = $value;

        return $this;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function priority(
        int $priority,
    ): self {
        $this->priority = $priority;

        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }
}
