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
namespace Monolog\Processor;

use ArrayAccess;
use Monolog\Log_Record;
/**
 * Injects url/method and remote IP of the current web request in all records
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Web_Processor implements Processor_Interface
{
    /**
     * @var array<string, mixed>|ArrayAccess<string, mixed>
     */
    protected array|ArrayAccess $server_data;
    /**
     * Default fields
     *
     * Array is structured as [key in record.extra => key in $serverData]
     *
     * @var array<string, string>
     */
    protected array $extra_fields = ['url' => 'REQUEST_URI', 'ip' => 'REMOTE_ADDR', 'http_method' => 'REQUEST_METHOD', 'server' => 'SERVER_NAME', 'referrer' => 'HTTP_REFERER', 'user_agent' => 'HTTP_USER_AGENT'];
    /**
     * @param array<string, mixed>|ArrayAccess<string, mixed>|null $serverData  Array or object w/ ArrayAccess that provides access to the $_SERVER data
     * @param array<string, string>|array<string>|null             $extraFields Field names and the related key inside $serverData to be added (or just a list of field names to use the default configured $serverData mapping). If not provided it defaults to: [url, ip, http_method, server, referrer] + unique_id if present in server data
     */
    public function __construct(array|ArrayAccess|null $server_data = null, array|null $extra_fields = null)
    {
        if (null === $server_data) {
            $this->server_data =& $_SERVER;
        } else {
            $this->server_data = $server_data;
        }
        $default_enabled = ['url', 'ip', 'http_method', 'server', 'referrer'];
        if (isset($this->server_data['UNIQUE_ID'])) {
            $this->extra_fields['unique_id'] = 'UNIQUE_ID';
            $default_enabled[] = 'unique_id';
        }
        if (null === $extra_fields) {
            $extra_fields = $default_enabled;
        }
        if (isset($extra_fields[0])) {
            foreach (array_keys($this->extra_fields) as $field_name) {
                if (!\in_array($field_name, $extra_fields, true)) {
                    unset($this->extra_fields[$field_name]);
                }
            }
        } else {
            $this->extra_fields = $extra_fields;
        }
    }
    /**
     * @inheritDoc
     */
    public function __invoke(Log_Record $record): Log_Record
    {
        // skip processing if for some reason request data
        // is not present (CLI or wonky SAPIs)
        if (!isset($this->server_data['REQUEST_URI'])) {
            return $record;
        }
        $record->extra = $this->append_extra_fields($record->extra);
        return $record;
    }
    /**
     * @return $this
     */
    public function add_extra_field(string $extra_name, string $server_name): self
    {
        $this->extra_fields[$extra_name] = $server_name;
        return $this;
    }
    /**
     * @param  mixed[] $extra
     * @return mixed[]
     */
    private function append_extra_fields(array $extra): array
    {
        foreach ($this->extra_fields as $extra_name => $server_name) {
            $extra[$extra_name] = $this->server_data[$server_name] ?? null;
        }
        return $extra;
    }
}