<?php

namespace Grooveland\Core\Console;

use Illuminate\Support\Arr;
use Illuminate\Console\Parser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command as IlluminateCommand;

class Command extends IlluminateCommand
{
    const COLOR_BLACK = 'Black';
    const COLOR_RED = 'Red';
    const COLOR_GREEN = 'Green';
    const COLOR_YELLOW = 'Yellow';
    const COLOR_BLUE = 'Blue';
    const COLOR_MAGENTA = 'Magenta';
    const COLOR_CYAN = 'Cyan';
    const COLOR_WHITE = 'White';
    const COLOR_DEFAULT = 'Default';

    const INFO_MSSG = 'info';
    const WARNING_MSSG = 'warning';
    const ERROR_MSSG = 'error';

    protected $logChannel = 'commands';
    protected $defaultParams = '{ --l|log : Display output in log file }';

    public function __construct()
    {
        parent::__construct();
        $this->addDefaultParams();
    }

    public function clear()
    {
        $clear = 'clear';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $clear = 'cls';
        }
        system($clear);
    }

    public function message($message, string $type = 'info', bool $force = false)
    {
        if (!method_exists($this, $type)) {
            $type = static::INFO_MSSG;
        }

        if ($this->option('verbose') || $force) {
            $this->$type($message);
        }
        $this->log($message, $type, $force);
    }

    public function log(string $message, string $type = 'info', bool $force = false)
    {
        $logger = Log::channel($this->logChannel);

        if (!method_exists($logger, $type)) {
            $type = static::INFO_MSSG;
        }

        if ($this->option('verbose') || $force) {
            $logger->$type($message);
        }
    }

    public function style(string $string, string $fg = self::DEFAULT, string $bg = self::DEFAULT, array $options = [])
    {
        return $this->stylizedOut($string, ['fg' => $fg, 'bg' => $bg, 'options' => $options]);
    }

    public function output($text, array $options = [])
    {
        $_text;

        if (is_array($text)) {
            $_text = [];
            foreach ($text as $string => $options) {
                array_push($_text, $this->stylizedOut($string, $options));
            }
            $_text = implode('', $_text);
        } else {
            $_text = $this->stylizedOut($text, $options);
        }

        $this->line("$_text</>");
    }

    public function form(string $text, array $params = [])
    {
        $type = Arr::get($params, 'type', 'text');
        $options = Arr::get($params, 'options', '');
        $value = Arr::get($params, 'value', null);
        $fields = null;

        if (!is_null($value) && !$this->confirm("The field {$text} has the value: {$value}, Do yo wish to change?")) {
            return $value;
        }

        if ($type === 'text') {
            if ($options === "secret") {
                $field = $this->secret($text);
            } else {
                $field = $this->ask($text);
            }
        } elseif ($type === 'choice') {
            $field = $this->choice($text, explode('|', $options));
        } elseif ($type === "artisan") {
            $command = $options;
            if (is_Array($options) && array_key_exists('command', $options)) {
                $command = $options['command'];
            }
            Artisan::call($command);
            $result = Artisan::output();

            if (is_Array($options) && array_key_exists('regex', $options)) {
                $matches = [];
                preg_match($options['regex'], $result, $matches);
                if (count($matches) > 0) {
                    $result = $matches[1];
                }
            }

            $field = preg_replace('/([\r\n\t])/', '', $result);
        }

        return $field;
    }

    protected function warning(string $message)
    {
        $this->output($message, [
            'fg' => static::COLOR_YELLOW
        ]);
    }

    protected function addDefaultPArams()
    {
        [$name, $arguments, $options] = Parser::parse("{$this->name} {$this->defaultParams}");
        $this->getDefinition()->addArguments($arguments);
        $this->getDefinition()->addOptions($options);
    }

    private function stylizedOut(string $text, array $options)
    {
        $style = '';
        $addOptions = 'options=%s';

        if (array_key_exists('fg', $options)) {
            $style .= ($style !== '' ? ";" : $style) . "fg=" . $options['fg'];
        }

        if (array_key_exists('bg', $options)) {
            $style .= ($style !== '' ? ";" : $style) . "bg=" . $options['bg'];
        }

        if (array_key_exists('options', $options) && length($options['options']) > 0) {
            $_options = (is_array($options['options'])) ? implode(',', $options['options']) : $options['options'];
            $addOptions = sprintf($addOptions, $_options);
            $style .= ($style !== '' ? ";" : $style) . $addOptions;
        }

        if ($style !== '') {
            $style = "<$style>";
        }

        return "$style$text</>";
    }
}
