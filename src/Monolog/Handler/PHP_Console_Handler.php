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

use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Line_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Utils;
use Php_Console\Connector;
use Php_Console\Handler as VendorPhpConsoleHandler;
use Php_Console\Helper;
use Php_Console\Storage;
/**
 * Monolog handler for Google Chrome extension "PHP Console"
 *
 * Display PHP error/debug log messages in Google Chrome console and notification popups, executes PHP code remotely
 *
 * Usage:
 * 1. Install Google Chrome extension [now dead and removed from the chrome store]
 * 2. See overview https://github.com/barbushin/php-console#overview
 * 3. Install PHP Console library https://github.com/barbushin/php-console#installation
 * 4. Example (result will looks like http://i.hizliresim.com/vg3Pz4.png)
 *
 *      $logger = new \Monolog\Logger('all', array(new \Monolog\Handler\PHPConsoleHandler()));
 *      \Monolog\ErrorHandler::register($logger);
 *      echo $undefinedVar;
 *      $logger->debug('SELECT * FROM users', array('db', 'time' => 0.012));
 *      PC::debug($_SERVER); // PHP Console debugger for any type of vars
 *
 * @author Sergey Barbushin https://www.linkedin.com/in/barbushin
 * @phpstan-type Options array{
 *     enabled: bool,
 *     classesPartialsTraceIgnore: string[],
 *     debugTagsKeysInContext: array<int|string>,
 *     useOwnErrorsHandler: bool,
 *     useOwnExceptionsHandler: bool,
 *     sourcesBasePath: string|null,
 *     registerHelper: bool,
 *     serverEncoding: string|null,
 *     headersLimit: int|null,
 *     password: string|null,
 *     enableSslOnlyMode: bool,
 *     ipMasks: string[],
 *     enableEvalListener: bool,
 *     dumperDetectCallbacks: bool,
 *     dumperLevelLimit: int,
 *     dumperItemsCountLimit: int,
 *     dumperItemSizeLimit: int,
 *     dumperDumpSizeLimit: int,
 *     detectDumpTraceAndSource: bool,
 *     dataStorage: Storage|null
 * }
 * @phpstan-type InputOptions array{
 *     enabled?: bool,
 *     classesPartialsTraceIgnore?: string[],
 *     debugTagsKeysInContext?: array<int|string>,
 *     useOwnErrorsHandler?: bool,
 *     useOwnExceptionsHandler?: bool,
 *     sourcesBasePath?: string|null,
 *     registerHelper?: bool,
 *     serverEncoding?: string|null,
 *     headersLimit?: int|null,
 *     password?: string|null,
 *     enableSslOnlyMode?: bool,
 *     ipMasks?: string[],
 *     enableEvalListener?: bool,
 *     dumperDetectCallbacks?: bool,
 *     dumperLevelLimit?: int,
 *     dumperItemsCountLimit?: int,
 *     dumperItemSizeLimit?: int,
 *     dumperDumpSizeLimit?: int,
 *     detectDumpTraceAndSource?: bool,
 *     dataStorage?: Storage|null
 * }
 *
 * @deprecated Since 2.8.0 and 3.2.0, PHPConsole is abandoned and thus we will drop this handler in Monolog 4
 */
class Php_Console_Handler extends Abstract_Processing_Handler
{
    /**
     * @phpstan-var Options
     */
    private array $options = [
        'enabled' => true,
        // bool Is PHP Console server enabled
        'classesPartialsTraceIgnore' => ['Monolog\\'],
        // array Hide calls of classes started with...
        'debugTagsKeysInContext' => [0, 'tag'],
        // bool Is PHP Console server enabled
        'useOwnErrorsHandler' => false,
        // bool Enable errors handling
        'useOwnExceptionsHandler' => false,
        // bool Enable exceptions handling
        'sourcesBasePath' => null,
        // string Base path of all project sources to strip in errors source paths
        'registerHelper' => true,
        // bool Register PhpConsole\Helper that allows short debug calls like PC::debug($var, 'ta.g.s')
        'serverEncoding' => null,
        // string|null Server internal encoding
        'headersLimit' => null,
        // int|null Set headers size limit for your web-server
        'password' => null,
        // string|null Protect PHP Console connection by password
        'enableSslOnlyMode' => false,
        // bool Force connection by SSL for clients with PHP Console installed
        'ipMasks' => [],
        // array Set IP masks of clients that will be allowed to connect to PHP Console: array('192.168.*.*', '127.0.0.1')
        'enableEvalListener' => false,
        // bool Enable eval request to be handled by eval dispatcher(if enabled, 'password' option is also required)
        'dumperDetectCallbacks' => false,
        // bool Convert callback items in dumper vars to (callback SomeClass::someMethod) strings
        'dumperLevelLimit' => 5,
        // int Maximum dumped vars array or object nested dump level
        'dumperItemsCountLimit' => 100,
        // int Maximum dumped var same level array items or object properties number
        'dumperItemSizeLimit' => 5000,
        // int Maximum length of any string or dumped array item
        'dumperDumpSizeLimit' => 500000,
        // int Maximum approximate size of dumped vars result formatted in JSON
        'detectDumpTraceAndSource' => false,
        // bool Autodetect and append trace data to debug
        'dataStorage' => null,
    ];
    private readonly Connector $connector;
    /**
     * @param  array<string, mixed> $options   See \Monolog\Handler\PHPConsoleHandler::$options for more details
     * @param  Connector|null       $connector Instance of \PhpConsole\Connector class (optional)
     * @throws \RuntimeException
     * @phpstan-param InputOptions $options
     */
    public function __construct(array $options = [], ?Connector $connector = null, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        if (!class_exists('PhpConsole\Connector')) {
            throw new \RuntimeException('PHP Console library not found. See https://github.com/barbushin/php-console#installation');
        }
        parent::__construct($level, $bubble);
        $this->options = $this->init_options($options);
        $this->connector = $this->init_connector($connector);
    }
    /**
     * @param  array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @phpstan-param InputOptions $options
     * @phpstan-return Options
     */
    private function init_options(array $options): array
    {
        $wrong_options = array_diff(array_keys($options), array_keys($this->options));
        if (\count($wrong_options) > 0) {
            throw new \RuntimeException('Unknown options: ' . implode(', ', $wrong_options));
        }
        return array_replace($this->options, $options);
    }
    private function init_connector(?Connector $connector = null): Connector
    {
        if (null === $connector) {
            if ($this->options['dataStorage'] instanceof Storage) {
                Connector::set_postpone_storage($this->options['dataStorage']);
            }
            $connector = Connector::get_instance();
        }
        if ($this->options['registerHelper'] && !Helper::is_registered()) {
            Helper::register();
        }
        if ($this->options['enabled'] && $connector->is_active_client()) {
            if ($this->options['useOwnErrorsHandler'] || $this->options['useOwnExceptionsHandler']) {
                $handler = Vendor_Php_Console_Handler::get_instance();
                $handler->set_handle_errors($this->options['useOwnErrorsHandler']);
                $handler->set_handle_exceptions($this->options['useOwnExceptionsHandler']);
                $handler->start();
            }
            if (null !== $this->options['sourcesBasePath']) {
                $connector->set_sources_base_path($this->options['sourcesBasePath']);
            }
            if (null !== $this->options['serverEncoding']) {
                $connector->set_server_encoding($this->options['serverEncoding']);
            }
            if (null !== $this->options['password']) {
                $connector->set_password($this->options['password']);
            }
            if ($this->options['enableSslOnlyMode']) {
                $connector->enable_ssl_only_mode();
            }
            if (\count($this->options['ipMasks']) > 0) {
                $connector->set_allowed_ip_masks($this->options['ipMasks']);
            }
            if (null !== $this->options['headersLimit'] && $this->options['headersLimit'] > 0) {
                $connector->set_headers_limit($this->options['headersLimit']);
            }
            if ($this->options['detectDumpTraceAndSource']) {
                $connector->get_debug_dispatcher()->detect_trace_and_source = true;
            }
            $dumper = $connector->get_dumper();
            $dumper->level_limit = $this->options['dumperLevelLimit'];
            $dumper->items_count_limit = $this->options['dumperItemsCountLimit'];
            $dumper->item_size_limit = $this->options['dumperItemSizeLimit'];
            $dumper->dump_size_limit = $this->options['dumperDumpSizeLimit'];
            $dumper->detect_callbacks = $this->options['dumperDetectCallbacks'];
            if ($this->options['enableEvalListener']) {
                $connector->start_eval_requests_listener();
            }
        }
        return $connector;
    }
    public function get_connector(): Connector
    {
        return $this->connector;
    }
    /**
     * @return array<string, mixed>
     */
    public function get_options(): array
    {
        return $this->options;
    }
    public function handle(Log_Record $record): bool
    {
        if ($this->options['enabled'] && $this->connector->is_active_client()) {
            return parent::handle($record);
        }
        return !$this->bubble;
    }
    /**
     * Writes the record down to the log of the implementing handler
     */
    protected function write(Log_Record $record): void
    {
        if ($record->level->is_lower_than(Level::Notice)) {
            $this->handle_debug_record($record);
        } elseif (isset($record->context['exception']) && $record->context['exception'] instanceof \Throwable) {
            $this->handle_exception_record($record);
        } else {
            $this->handle_error_record($record);
        }
    }
    private function handle_debug_record(Log_Record $record): void
    {
        [$tags, $filtered_context] = $this->get_record_tags($record);
        $message = $record->message;
        if (\count($filtered_context) > 0) {
            $message .= ' ' . Utils::json_encode($this->connector->get_dumper()->dump(array_filter($filtered_context)), null, true);
        }
        $this->connector->get_debug_dispatcher()->dispatch_debug($message, $tags, $this->options['classesPartialsTraceIgnore']);
    }
    private function handle_exception_record(Log_Record $record): void
    {
        $this->connector->get_errors_dispatcher()->dispatch_exception($record->context['exception']);
    }
    private function handle_error_record(Log_Record $record): void
    {
        $context = $record->context;
        $this->connector->get_errors_dispatcher()->dispatch_error($context['code'] ?? null, $context['message'] ?? $record->message, $context['file'] ?? null, $context['line'] ?? null, $this->options['classesPartialsTraceIgnore']);
    }
    /**
     * @return array{string, mixed[]}
     */
    private function get_record_tags(Log_Record $record): array
    {
        $tags = null;
        $filtered_context = [];
        if ($record->context !== []) {
            $filtered_context = $record->context;
            foreach ($this->options['debugTagsKeysInContext'] as $key) {
                if (isset($filtered_context[$key])) {
                    $tags = $filtered_context[$key];
                    if ($key === 0) {
                        array_shift($filtered_context);
                    } else {
                        unset($filtered_context[$key]);
                    }
                    break;
                }
            }
        }
        return [$tags ?? $record->level->to_psr_log_level(), $filtered_context];
    }
    /**
     * @inheritDoc
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Line_Formatter('%message%');
    }
}