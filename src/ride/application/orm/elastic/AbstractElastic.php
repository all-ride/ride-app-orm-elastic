<?php

namespace ride\application\orm\elastic;

use Elasticsearch\Client;

use ride\library\orm\entry\Entry;
use ride\library\orm\entry\LocalizedEntry;
use ride\library\orm\model\Model;

/**
 * Abstract class to integrate with Elasticsearch
 */
abstract class AbstractElastic {

    /**
     * Elastic search client
     * @var \Elasticsearch\Client
     */
    protected $client;

    /**
     * Mapping between model name and index endpoint
     * @var array
     */
    private $types;

    /**
     * Constructs an elastic integration
     * @param \Elasticsearch\Client $client
     * @return null
     */
    public function __construct(Client $client) {
        $this->client = $client;
        $this->types = array();
    }

    /**
     * Gets the document id for the provided entry
     * @param \ride\library\orm\entry\Entry $entry
     * @return string
     */
    protected function getDocumentId(Entry $entry) {
        $id = $entry->getId();

        if ($entry instanceof LocalizedEntry) {
            $id .= '-' . $entry->getLocale();
        }

        return $id;
    }

    /**
     * Gets needed request parameters for the provided model
     * @param \ride\library\orm\model\Model
     * @return array|boolean Array with type and index or false when elastic is
     * disabled for the provided modelb
     */
    protected function getParameters(Model $model) {
        $modelName = $model->getName();

        if (isset($this->types[$modelName])) {
            return $this->types[$modelName];
        }

        $behaviour = $model->getMeta()->getOption('behaviour.elastic');
        if ($behaviour) {
            list($index, $type) = explode('/', $behaviour);

            $this->types[$modelName] = array(
                'index' => $index,
                'type' => $type,
            );
        } else {
            $this->types[$modelName] = false;
        }

        return $this->types[$modelName];
    }

}
