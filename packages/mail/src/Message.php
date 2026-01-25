<?php

declare(strict_types=1);

namespace Marko\Mail;

use Marko\Mail\Exceptions\MessageException;

class Message
{
    /** @var array<Address> */
    public private(set) array $to = [];

    /** @var array<Address> */
    public private(set) array $cc = [];

    /** @var array<Address> */
    public private(set) array $bcc = [];

    public private(set) ?Address $from = null;

    public private(set) ?Address $replyTo = null;

    public private(set) ?string $subject = null;

    public private(set) ?string $html = null;

    public private(set) ?string $text = null;

    /** @var array<Attachment> */
    public private(set) array $attachments = [];

    /** @var array<string, string> */
    public private(set) array $headers = [];

    public private(set) ?int $priority = null;

    public private(set) ?string $view = null;

    /** @var array<string, mixed> */
    public private(set) array $viewData = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * @throws MessageException
     */
    public function to(
        string $email,
        ?string $name = null,
    ): self {
        $this->to[] = new Address($email, $name);

        return $this;
    }

    /**
     * @throws MessageException
     */
    public function cc(
        string $email,
        ?string $name = null,
    ): self {
        $this->cc[] = new Address($email, $name);

        return $this;
    }

    /**
     * @throws MessageException
     */
    public function bcc(
        string $email,
        ?string $name = null,
    ): self {
        $this->bcc[] = new Address($email, $name);

        return $this;
    }

    /**
     * @throws MessageException
     */
    public function from(
        string $email,
        ?string $name = null,
    ): self {
        $this->from = new Address($email, $name);

        return $this;
    }

    /**
     * @throws MessageException
     */
    public function replyTo(
        string $email,
        ?string $name = null,
    ): self {
        $this->replyTo = new Address($email, $name);

        return $this;
    }

    public function subject(
        string $subject,
    ): self {
        $this->subject = $subject;

        return $this;
    }

    public function html(
        string $html,
    ): self {
        $this->html = $html;

        return $this;
    }

    public function text(
        string $text,
    ): self {
        $this->text = $text;

        return $this;
    }

    /**
     * @throws MessageException
     */
    public function attach(
        string $path,
        ?string $name = null,
        ?string $mimeType = null,
    ): self {
        $this->attachments[] = Attachment::fromPath($path, $name, $mimeType);

        return $this;
    }

    /**
     * @throws MessageException
     */
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

    public function priority(
        int $priority,
    ): self {
        $this->priority = $priority;

        return $this;
    }

    public function view(
        string $template,
    ): self {
        $this->view = $template;

        return $this;
    }

    /**
     * @param array<string, mixed>|string $key
     */
    public function with(
        array|string $key,
        mixed $value = null,
    ): self {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this;
    }
}
