<?php

namespace SilverStripe\Cow\Utility;

use Symfony\Component\Console\Output\OutputInterface;

class Logger
{
    public static function log(OutputInterface $output, $message, $messagePrefix = '', $format = '')
    {
        $text = '';
        if ($messagePrefix) {
            $text = "<bold>[{$messagePrefix}]</bold> ";
        }
        if ($format) {
            $text .= "<{$format}>{$message}</{$format}>";
        } else {
            $text .= $message;
        }
        $output->writeln($text);
    }
}
