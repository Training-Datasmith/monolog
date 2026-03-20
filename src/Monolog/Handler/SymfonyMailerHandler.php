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

use Closure;
use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Line_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Utils;
use Symfony\Component\Mailer\Mailer_Interface;
use Symfony\Component\Mailer\Transport\Transport_Interface;
use Symfony\Component\Mime\Email;
/**
 * SymfonyMailerHandler uses Symfony's Mailer component to send the emails
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Symfony_Mailer_Handler extends Mail_Handler
{
    /**
     * @phpstan-param Email|Closure(string, LogRecord[]): Email $email
     *
     * @param MailerInterface|TransportInterface $mailer The mailer to use
     * @param Closure|Email $emailTemplate An email template, the subject/body will be replaced
     */
    public function __construct(protected Mailer_Interface|Transport_Interface $mailer, private readonly Email|Closure $email_template, int|string|Level $level = Level::Error, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }
    /**
     * {@inheritDoc}
     */
    protected function send(string $content, array $records): void
    {
        $this->mailer->send($this->build_message($content, $records));
    }
    /**
     * Gets the formatter for the Swift_Message subject.
     *
     * @param string|null $format The format of the subject
     */
    protected function get_subject_formatter(?string $format): Formatter_Interface
    {
        return new Line_Formatter($format);
    }
    /**
     * Creates instance of Email to be sent
     *
     * @param string      $content formatted email body to be sent
     * @param LogRecord[] $records Log records that formed the content
     */
    protected function build_message(string $content, array $records): Email
    {
        $message = null;
        if ($this->email_template instanceof Email) {
            $message = clone $this->email_template;
        } elseif (\is_callable($this->email_template)) {
            $message = ($this->email_template)($content, $records);
        }
        if (!$message instanceof Email) {
            $record = reset($records);
            throw new \InvalidArgumentException('Could not resolve message as instance of Email or a callable returning it' . ($record instanceof Log_Record ? Utils::get_record_message_for_exception($record) : ''));
        }
        if (\count($records) > 0) {
            $subject_formatter = $this->get_subject_formatter($message->get_subject());
            $message->subject($subject_formatter->format($this->get_highest_record($records)));
        }
        if ($this->is_html_body($content)) {
            if (null !== $charset = $message->get_html_charset()) {
                $message->html($content, $charset);
            } else {
                $message->html($content);
            }
        } else if (null !== $charset = $message->get_text_charset()) {
            $message->text($content, $charset);
        } else {
            $message->text($content);
        }
        return $message->date(new \DateTimeImmutable());
    }
}