<?php

namespace ride\application\orm\elastic;

use ride\library\orm\entry\Entry;
use ride\library\orm\model\Model;

/**
 * Indexer of ORM entries in Elasticsearch
 */
class ElasticIndexer extends AbstractElastic {

    /**
     * Indexes all entries of the provided models
     * @param array $models Array with Model instances
     * @return null
     */
    public function indexModels(array $models) {
        foreach ($models as $model) {
            $this->indexModel($model);
        }
    }

    /**
     * Indexes all entries of the provided model
     * @param \ride\library\orm\model\Model $model
     * @return null
     */
    public function indexModel(Model $model) {
        if (!$model->getMeta()->getOption('behaviour.elastic')) {
            return;
        }

        $limit = 1000;
        $page = 1;

        if ($model->getMeta()->isLocalized()) {
            $locales = $model->getOrmManager()->getLocales();
        } else {
            $locales = array($model->getOrmManager()->getLocale());
        }

        foreach ($locales as $locale) {
            $page = 1;
            do {
                $query = $model->createQuery($locale);
                $query->addOrderBy('{id} ASC');
                $query->setLimit($limit, ($page - 1) * $limit);

                $entries = $query->query();
                foreach ($entries as $entry) {
                    $this->indexEntry($model, $entry);
                }

                $page++;
            } while (count($entries) == $limit);
        }
    }

    /**
     * Indexes a new entry
     * @param \ride\library\orm\model\Model $model
     * @param \ride\library\orm\entry\Entry $entry
     * @return array Index result from Elastic client
     */
    public function indexEntry(Model $model, Entry $entry) {
        $parameters = $this->getParameters($model);
        if (!$parameters) {
            return false;
        }

        $parameters['id'] = $this->getDocumentId($entry);
        $parameters['body'] = $entry->getElasticDocument();

        return $this->client->index($parameters);
    }

    /**
     * Updates an entry in the index
     * @param \ride\library\orm\model\Model $model
     * @param \ride\library\orm\entry\Entry $entry
     * @return array Index result from Elastic client
     */
    public function updateEntry(Model $model, Entry $entry) {
        $parameters = $this->getParameters($model);
        if (!$parameters) {
            return false;
        }

        $parameters['id'] = $this->getDocumentId($entry);
        $parameters['body'] = array('doc' => $entry->getElasticDocument());

        return $this->client->update($parameters);
    }

    /**
     * Deletes an entry from the index
     * @param \ride\library\orm\model\Model $model
     * @param \ride\library\orm\entry\Entry $entry
     * @return array Index result from Elastic client
     */
    public function deleteEntry(Model $model, Entry $entry) {
        $parameters = $this->getParameters($model);
        if (!$parameters) {
            return false;
        }

        $parameters['id'] = $this->getDocumentId($entry);

        return $this->client->delete($parameters);
    }

}
