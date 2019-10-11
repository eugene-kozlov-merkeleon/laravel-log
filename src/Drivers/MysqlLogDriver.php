<?php


namespace Merkeleon\Log\Drivers;

use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Merkeleon\Log\Exceptions\LogException;

class MysqlLogDriver extends LogDriver
{
    protected $query;
    protected $collectionCallbacks = [];

    protected function saveToDb($row)
    {
        unset($row['id']);

        $lastInsertId = DB::table($this->getTableName())
                          ->insertGetId($row);

        return $lastInsertId;

    }

    public function query()
    {
        if (!$this->query)
        {
            $this->query = DB::table($this->getTableName());
        }

        return $this->query;
    }

    public function paginate($perPage)
    {
        $page = Paginator::resolveCurrentPage();

        $total = $this->getTotal();

        $results = $total ? $this->forPage($page, $perPage)
                                 ->get(['*']) : collect();

        foreach ($this->collectionCallbacks as $callback)
        {
            $callback($results);
        }

        return $this->paginator($results, $total, $perPage, $page, [
            'path'     => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    public function getTotal()
    {
        $query = clone($this->query());

        $row = $query->addSelect(DB::raw('COUNT(id) as total'))
                     ->first();

        return $row->total;
    }

    public function find($id)
    {
        $row = $this->query()
                    ->find($id);

        if ($row)
        {
            return $this->prepareLog((array) $row);
        }

        return null;
    }

    public function get()
    {
        return $this->query()
                    ->get()
                    ->map(function ($item) {
                        return $this->prepareLog((array)$item);
                    });

    }

    public function first()
    {
        $row = $this->query()
                           ->first();
        if ($row)
        {
            return $this->prepareLog((array) $row);
        }

        return null;
    }

    public function firstOrFail()
    {
        if ($first = $this->first())
        {
            return $first;
        }

        throw new LogException('Model not found');

    }

    public function chunkById($count, callable $callback)
    {
        $newCallback = function ($rows) use ($callback) {
            $rows = $rows->map(function ($item) {
                return $this->prepareLog((array)$item);
            });

            $callback($rows);
        };

        return $this->query()
                    ->chunkById($count, $newCallback);
    }

    public function range($name, $from, $to)
    {
        if ($from)
        {
            $this->query()
                 ->where($name, '>=', $from);
        }

        if ($to)
        {
            $this->query()
                 ->where($name, '<=', $to);
        }

        return $this;
    }

    public function matchSubString($name, $value, $searchInObject)
    {
        $this->query()
             ->where($name, 'like', '%' . $value . '%');

        return $this;
    }

    public function __call($name, $arguments)
    {
        if (!is_callable([$this->query(), $name]))
        {
            throw  new LogException('Method' . $name . ' doesn\'t exists in LogDriver');
        }

        $this->query()
             ->$name(...$arguments);

        return $this;
    }

    public function whereOr(array $conditions)
    {
        $this->query()
             ->where(function ($query) use ($conditions) {
                 $firstKey   = array_first(array_keys($conditions));
                 $firstValue = array_shift($conditions);
                 $query->where($firstKey, $firstValue);

                 foreach ($conditions as $key => $value)
                 {
                     $query->orWhere($key, $value);
                 }
             });

        return $this;
    }

    protected function prepareLog($row)
    {
        return $this->newLog($row);
    }

    protected function fromCastArray(array $value)
    {
        $value = $this->asJson($value);

        if ($value === false)
        {
            throw JsonEncodingException::forAttribute(
                $this, $key, json_last_error_msg()
            );
        }

        return $value;
    }


    public function addCollectionCallbacks(array $collectionCallbacks)
    {
        $this->collectionCallbacks = $collectionCallbacks;

        return $this;
    }
}