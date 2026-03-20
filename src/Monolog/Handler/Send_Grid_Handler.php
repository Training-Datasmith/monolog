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

use Monolog\Level;
use Monolog\Utils;
/**
 * SendGridHandler uses the SendGrid API v3 function to send Log emails, more information in https://www.twilio.com/docs/sendgrid/for-developers/sending-email/api-getting-started
 *
 * @author Ricardo Fontanelli <ricardo.fontanelli@hotmail.com>
 */
class Send_Grid_Handler extends Mail_Handler
{
    /**
     * The SendGrid API User
     * @deprecated this is not used anymore as of SendGrid API v3
     */
    protected string $api_user;
    /**
     * The email addresses to which the message will be sent
     * @var string[]
     */
    protected array $to;
    /**
     * @param string|null $apiUser Unused user as of SendGrid API v3, you can pass null or any string
     * @param list<string>|string $to
     * @param non-empty-string $apiHost Allows you to use another endpoint (e.g. api.eu.sendgrid.com)
     * @throws MissingExtensionException If the curl extension is missing
     */
    public function __construct(
        string|null $api_user,
        protected string $api_key,
        protected string $from,
        array|string $to,
        protected string $subject,
        int|string|Level $level = Level::Error,
        bool $bubble = true,
        /** @var non-empty-string */
        private readonly string $api_host = 'api.sendgrid.com'
    )
    {
        if (!\extension_loaded('curl')) {
            throw new Missing_Extension_Exception('The curl extension is needed to use the SendGridHandler');
        }
        $this->to = (array) $to;
        // @phpstan-ignore property.deprecated
        $this->api_user = $api_user ?? '';
        parent::__construct($level, $bubble);
    }
    protected function send(string $content, array $records): void
    {
        $body = [];
        $body['personalizations'] = [];
        $body['from']['email'] = $this->from;
        foreach ($this->to as $recipient) {
            $body['personalizations'][]['to'][]['email'] = $recipient;
        }
        $body['subject'] = $this->subject;
        if ($this->is_html_body($content)) {
            $body['content'][] = ['type' => 'text/html', 'value' => $content];
        } else {
            $body['content'][] = ['type' => 'text/plain', 'value' => $content];
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $this->api_key]);
        curl_setopt($ch, CURLOPT_URL, 'https://' . $this->api_host . '/v3/mail/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, Utils::json_encode($body));
        Curl\Util::execute($ch, 2);
    }
}