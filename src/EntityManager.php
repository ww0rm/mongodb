<?php

namespace ww0rm\mongodb;

use MongoDB\BSON\ObjectID;
use MongoDB\Client;
use MongoDB\Model\BSONDocument;

class EntityManager
{
    /**
     * @const int Default limit for find queries
     */
    const DEFAULT_LIMIT = 20;

    /**
     * @var EntityManager instance
     */
    private static $manager;

    /**
     * @var string URI
     */
    private static $uri;

    /**
     * @var string Database name
     */
    private static $dbName;

    /**
     * @var Client MongoDB native client
     */
    private $client;

    /**
     * @var string collection name
     */
    private $collectionName;

    /**
     * @var int limit
     */
    private $limit = self::DEFAULT_LIMIT;

    /**
     * @var array limit
     */
    private $sort = [];

    /**
     * @param string $uri
     * @param string $dbName
     */
    public static function init(string $uri, string $dbName)
    {
        self::$uri = $uri;
        self::$dbName = $dbName;
    }

    /**
     * Get manager instance
     *
     * @return EntityManager
     *
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     * @throws \MongoDB\Driver\Exception\InvalidArgumentException
     */
    public static function getInstance()
    {
        if (null === self::$manager) {
            self::$manager = new EntityManager(self::$uri, self::$dbName);
        }

        return self::$manager;
    }

    /**
     * MongoEntityManager constructor.
     *
     * @param string $uri
     * @param string $dbName
     *
     * @throws \MongoDB\Driver\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     * @throws \MongoDB\Exception\InvalidArgumentException
     */
    private function __construct(string $uri, string $dbName)
    {
        $this->client = new Client($uri . '/' . $dbName);
    }

    /**
     * Set collection name
     *
     * @param string $collection
     *
     * @return EntityManager
     */
    public function setCollection(string $collection) : EntityManager
    {
        $this->collectionName = $collection;

        return $this;
    }

    public function limit(int $limit) : EntityManager
    {
        $this->limit = $limit;

        return $this;
    }

    public function sort(array $sort) : EntityManager
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * Find by query
     *
     * @param array $params
     *
     * @return array
     *
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function find(array $params)
    {
        $queryOptions = [];
        if (!empty($sort)) {
            $queryOptions['sort'] = $sort;
        }
        if (!empty($limit)) {
            $queryOptions['limit'] = $limit;
        }

        $results = $this->client
            ->selectCollection(self::$dbName, $this->collectionName)
            ->withOptions()
            ->find($params, $queryOptions)
            ->toArray();

        $models = [];
        foreach ($results as $result) {
            $models[] = self::mapModel(new $result['className'](), $result);
        }

        return $models;
    }

    /**
     * Find one document by query
     *
     * @param array $params
     *
     * @return mixed|null
     *
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function findOne(array $params)
    {
        $result = $this->limit(1)->find($params);
        if (0 === count($result)) {
            return null;
        }

        return $result[0];
    }

    /**
     * Find one document by _id
     *
     * @param string $_id
     *
     * @return mixed|null
     *
     * @throws \MongoDB\Driver\Exception\InvalidArgumentException
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function findById(string $_id)
    {
        return $this->findOne(['_id' => new ObjectID($_id)]);
    }

    /**
     * Insert document to database
     *
     * @param BaseModel $model
     *
     * @return \MongoDB\InsertOneResult
     *
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function insert(BaseModel $model)
    {
        return $this->client
            ->selectCollection(self::$dbName, $model->getCollection())
            ->insertOne($model->getAttributes());
    }

    /**
     * Update document in databse
     *
     * @param BaseModel $model
     *
     * @return \MongoDB\UpdateResult
     *
     * @throws \MongoDB\Driver\Exception\InvalidArgumentException
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function update(BaseModel $model)
    {
        return $this->client
            ->selectCollection(self::$dbName, $model->getCollection())
            ->replaceOne(
                ['_id' => new ObjectID($model->getId())],
                $model->getAttributes()
            );
    }

    /**
     * Delete document from database
     *
     * @param BaseModel $model
     *
     * @return \MongoDB\DeleteResult
     *
     * @throws \MongoDB\Driver\Exception\InvalidArgumentException
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     * @throws \MongoDB\Exception\InvalidArgumentException
     */
    public function delete(BaseModel $model)
    {
        return $this->client
            ->selectCollection(self::$dbName, $model->getCollection())
            ->deleteOne(['_id' => new ObjectID($model->getId())]);
    }

    /**
     * Map document to model
     *
     * @param BaseModel $model
     * @param $result
     *
     * @return BaseModel
     */
    private static function mapModel(BaseModel $model, BSONDocument $result)
    {
        foreach ($result as $key => $value) {
            $method = self::buildMethodName($key);

            if (method_exists($model, $method)) {
                if ($value instanceof BSONDocument && isset($value['className'])) {
                    $value = self::mapModel(new $value['className'](), $value);
                }

                $model->$method($value);
            }
        }

        return $model;
    }

    /**
     * System function. build setter name for model
     *
     * @param string $key
     *
     * @return string
     */
    private static function buildMethodName(string $key)
    {
        $words = explode('_', $key);
        foreach ($words as &$word) {
            $word = ucfirst($word);
        }

        return 'set' . implode('', $words);
    }
}