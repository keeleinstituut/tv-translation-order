<?php

namespace App\Traits;

use App\Exceptions\ReadOnlyException;
use Illuminate\Database\Eloquent\Builder;

trait HasReadonlyAccess
{
    /**
     * @throws ReadOnlyException
     */
    public static function create()
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public static function forceCreate()
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public function save(array $options = [])
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public function update(array $attributes = [], array $options = [])
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public static function firstOrCreate(array $attributes, array $values = [])
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public static function firstOrNew(array $attributes, array $values = [])
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public static function updateOrCreate(array $attributes, array $values = [])
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public function delete()
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public static function destroy($ids)
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public function restore()
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public function forceDelete()
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public function performDeleteOnModel()
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public function push()
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public function finishSave(array $options)
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public function performUpdate(Builder $query, array $options = []): bool
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public function touch($attribute = null)
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public function insert()
    {
        throw new ReadOnlyException();
    }

    /**
     * @throws ReadOnlyException
     */
    public function truncate()
    {
        throw new ReadOnlyException();
    }
}
