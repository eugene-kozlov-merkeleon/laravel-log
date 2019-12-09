<?php


namespace Merkeleon\Log\Drivers;

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Merkeleon\Log\Exceptions\LogException;
use Merkeleon\Log\Model\Log;
use Ramsey\Uuid\Uuid;


abstract class LogDriver
{
    protected $logClassName;
    protected $logFile;
    protected $collectionCallbacks;

    abstract protected function saveToDb($row);

    public function __construct($logClassName, $logFile = null)
    {
        $this->logClassName = $logClassName;
        $this->logFile      = $logFile;
    }

    protected function getTableName()
    {
        return $this->logClassName::getTableName();
    }

    protected function paginator($items, $total, $perPage, $currentPage, $options)
    {
        return Container::getInstance()
                        ->makeWith(LengthAwarePaginator::class, compact(
                            'items', 'total', 'perPage', 'currentPage', 'options'
                        ));
    }

    protected function prepareValues(Log $log)
    {
        $values = $log->getValues();

        $attributes = $log::getAttributesWithCasts();

        foreach ($attributes as $key => $cast)
        {
            if ($value = $this->prapareValue($key, array_get($values, $key), $cast))
            {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    protected function prapareValue($key, $value, $cast)
    {
        $prepareValueMethodName = studly_method_name('from_cast_' . $cast);

        if (method_exists($this, $prepareValueMethodName))
        {
            return $this->$prepareValueMethodName($value);
        }

        return $value;
    }

    protected function fromCastDatetime($value = null)
    {
        if (is_null($value))
        {
            return null;
        }

        return $value->timezone(config('app.timezone'))
                     ->format($this->logClassName::$dateTimeFormat);
    }


    protected function asJson($value)
    {
        return json_encode($value);
    }

    public function makeLog($data)
    {
        $log = $this->newLog($data);

        return $this->save($log);
    }

    public function save(Log $log)
    {
        $this->saveToFile($log);
        $values = $this->prepareValues($log);

        return  $this->saveToDb($values);
    }

    public function newLog(array $data)
    {
        $data = $this->prepareData($data);

        return new $this->logClassName($data);
    }

    protected function prepareData(array $data)
    {
        $data = array_intersect_key($data, array_flip($this->logClassName::getAttributes()));

        return array_filter(
            $this->castData($data),
            function ($value) {
                return !is_null($value);
            }
        );
    }

    protected function castData(array $data)
    {
        $results = [];

        foreach ($data as $key => $value)
        {
            $results[$key] = $this->prepareCast($key, $value);
        }

        return $results;
    }

    protected function prepareCast($key, $value)
    {
        $casts = $this->logClassName::getAttributesWithCasts();

        if (is_null($value) || !array_key_exists($key, $casts))
        {
            return $value;
        }

        $cast = $casts[$key];

        $castMethod = studly_method_name('cast_' . $cast);

        if (method_exists($this, $castMethod))
        {
            return $this->$castMethod($value);
        }

        return $value;
    }

    protected function castInt($value)
    {
        return (int)$value;
    }

    protected function castFloat($value)
    {
        return (float)$value;
    }

    protected function castString($value)
    {
        return (string)$value;
    }

    protected function castBool($value)
    {
        return (bool)$value;
    }

    protected function castArray($value)
    {
        if (is_array($value))
        {
            return $value;
        }

        return json_decode($value, true);
    }

    protected function castJson($value)
    {
        return json_decode($value, true);
    }

    protected function castDatetime($value)
    {
        return $this->asDateTime($value);
    }

    protected function castUuid($value)
    {
        return (string)$value;
    }

    protected function asDateTime($value)
    {
        if ($value instanceof Carbon)
        {
            return $value;
        }

        if ($value instanceof \DateTimeInterface)
        {
            return new Carbon(
                $value->format('Y-m-d H:i:s.u'), $value->getTimezone()
            );
        }

        if (is_numeric($value))
        {
            return Carbon::createFromTimestamp($value);
        }

        if ($this->isStandardDateFormat($value))
        {
            return Carbon::createFromFormat('Y-m-d', $value)
                         ->startOfDay();
        }

        return Carbon::createFromFormat(
            $this->logClassName::$dateTimeFormat, $value
        );
    }

    protected function isStandardDateFormat($value)
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
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

    public function with($arguments)
    {
        if (!is_array($arguments))
        {
            $arguments = [$arguments];
        }

        $relations = array_intersect_key(
            $this->logClassName::getRelations(),
            array_flip($arguments)
        );

        $callbacks = $this->prepareCollectionCallback($relations);

        $this->addCollectionCallbacks($callbacks);

        return $this;
    }

    protected function prepareCollectionCallback($relations)
    {
        $callbacks = [];
        foreach ($relations as $relationKey => $relation)
        {
            $method = studly_method_name('prepare_' . $relation['type'] . '_relation');
            if (!method_exists($this, $method))
            {
                throw new LogException('There is no method ' . $method);
            }

            $callbacks[] = $this->$method($relationKey, $relation);
        }

        return $callbacks;
    }

    protected function prepareOneRelation($relationKey, $relation)
    {
        return
            function (Collection $collection) use ($relationKey, $relation) {
                $foreignIds = $collection->map(function ($item) use ($relation) {
                    return $item->{$relation['foreign_id']};
                })->toArray();

                $relationList = $relation['class']::whereIn($relation['local_id'], $foreignIds)
                                                  ->get()
                                                  ->keyBy($relation['local_id']);

                $collection->each(
                    function ($item) use ($relation, $relationKey, $relationList) {
                        if (optional($item)->{$relation['foreign_id']})
                        {
                            $item->setAttribute($relationKey, $relationList->get($item->{$relation['foreign_id']}));
                        }
                    }
                );
            };
    }
}
