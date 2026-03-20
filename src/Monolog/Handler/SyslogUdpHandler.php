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

use DateTimeInterface;
use Monolog\Handler\Syslog_Udp\Udp_Socket;
use Monolog\Level;
use Monolog\Log_Record;
/**
 * A Handler for logging to a remote syslogd server.
 *
 * @author Jesper Skovgaard Nielsen <nulpunkt@gmail.com>
 * @author Dominik Kukacka <dominik.kukacka@gmail.com>
 */
class Syslog_Udp_Handler extends Abstract_Syslog_Handler
{
    public const RFC3164 = 0;
    public const RFC5424 = 1;
    public const RFC5424e = 2;
    /** @var array<self::RFC*, string> */
    private array $date_formats = [self::RFC3164 => 'M d H:i:s', self::RFC5424 => \DateTime::RFC3339, self::RFC5424e => \DateTime::RFC3339_EXTENDED];
    protected Udp_Socket $socket;
    /**
     * @param  string                    $host     Either IP/hostname or a path to a unix socket (port must be 0 then)
     * @param  int                       $port     Port number, or 0 if $host is a unix socket
     * @param  string|int                $facility Either one of the names of the keys in $this->facilities, or a LOG_* facility constant
     * @param  bool                      $bubble   Whether the messages that are handled can bubble up the stack or not
     * @param  string                    $ident    Program name or tag for each log message.
     * @param  int                       $rfc      RFC to format the message for.
     * @throws MissingExtensionException when there is no socket extension
     *
     * @phpstan-param self::RFC* $rfc
     */
    public function __construct(string $host, int $port = 514, string|int $facility = LOG_USER, int|string|Level $level = Level::Debug, bool $bubble = true, protected string $ident = 'php', protected int $rfc = self::RFC5424)
    {
        if (!\extension_loaded('sockets')) {
            throw new Missing_Extension_Exception('The sockets extension is required to use the SyslogUdpHandler');
        }
        parent::__construct($facility, $level, $bubble);
        $this->socket = new Udp_Socket($host, $port);
    }
    protected function write(Log_Record $record): void
    {
        $lines = $this->split_message_into_lines($record->formatted);
        $header = $this->make_common_syslog_header($this->to_syslog_priority($record->level), $record->datetime);
        foreach ($lines as $line) {
            $this->socket->write($line, $header);
        }
    }
    public function close(): void
    {
        $this->socket->close();
    }
    /**
     * @param  string|string[] $message
     * @return string[]
     */
    private function split_message_into_lines($message): array
    {
        if (\is_array($message)) {
            $message = implode("\n", $message);
        }
        $lines = preg_split('/$\R?^/m', (string) $message, -1, PREG_SPLIT_NO_EMPTY);
        if (false === $lines) {
            $pcre_error_code = preg_last_error();
            throw new \RuntimeException('Could not preg_split: ' . $pcre_error_code . ' / ' . preg_last_error_msg());
        }
        return $lines;
    }
    /**
     * Make common syslog header (see rfc5424 or rfc3164)
     */
    protected function make_common_syslog_header(int $severity, DateTimeInterface $datetime): string
    {
        $priority = $severity + $this->facility;
        $pid = getmypid();
        if (false === $pid) {
            $pid = '-';
        }
        $hostname = gethostname();
        if (false === $hostname) {
            $hostname = '-';
        }
        if ($this->rfc === self::RFC3164) {
            // see https://github.com/phpstan/phpstan/issues/5348
            // @phpstan-ignore-next-line
            $date_new = $datetime->set_timezone(new \DateTimeZone('UTC'));
            $date = $date_new->format($this->date_formats[$this->rfc]);
            return "<{$priority}>" . $date . ' ' . $hostname . ' ' . $this->ident . '[' . $pid . ']: ';
        }
        $date = $datetime->format($this->date_formats[$this->rfc]);
        return "<{$priority}>1 " . $date . ' ' . $hostname . ' ' . $this->ident . ' ' . $pid . ' - - ';
    }
    /**
     * Inject your own socket, mainly used for testing
     *
     * @return $this
     */
    public function set_socket(Udp_Socket $socket): self
    {
        $this->socket = $socket;
        return $this;
    }
}