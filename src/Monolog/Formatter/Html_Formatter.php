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
namespace Monolog\Formatter;

use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Utils;
/**
 * Formats incoming records into an HTML table
 *
 * This is especially useful for html email logging
 *
 * @author Tiago Brito <tlfbrito@gmail.com>
 */
class Html_Formatter extends Normalizer_Formatter
{
    /**
     * Translates Monolog log levels to html color priorities.
     */
    protected function get_level_color(Level $level): string
    {
        return match ($level) {
            Level::Debug => '#CCCCCC',
            Level::Info => '#28A745',
            Level::Notice => '#17A2B8',
            Level::Warning => '#FFC107',
            Level::Error => '#FD7E14',
            Level::Critical => '#DC3545',
            Level::Alert => '#821722',
            Level::Emergency => '#000000',
        };
    }
    /**
     * Creates an HTML table row
     *
     * @param string $th       Row header content
     * @param string $td       Row standard cell content
     * @param bool   $escapeTd false if td content must not be html escaped
     */
    protected function add_row(string $th, string $td = ' ', bool $escape_td = true): string
    {
        $th = htmlspecialchars($th, ENT_NOQUOTES, 'UTF-8');
        if ($escape_td) {
            $td = '<pre>' . htmlspecialchars($td, ENT_NOQUOTES, 'UTF-8') . '</pre>';
        }
        return "<tr style=\"padding: 4px;text-align: left;\">\n<th style=\"vertical-align: top;background: #ccc;color: #000\" width=\"100\">{$th}:</th>\n<td style=\"padding: 4px;text-align: left;vertical-align: top;background: #eee;color: #000\">" . $td . "</td>\n</tr>";
    }
    /**
     * Create a HTML h1 tag
     *
     * @param string $title Text to be in the h1
     */
    protected function add_title(string $title, Level $level): string
    {
        $title = htmlspecialchars($title, ENT_NOQUOTES, 'UTF-8');
        return '<h1 style="background: ' . $this->get_level_color($level) . ';color: #ffffff;padding: 5px;" class="monolog-output">' . $title . '</h1>';
    }
    /**
     * Formats a log record.
     *
     * @return string The formatted record
     */
    public function format(Log_Record $record): string
    {
        $output = $this->add_title($record->level->get_name(), $record->level);
        $output .= '<table cellspacing="1" width="100%" class="monolog-output">';
        $output .= $this->add_row('Message', $record->message);
        $output .= $this->add_row('Time', $this->format_date($record->datetime));
        $output .= $this->add_row('Channel', $record->channel);
        if (\count($record->context) > 0) {
            $embedded_table = '<table cellspacing="1" width="100%">';
            foreach ($record->context as $key => $value) {
                $embedded_table .= $this->add_row((string) $key, $this->convert_to_string($value));
            }
            $embedded_table .= '</table>';
            $output .= $this->add_row('Context', $embedded_table, false);
        }
        if (\count($record->extra) > 0) {
            $embedded_table = '<table cellspacing="1" width="100%">';
            foreach ($record->extra as $key => $value) {
                $embedded_table .= $this->add_row((string) $key, $this->convert_to_string($value));
            }
            $embedded_table .= '</table>';
            $output .= $this->add_row('Extra', $embedded_table, false);
        }
        return $output . '</table>';
    }
    /**
     * Formats a set of log records.
     *
     * @return string The formatted set of records
     */
    public function format_batch(array $records): string
    {
        $message = '';
        foreach ($records as $record) {
            $message .= $this->format($record);
        }
        return $message;
    }
    /**
     * @param mixed $data
     */
    protected function convert_to_string($data): string
    {
        if (null === $data || \is_scalar($data)) {
            return (string) $data;
        }
        $data = $this->normalize($data);
        return Utils::json_encode($data, JSON_PRETTY_PRINT | Utils::DEFAULT_JSON_FLAGS, true);
    }
}