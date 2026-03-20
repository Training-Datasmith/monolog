<?php

declare (strict_types=1);
/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Monolog\Handler;

use Monolog\Formatter\Line_Formatter;
use Monolog\Level;
/**
 * NativeMailerHandler uses the mail() function to send the emails
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Mark Garrett <mark@moderndeveloperllc.com>
 */
class Native_Mailer_Handler extends Mail_Handler
{
    /**
     * The email addresses to which the message will be sent
     * @var string[]
     */
    protected array $to;
    /**
     * Optional headers for the message
     * @var string[]
     */
    protected array $headers = [];
    /**
     * Optional parameters for the message
     * @var string[]
     */
    protected array $parameters = [];
    /**
     * The Content-type for the message
     */
    protected string|null $content_type = null;
    /**
     * The encoding for the message
     */
    protected string $encoding = 'utf-8';
    /**
     * @param string|string[] $to             The receiver of the mail
     * @param string          $subject        The subject of the mail
     * @param string          $from           The sender of the mail
     * @param int             $maxColumnWidth The maximum column width that the message lines will have
     */
    public function __construct(string|array $to, protected string $subject, string $from, int|string|Level $level = Level::Error, bool $bubble = true, protected int $max_column_width = 70)
    {
        parent::__construct($level, $bubble);
        $this->to = (array) $to;
        $this->add_header(sprintf('From: %s', $from));
    }
    /**
     * Add headers to the message
     *
     * @param  string|string[] $headers Custom added headers
     * @return $this
     */
    public function add_header($headers): self
    {
        foreach ((array) $headers as $header) {
            if (str_contains($header, "\n") || str_contains($header, "\r")) {
                throw new \InvalidArgumentException('Headers can not contain newline characters for security reasons');
            }
            $this->headers[] = $header;
        }
        return $this;
    }
    /**
     * Add parameters to the message
     *
     * @param  string|string[] $parameters Custom added parameters
     * @return $this
     */
    public function add_parameter($parameters): self
    {
        $this->parameters = array_merge($this->parameters, (array) $parameters);
        return $this;
    }
    /**
     * @inheritDoc
     */
    protected function send(string $content, array $records): void
    {
        $content_type = $this->get_content_type() ?? ($this->is_html_body($content) ? 'text/html' : 'text/plain');
        if ($content_type !== 'text/html') {
            $content = wordwrap($content, $this->max_column_width);
        }
        $headers = ltrim(implode("\r\n", $this->headers) . "\r\n", "\r\n");
        $headers .= 'Content-type: ' . $content_type . '; charset=' . $this->get_encoding() . "\r\n";
        if ($content_type === 'text/html' && !str_contains($headers, 'MIME-Version:')) {
            $headers .= 'MIME-Version: 1.0' . "\r\n";
        }
        $subject_formatter = new Line_Formatter($this->subject);
        $subject = str_replace(["\r", "\n"], '', $subject_formatter->format($this->get_highest_record($records)));
        $parameters = implode(' ', $this->parameters);
        foreach ($this->to as $to) {
            $this->mail($to, $subject, $content, $headers, $parameters);
        }
    }
    public function get_content_type(): ?string
    {
        return $this->content_type;
    }
    public function get_encoding(): string
    {
        return $this->encoding;
    }
    /**
     * @param  string $contentType The content type of the email - Defaults to text/plain. Use text/html for HTML messages.
     * @return $this
     */
    public function set_content_type(string $content_type): self
    {
        if (str_contains($content_type, "\n") || str_contains($content_type, "\r")) {
            throw new \InvalidArgumentException('The content type can not contain newline characters to prevent email header injection');
        }
        $this->content_type = $content_type;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_encoding(string $encoding): self
    {
        if (str_contains($encoding, "\n") || str_contains($encoding, "\r")) {
            throw new \InvalidArgumentException('The encoding can not contain newline characters to prevent email header injection');
        }
        $this->encoding = $encoding;
        return $this;
    }
    protected function mail(string $to, string $subject, string $content, string $headers, string $parameters): void
    {
        mail($to, $subject, $content, $headers, $parameters);
    }
}