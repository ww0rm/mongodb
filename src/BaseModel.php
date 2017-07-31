<?php

namespace ww0rm\mongodb;

abstract class BaseModel
{
    /**
     * @var array Hidden system fields
     */
    const SYSTEM_FIELDS = ['_id', 'isNewRecord'];

    /**
     * @var string MongoDB id
     */
    protected $_id;

    /**
     * @var bool Is new record flag
     */
    protected $isNewRecord = true;

    /**
     * @var string Model class name
     */
    protected $className;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->_id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id)
    {
        $this->_id = $id;
    }

    /**
     * Get model collection name
     *
     * @return string
     */
    public abstract function getCollection() : string;

    /**
     * Get model attributes
     *
     * @return array
     */
    public function getAttributes() : array
    {
        $attributes = get_object_vars($this);
        $attributes['className'] = get_called_class();

        foreach ($attributes as $field => &$attribute) {
            if (in_array($field, self::SYSTEM_FIELDS, true)) {
                unset($attributes[$field]);
            } else if ($attribute instanceof BaseModel) {
                $attribute = $attribute->getAttributes();
            }
        }
        unset($attribute);

        ksort($attributes);
        return $attributes;
    }

    /**
     * Find docuemnts by query in database
     *
     * @param array $params
     * @param array $sort
     *
     * @return array
     *
     * @throws \MongoDB\Driver\Exception\InvalidArgumentException
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public static function find(array $params, array $sort = []) : array
    {
        return EntityManager::getInstance()
            ->setCollection(self::getCollection())
            ->sort($sort)
            ->find($params);
    }

    /**
     * Find one document by query in database
     *
     * @param array $params
     * @param array $sort
     *
     * @return mixed|null
     *
     * @throws \MongoDB\Driver\Exception\InvalidArgumentException
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public static function findOne(array $params, array $sort = []) : ?BaseModel
    {
        $model = EntityManager::getInstance()
            ->setCollection(static::getCollection())
            ->sort($sort)
            ->findOne($params);
        if (null !== $model) {
            $model->isNewRecord = false;
        }

        return $model;
    }

    /**
     * Find document by id in database
     *
     * @param string $id
     *
     * @return mixed|null
     *
     * @throws \MongoDB\Driver\Exception\InvalidArgumentException
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public static function findById(string $id) : ?BaseModel
    {
        $model =  EntityManager::getInstance()
            ->setCollection(static::getCollection())
            ->findById($id);
        if (null !== $model) {
            $model->isNewRecord = false;
        }

        return $model;
    }

    /**
     * Save current model to database
     *
     * @return bool
     *
     * @throws \MongoDB\Driver\Exception\InvalidArgumentException
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function save() : bool
    {
        if ($this->isNewRecord) {
            $result = EntityManager::getInstance()->insert($this);

            if (null !== $result) {
                $this->setId($result->getInsertedId());
                return true;
            }

            return false;
        }

        return null !== EntityManager::getInstance()->update($this);
    }

    /**
     * Delete current model from database
     *
     * @return bool
     *
     * @throws \MongoDB\Driver\Exception\InvalidArgumentException
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function delete() : bool
    {
        return null !== EntityManager::getInstance()->delete($this);
    }
}