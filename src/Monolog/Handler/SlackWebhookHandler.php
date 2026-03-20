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
 * Sends notifications through Slack Webhooks
 *
 * @author Haralan Dobrev <hkdobrev@gmail.com>
 * @see    https://api.slack.com/incoming-webhooks
 */
class Slack_Webhook_Handler extends Abstract_Processing_Handler
{
    /**
     * Instance of the SlackRecord util class preparing data for Slack API.
     */
    private readonly Slack_Record $slack_record;
    /**
     * @param non-empty-string $webhookUrl             Slack Webhook URL
     * @param string|null $channel                Slack channel (encoded ID or name)
     * @param string|null $username               Name of a bot
     * @param bool        $useAttachment          Whether the message should be added to Slack as attachment (plain text otherwise)
     * @param string|null $iconEmoji              The emoji name to use (or null)
     * @param bool        $useShortAttachment     Whether the the context/extra messages added to Slack as attachments are in a short style
     * @param bool        $includeContextAndExtra Whether the attachment should include context and extra data
     * @param string[]    $excludeFields          Dot separated list of fields to exclude from slack message. E.g. ['context.field1', 'extra.field2']
     *
     * @throws MissingExtensionException If the curl extension is missing
     */
    public function __construct(
        /**
         * Slack Webhook token
         */
        private readonly string $webhook_url,
        ?string $channel = null,
        ?string $username = null,
        bool $use_attachment = true,
        ?string $icon_emoji = null,
        bool $use_short_attachment = false,
        bool $include_context_and_extra = false,
        int|string|\Monolog\Level $level = Level::Critical,
        bool $bubble = true,
        array $exclude_fields = []
    )
    {
        if (!\extension_loaded('curl')) {
            throw new Missing_Extension_Exception('The curl extension is needed to use the SlackWebhookHandler');
        }
        parent::__construct($level, $bubble);
        $this->slack_record = new Slack_Record($channel, $username, $use_attachment, $icon_emoji, $use_short_attachment, $include_context_and_extra, $exclude_fields);
    }
    public function get_slack_record(): Slack_Record
    {
        return $this->slack_record;
    }
    public function get_webhook_url(): string
    {
        return $this->webhook_url;
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        $post_data = $this->slack_record->get_slack_data($record);
        $post_string = Utils::json_encode($post_data);
        $ch = curl_init();
        $options = [CURLOPT_URL => $this->webhook_url, CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-type: application/json'], CURLOPT_POSTFIELDS => $post_string];
        curl_setopt_array($ch, $options);
        Curl\Util::execute($ch);
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
}