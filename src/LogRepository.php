<?php

namespace Merkeleon\Log;

use Merkeleon\Log\Drivers\ElasticSearchLogDriver;
use Merkeleon\Log\Drivers\LogDriver;
use Merkeleon\Log\Drivers\MysqlLogDriver;
use Merkeleon\Log\Exceptions\LogException;
use Merkeleon\Log\Model\Log;

class LogRepository
{
    /** @var LogDriver */
    protected $logDriver;

    protected $logClassName;

    protected $bufferDir;

    protected $config;

    protected $logFile;

    protected $drivers = [
        'mysql'   => MysqlLogDriver::class,
        'elastic' => ElasticSearchLogDriver::class
    ];

    public function __construct($logName)
    {
        $config = config('merkeleon_log.' . $logName);

        $this->checkConfig($config);

        $this->logDriver = $this->getDriver(
            $config['driver'],
            $config['class']
        );

        $this->config = $config;

        $this->logClassName = $config['class'];

        $this->logFile = array_get($config, 'log_file');
    }

    public static function make($logName)
    {
        return new static($logName);
    }

    protected function checkConfig($config)
    {
        if (!$config)
        {
            throw new LogException('There is no merkeleon log config');
        }

        if (!is_array($config))
        {
            throw new LogException('Merkeleon log config must be array');
        }

        if (!array_key_exists('driver', $config))
        {
            throw new LogException('You should point merkeleon log driver');
        }

        if (!array_key_exists('class', $config))
        {
            throw new LogException('You should point merkeleon log class');
        }

        if (!class_exists($config['class']))
        {
            throw new LogException('There is no class ' . $config['class']);
        }

        if (!is_subclass_of($config['class'], Log::class))
        {
            throw new LogException('Merkeleon log class should extend ' . Log::class . ' class');
        }

        if (!in_array($config['driver'], array_keys($this->drivers)))
        {
            throw new LogException('Merkeleon log driver can be: ' . implode(",", $this->drivers));
        }
    }

    protected function getDriver($driverName, $logClassName)
    {
        $driverClass = $this->drivers[$driverName];

        return new $driverClass($logClassName);
    }

    public function write(array $data, bool $addToBuffer = false)
    {
        $data = array_merge($this->logClassName::getDefaultValues(), $data);

        $log = $this->logDriver->newLog($data);

        return $this->save($log, $addToBuffer);
    }

    public function save(Log $log, bool $addToBuffer = false)
    {
        $this->saveToFile($log);

        if ($addToBuffer)
        {
            return $this->saveToBuffer($log);
        }

        return $this->logDriver->save($log);
    }

    protected function saveToFile(Log $log)
    {
        if (!$this->logFile)
        {
            return;
        }

        file_put_contents(
            $this->logFile,
            json_encode($log->toLogFileArray()) . "\n",
            FILE_APPEND
        );
    }

    protected function saveToBuffer(Log $log)
    {
        $bufferFile = $this->getBufferFile();

        $values = $this->logDriver->prepareValues($log);

        file_put_contents(
            $bufferFile,
            json_encode($values) . "\n",
            FILE_APPEND
        );
    }

    public function flushBuffer()
    {
        $bufferFile = $this->getBufferFile();

        if (!is_file($bufferFile))
        {
            return;
        }

        if (!is_readable($bufferFile))
        {
            throw new LogException('The buffer file' . $bufferFile . ' is not readable');
        }

        $tmpBufferFile = $this->getTmpBufferFile();

        rename($bufferFile, $tmpBufferFile);

        $handle = fopen($tmpBufferFile, "r");

        if (!$handle)
        {
            return;
        }

        $i      = 0;
        $params = [];

        while (!feof($handle))
        {
            $line = fgets($handle);
            $row  = json_decode($line, true);
            if (!$row)
            {
                continue;
            }
            $params[] = $row;

            $i++;

            if ($i % 1000 == 0)
            {
                $this->writeFromBuffer($params);
                $params = [];
                $i      = 0;
            }
        }

        if ($params)
        {
            $this->writeFromBuffer($params);
        }

        fclose($handle);

        unlink($tmpBufferFile);
    }

    protected function getBufferDir()
    {
        if (!$this->bufferDir)
        {
            $this->bufferDir = array_get($this->config, 'buffer_dir');
        }

        if (!$this->bufferDir || !is_dir($this->bufferDir))
        {
            throw new LogException('The buffer dir' . $this->bufferDir . ' or is not a dir');
        }

        return $this->bufferDir;
    }

    protected function getBufferFile()
    {
        $bufferDir = $this->getBufferDir();

        return $bufferDir . '/buffer_' . $this->logClassName::getTableName() . '.log';
    }

    protected function getTmpBufferFile()
    {
        $bufferDir = $this->getBufferDir();

        return $bufferDir . '/tmp_buffer_' . $this->logClassName::getTableName() . '_' . time() . '.log';
    }


    public function writeFromBuffer(array $data)
    {
        return $this->logDriver->bulkSaveToDb($data);
    }

    public function __call($name, $arguments)
    {
        if (!is_callable([$this->logDriver, $name]))
        {
            throw new LogException('method ' . $name . ' doesn\'t exists');
        }

        return $this->logDriver->$name(...$arguments);
    }

    public function getDateFormat()
    {
        return $this->logClassName::$dateTimeFormat;
    }

    public function with($arguments)
    {
        $this->logDriver->with($arguments);

        return $this;
    }
}