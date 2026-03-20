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
namespace Monolog\Handler\Slack;

use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Normalizer_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Utils;
/**
 * Slack record utility helping to log to Slack webhooks or API.
 *
 * @author Greg Kedzierski <greg@gregkedzierski.com>
 * @author Haralan Dobrev <hkdobrev@gmail.com>
 * @see    https://api.slack.com/incoming-webhooks
 * @see    https://api.slack.com/docs/message-attachments
 */
class Slack_Record
{
    public const COLOR_DANGER = 'danger';
    public const COLOR_WARNING = 'warning';
    public const COLOR_GOOD = 'good';
    public const COLOR_DEFAULT = '#e3e4e6';
    /**
     * Slack channel (encoded ID or name)
     */
    private string|null $channel = null;
    /**
     * Name of a bot
     */
    private string|null $username = null;
    /**
     * User icon e.g. 'ghost', 'http://example.com/user.png'
     */
    private string|null $user_icon = null;
    /**
     * Whether the message should be added to Slack as attachment (plain text otherwise)
     */
    private bool $use_attachment;
    /**
     * Whether the the context/extra messages added to Slack as attachments are in a short style
     */
    private bool $use_short_attachment;
    /**
     * Whether the attachment should include context and extra data
     */
    private bool $include_context_and_extra;
    /**
     * Dot separated list of fields to exclude from slack message. E.g. ['context.field1', 'extra.field2']
     * @var string[]
     */
    private array $exclude_fields;
    private Formatter_Interface|null $formatter;
    private Normalizer_Formatter $normalizer_formatter;
    /**
     * @param string[] $excludeFields
     */
    public function __construct(?string $channel = null, ?string $username = null, bool $use_attachment = true, ?string $user_icon = null, bool $use_short_attachment = false, bool $include_context_and_extra = false, array $exclude_fields = [], Formatter_Interface|null $formatter = null)
    {
        $this->set_channel($channel)->set_username($username)->use_attachment($use_attachment)->set_user_icon($user_icon)->use_short_attachment($use_short_attachment)->include_context_and_extra($include_context_and_extra)->exclude_fields($exclude_fields)->set_formatter($formatter);
        if ($this->include_context_and_extra) {
            $this->normalizer_formatter = new Normalizer_Formatter();
        }
    }
    /**
     * Returns required data in format that Slack
     * is expecting.
     *
     * @phpstan-return mixed[]
     */
    public function get_slack_data(Log_Record $record): array
    {
        $data_array = [];
        if ($this->username !== null) {
            $data_array['username'] = $this->username;
        }
        if ($this->channel !== null) {
            $data_array['channel'] = $this->channel;
        }
        if ($this->formatter !== null && !$this->use_attachment) {
            $message = $this->formatter->format($record);
        } else {
            $message = $record->message;
        }
        $record_data = $this->remove_excluded_fields($record);
        if ($this->use_attachment) {
            $attachment = ['fallback' => $message, 'text' => $message, 'color' => $this->get_attachment_color($record->level), 'fields' => [], 'mrkdwn_in' => ['fields'], 'ts' => $record_data['datetime']->get_timestamp(), 'footer' => $this->username, 'footer_icon' => $this->user_icon];
            if ($this->use_short_attachment) {
                $attachment['title'] = $record_data['level_name'];
            } else {
                $attachment['title'] = 'Message';
                $attachment['fields'][] = $this->generate_attachment_field('Level', $record_data['level_name']);
            }
            if ($this->include_context_and_extra) {
                foreach (['extra', 'context'] as $key) {
                    if (!isset($record_data[$key])) {
                        continue;
                    }
                    if (\count($record_data[$key]) === 0) {
                        continue;
                    }
                    if ($this->use_short_attachment) {
                        $attachment['fields'][] = $this->generate_attachment_field($key, $record_data[$key]);
                    } else {
                        // Add all extra fields as individual fields in attachment
                        $attachment['fields'] = array_merge($attachment['fields'], $this->generate_attachment_fields($record_data[$key]));
                    }
                }
            }
            $data_array['attachments'] = [$attachment];
        } else {
            $data_array['text'] = $message;
        }
        if ($this->user_icon !== null) {
            if (false !== $icon_url = filter_var($this->user_icon, FILTER_VALIDATE_URL)) {
                $data_array['icon_url'] = $icon_url;
            } else {
                $data_array['icon_emoji'] = ":{$this->user_icon}:";
            }
        }
        return $data_array;
    }
    /**
     * Returns a Slack message attachment color associated with
     * provided level.
     */
    public function get_attachment_color(Level $level): string
    {
        return match ($level) {
            Level::Error, Level::Critical, Level::Alert, Level::Emergency => static::COLOR_DANGER,
            Level::Warning => static::COLOR_WARNING,
            Level::Info, Level::Notice => static::COLOR_GOOD,
            Level::Debug => static::COLOR_DEFAULT,
        };
    }
    /**
     * Stringifies an array of key/value pairs to be used in attachment fields
     *
     * @param mixed[] $fields
     */
    public function stringify(array $fields): string
    {
        /** @var array<array<mixed>|bool|float|int|string|null> $normalized */
        $normalized = $this->normalizer_formatter->normalize_value($fields);
        $has_second_dimension = \count(array_filter($normalized, is_array(...))) > 0;
        $has_only_non_numeric_keys = \count(array_filter(array_keys($normalized), is_numeric(...))) === 0;
        return $has_second_dimension || $has_only_non_numeric_keys ? Utils::json_encode($normalized, JSON_PRETTY_PRINT | Utils::DEFAULT_JSON_FLAGS) : Utils::json_encode($normalized, Utils::DEFAULT_JSON_FLAGS);
    }
    /**
     * Channel used by the bot when posting
     *
     * @return $this
     */
    public function set_channel(?string $channel = null): self
    {
        $this->channel = $channel;
        return $this;
    }
    /**
     * Username used by the bot when posting
     *
     * @return $this
     */
    public function set_username(?string $username = null): self
    {
        $this->username = $username;
        return $this;
    }
    /**
     * @return $this
     */
    public function use_attachment(bool $use_attachment = true): self
    {
        $this->use_attachment = $use_attachment;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_user_icon(?string $user_icon = null): self
    {
        $this->user_icon = $user_icon;
        if (\is_string($user_icon)) {
            $this->user_icon = trim($user_icon, ':');
        }
        return $this;
    }
    /**
     * @return $this
     */
    public function use_short_attachment(bool $use_short_attachment = false): self
    {
        $this->use_short_attachment = $use_short_attachment;
        return $this;
    }
    /**
     * @return $this
     */
    public function include_context_and_extra(bool $include_context_and_extra = false): self
    {
        $this->include_context_and_extra = $include_context_and_extra;
        if ($this->include_context_and_extra) {
            $this->normalizer_formatter = new Normalizer_Formatter();
        }
        return $this;
    }
    /**
     * @param  string[] $excludeFields
     * @return $this
     */
    public function exclude_fields(array $exclude_fields = []): self
    {
        $this->exclude_fields = $exclude_fields;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_formatter(?Formatter_Interface $formatter = null): self
    {
        $this->formatter = $formatter;
        return $this;
    }
    /**
     * Generates attachment field
     *
     * @param string|mixed[] $value
     *
     * @return array{title: string, value: string, short: false}
     */
    private function generate_attachment_field(string $title, $value): array
    {
        $value = \is_array($value) ? sprintf('```%s```', substr($this->stringify($value), 0, 1990)) : $value;
        return ['title' => ucfirst($title), 'value' => $value, 'short' => false];
    }
    /**
     * Generates a collection of attachment fields from array
     *
     * @param mixed[] $data
     *
     * @return array<array{title: string, value: string, short: false}>
     */
    private function generate_attachment_fields(array $data): array
    {
        /** @var array<array<mixed>|string> $normalized */
        $normalized = $this->normalizer_formatter->normalize_value($data);
        $fields = [];
        foreach ($normalized as $key => $value) {
            $fields[] = $this->generate_attachment_field((string) $key, $value);
        }
        return $fields;
    }
    /**
     * Get a copy of record with fields excluded according to $this->excludeFields
     *
     * @return mixed[]
     */
    private function remove_excluded_fields(Log_Record $record): array
    {
        $record_data = $record->to_array();
        foreach ($this->exclude_fields as $field) {
            $keys = explode('.', $field);
            $node =& $record_data;
            $last_key = end($keys);
            foreach ($keys as $key) {
                if (!isset($node[$key])) {
                    break;
                }
                if ($last_key === $key) {
                    unset($node[$key]);
                    break;
                }
                $node =& $node[$key];
            }
        }
        return $record_data;
    }
}