<?php

namespace App\Services;

abstract class BaseService
{
    protected $model;

    public function __construct()
    {
        $this->initializeModel();
    }

    abstract protected function initializeModel();

    public function getAll($filters = [], $relations = [])
    {
        $query = $this->model::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        foreach ($filters as $field => $value) {
            if (method_exists($this, 'filter' . ucfirst($field))) {
                $this->{'filter' . ucfirst($field)}($query, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->paginate();
    }

    public function find($id, $relations = [])
    {
        return $this->model::with($relations)->findOrFail($id);
    }

    public function create(array $data)
    {
        return $this->model::create($data);
    }

    public function update($id, array $data)
    {
        $model = $this->find($id);
        $model->update($data);
        return $model;
    }

    public function delete($id)
    {
        $model = $this->find($id);
        return $model->delete();
    }
} 