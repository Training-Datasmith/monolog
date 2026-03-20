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
use Monolog\Formatter\Normalizer_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Utils;
/**
 * Class to record a log on a NewRelic application.
 * Enabling New Relic High Security mode may prevent capture of useful information.
 *
 * This handler requires a NormalizerFormatter to function and expects an array in $record->formatted
 *
 * @see https://docs.newrelic.com/docs/agents/php-agent
 * @see https://docs.newrelic.com/docs/accounts-partnerships/accounts/security/high-security
 */
class New_Relic_Handler extends Abstract_Processing_Handler
{
    /**
     * @inheritDoc
     */
    public function __construct(
        int|string|Level $level = Level::Error,
        bool $bubble = true,
        /**
         * Name of the New Relic application that will receive logs from this handler.
         */
        protected string|null $app_name = null,
        /**
         * Some context and extra data is passed into the handler as arrays of values. Do we send them as is
         * (useful if we are using the API), or explode them for display on the NewRelic RPM website?
         */
        protected bool $explode_arrays = false,
        /**
         * Name of the current transaction
         */
        protected string|null $transaction_name = null
    )
    {
        parent::__construct($level, $bubble);
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        if (!$this->is_new_relic_enabled()) {
            throw new Missing_Extension_Exception('The newrelic PHP extension is required to use the NewRelicHandler');
        }
        if (null !== $app_name = $this->get_app_name($record->context)) {
            $this->set_new_relic_app_name($app_name);
        }
        if (null !== $transaction_name = $this->get_transaction_name($record->context)) {
            $this->set_new_relic_transaction_name($transaction_name);
            unset($record->formatted['context']['transaction_name']);
        }
        if (isset($record->context['exception']) && $record->context['exception'] instanceof \Throwable) {
            newrelic_notice_error($record->message, $record->context['exception']);
            unset($record->formatted['context']['exception']);
        } else {
            newrelic_notice_error($record->message);
        }
        if (isset($record->formatted['context']) && \is_array($record->formatted['context'])) {
            foreach ($record->formatted['context'] as $key => $parameter) {
                if (\is_array($parameter) && $this->explode_arrays) {
                    foreach ($parameter as $param_key => $param_value) {
                        $this->set_new_relic_parameter('context_' . $key . '_' . $param_key, $param_value);
                    }
                } else {
                    $this->set_new_relic_parameter('context_' . $key, $parameter);
                }
            }
        }
        if (isset($record->formatted['extra']) && \is_array($record->formatted['extra'])) {
            foreach ($record->formatted['extra'] as $key => $parameter) {
                if (\is_array($parameter) && $this->explode_arrays) {
                    foreach ($parameter as $param_key => $param_value) {
                        $this->set_new_relic_parameter('extra_' . $key . '_' . $param_key, $param_value);
                    }
                } else {
                    $this->set_new_relic_parameter('extra_' . $key, $parameter);
                }
            }
        }
    }
    /**
     * Checks whether the NewRelic extension is enabled in the system.
     */
    protected function is_new_relic_enabled(): bool
    {
        return \extension_loaded('newrelic');
    }
    /**
     * Returns the appname where this log should be sent. Each log can override the default appname, set in this
     * handler's constructor, by providing the appname in it's context.
     *
     * @param mixed[] $context
     */
    protected function get_app_name(array $context): ?string
    {
        return $context['appname'] ?? $this->app_name;
    }
    /**
     * Returns the name of the current transaction. Each log can override the default transaction name, set in this
     * handler's constructor, by providing the transaction_name in it's context
     *
     * @param mixed[] $context
     */
    protected function get_transaction_name(array $context): ?string
    {
        return $context['transaction_name'] ?? $this->transaction_name;
    }
    /**
     * Sets the NewRelic application that should receive this log.
     */
    protected function set_new_relic_app_name(string $app_name): void
    {
        newrelic_set_appname($app_name);
    }
    /**
     * Overwrites the name of the current transaction
     */
    protected function set_new_relic_transaction_name(string $transaction_name): void
    {
        newrelic_name_transaction($transaction_name);
    }
    /**
     * @param mixed $value
     */
    protected function set_new_relic_parameter(string $key, $value): void
    {
        if (null === $value || \is_scalar($value)) {
            newrelic_add_custom_parameter($key, $value);
        } else {
            newrelic_add_custom_parameter($key, Utils::json_encode($value, null, true));
        }
    }
    /**
     * @inheritDoc
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Normalizer_Formatter();
    }
}