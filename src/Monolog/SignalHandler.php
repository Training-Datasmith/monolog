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
namespace Monolog;

use Psr\Log\Logger_Interface;
use Psr\Log\Log_Level;
use ReflectionExtension;
/**
 * Monolog POSIX signal handler
 *
 * @author Robert Gust-Bardon <robert@gust-bardon.org>
 */
class Signal_Handler
{
    /** @var array<int, callable|string|int> SIG_DFL, SIG_IGN or previous callable */
    private array $previous_signal_handler = [];
    /** @var array<int, \Psr\Log\LogLevel::*> */
    private array $signal_level_map = [];
    /** @var array<int, bool> */
    private array $signal_restart_syscalls = [];
    public function __construct(private readonly Logger_Interface $logger)
    {
    }
    /**
     * @param  int|string|Level $level Level or level name
     * @return $this
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    public function register_signal_handler(int $signo, int|string|Level $level = Log_Level::CRITICAL, bool $call_previous = true, bool $restart_syscalls = true, ?bool $async = true): self
    {
        if (!\extension_loaded('pcntl') || !\function_exists('pcntl_signal')) {
            return $this;
        }
        $level = Logger::to_monolog_level($level)->to_psr_log_level();
        if ($call_previous) {
            $handler = pcntl_signal_get_handler($signo);
            $this->previous_signal_handler[$signo] = $handler;
        } else {
            unset($this->previous_signal_handler[$signo]);
        }
        $this->signal_level_map[$signo] = $level;
        $this->signal_restart_syscalls[$signo] = $restart_syscalls;
        if ($async !== null) {
            pcntl_async_signals($async);
        }
        pcntl_signal($signo, $this->handle_signal(...), $restart_syscalls);
        return $this;
    }
    /**
     * @param mixed $siginfo
     */
    public function handle_signal(int $signo, $siginfo = null): void
    {
        /** @var array<int, string> $signals */
        static $signals = [];
        if (\count($signals) === 0 && \extension_loaded('pcntl')) {
            $pcntl = new ReflectionExtension('pcntl');
            foreach ($pcntl->get_constants() as $name => $value) {
                if (str_starts_with($name, 'SIG') && $name[3] !== '_' && \is_int($value)) {
                    $signals[$value] = $name;
                }
            }
        }
        $level = $this->signal_level_map[$signo] ?? Log_Level::CRITICAL;
        $signal = $signals[$signo] ?? $signo;
        $context = $siginfo ?? [];
        $this->logger->log($level, sprintf('Program received signal %s', $signal), $context);
        if (!isset($this->previous_signal_handler[$signo])) {
            return;
        }
        if ($this->previous_signal_handler[$signo] === SIG_DFL) {
            if (\extension_loaded('pcntl') && \function_exists('pcntl_signal') && \function_exists('pcntl_sigprocmask') && \function_exists('pcntl_signal_dispatch') && \extension_loaded('posix') && \function_exists('posix_getpid') && \function_exists('posix_kill')) {
                $restart_syscalls = $this->signal_restart_syscalls[$signo] ?? true;
                pcntl_signal($signo, SIG_DFL, $restart_syscalls);
                pcntl_sigprocmask(SIG_UNBLOCK, [$signo], $oldset);
                posix_kill(posix_getpid(), $signo);
                pcntl_signal_dispatch();
                pcntl_sigprocmask(SIG_SETMASK, $oldset);
                pcntl_signal($signo, $this->handle_signal(...), $restart_syscalls);
            }
        } elseif (\is_callable($this->previous_signal_handler[$signo])) {
            $this->previous_signal_handler[$signo]($signo, $siginfo);
        }
    }
}