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
use Monolog\Handler\Slack\Slack_Record;
use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Utils;
/**
 * Sends notifications through Slack API
 *
 * @author Greg Kedzierski <greg@gregkedzierski.com>
 * @see    https://api.slack.com/
 */
class Slack_Handler extends Socket_Handler
{
    /**
     * Instance of the SlackRecord util class preparing data for Slack API.
     */
    private readonly Slack_Record $slack_record;
    /**
     * @param  string                    $token                  Slack API token
     * @param  string                    $channel                Slack channel (encoded ID or name)
     * @param  string|null               $username               Name of a bot
     * @param  bool                      $useAttachment          Whether the message should be added to Slack as attachment (plain text otherwise)
     * @param  string|null               $iconEmoji              The emoji name to use (or null)
     * @param  bool                      $useShortAttachment     Whether the context/extra messages added to Slack as attachments are in a short style
     * @param  bool                      $includeContextAndExtra Whether the attachment should include context and extra data
     * @param  string[]                  $excludeFields          Dot separated list of fields to exclude from slack message. E.g. ['context.field1', 'extra.field2']
     * @throws MissingExtensionException If no OpenSSL PHP extension configured
     */
    public function __construct(private readonly string $token, string $channel, ?string $username = null, bool $use_attachment = true, ?string $icon_emoji = null, $level = Level::Critical, bool $bubble = true, bool $use_short_attachment = false, bool $include_context_and_extra = false, array $exclude_fields = [], bool $persistent = false, float $timeout = 0.0, float $writing_timeout = 10.0, ?float $connection_timeout = null, ?int $chunk_size = null)
    {
        if (!\extension_loaded('openssl')) {
            throw new Missing_Extension_Exception('The OpenSSL PHP extension is required to use the SlackHandler');
        }
        parent::__construct('ssl://slack.com:443', $level, $bubble, $persistent, $timeout, $writing_timeout, $connection_timeout, $chunk_size);
        $this->slack_record = new Slack_Record($channel, $username, $use_attachment, $icon_emoji, $use_short_attachment, $include_context_and_extra, $exclude_fields);
    }
    public function get_slack_record(): Slack_Record
    {
        return $this->slack_record;
    }
    public function get_token(): string
    {
        return $this->token;
    }
    /**
     * @inheritDoc
     */
    protected function generate_data_stream(Log_Record $record): string
    {
        $content = $this->build_content($record);
        return $this->build_header($content) . $content;
    }
    /**
     * Builds the body of API call
     */
    private function build_content(Log_Record $record): string
    {
        $data_array = $this->prepare_content_data($record);
        return http_build_query($data_array);
    }
    /**
     * @return string[]
     */
    protected function prepare_content_data(Log_Record $record): array
    {
        $data_array = $this->slack_record->get_slack_data($record);
        $data_array['token'] = $this->token;
        if (isset($data_array['attachments']) && \is_array($data_array['attachments']) && \count($data_array['attachments']) > 0) {
            $data_array['attachments'] = Utils::json_encode($data_array['attachments']);
        }
        return $data_array;
    }
    /**
     * Builds the header of the API Call
     */
    private function build_header(string $content): string
    {
        $header = "POST /api/chat.postMessage HTTP/1.1\r\n";
        $header .= "Host: slack.com\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= 'Content-Length: ' . \strlen($content) . "\r\n";
        return $header . "\r\n";
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        parent::write($record);
        $this->finalize_write();
    }
    /**
     * Finalizes the request by reading some bytes and then closing the socket
     *
     * If we do not read some but close the socket too early, slack sometimes
     * drops the request entirely.
     */
    protected function finalize_write(): void
    {
        $res = $this->get_resource();
        if (\is_resource($res)) {
            @fread($res, 2048);
        }
        $this->close_socket();
    }
    public function set_formatter(Formatter_Interface $formatter): Handler_Interface
    {
        parent::set_formatter($formatter);
        $this->slack_record->set_formatter($formatter);
        return $this;
    }
    public function get_formatter(): Formatter_Interface
    {
        $formatter = parent::get_formatter();
        $this->slack_record->set_formatter($formatter);
        return $formatter;
    }
    /**
     * Channel used by the bot when posting
     *
     * @return $this
     */
    public function set_channel(string $channel): self
    {
        $this->slack_record->set_channel($channel);
        return $this;
    }
    /**
     * Username used by the bot when posting
     *
     * @return $this
     */
    public function set_username(string $username): self
    {
        $this->slack_record->set_username($username);
        return $this;
    }
    /**
     * @return $this
     */
    public function use_attachment(bool $use_attachment): self
    {
        $this->slack_record->use_attachment($use_attachment);
        return $this;
    }
    /**
     * @return $this
     */
    public function set_icon_emoji(string $icon_emoji): self
    {
        $this->slack_record->set_user_icon($icon_emoji);
        return $this;
    }
    /**
     * @return $this
     */
    public function use_short_attachment(bool $use_short_attachment): self
    {
        $this->slack_record->use_short_attachment($use_short_attachment);
        return $this;
    }
    /**
     * @return $this
     */
    public function include_context_and_extra(bool $include_context_and_extra): self
    {
        $this->slack_record->include_context_and_extra($include_context_and_extra);
        return $this;
    }
    /**
     * @param  string[] $excludeFields
     * @return $this
     */
    public function exclude_fields(array $exclude_fields): self
    {
        $this->slack_record->exclude_fields($exclude_fields);
        return $this;
    }
}