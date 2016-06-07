<?php

namespace ride\application\orm\elastic;

use ride\library\event\Event;
use ride\library\orm\model\GenericModel;

/**
 * Event listener to update the ORM entries into Elastic
 */
class ElasticEventListener {

    /**
     * Constructs an Elastic event listener
     * @param ElasticIndexer $indexer
     * @return null
     */
    public function __construct(ElasticIndexer $indexer) {
        $this->indexer = $indexer;
    }

    /**
     * Handles a ORM action to update the elastic documents
     * @param \ride\library\event\Event $event
     * @return null
     */
    public function handleOrmAction(Event $event) {
        $model = $event->getArgument('model');
        $entry = $event->getArgument('entry');

        switch ($event->getName()) {
            case GenericModel::EVENT_INSERT_POST:
                $this->indexer->indexEntry($model, $entry);

                break;
            case GenericModel::EVENT_UPDATE_POST:
                $this->indexer->updateEntry($model, $entry);

                break;
            case GenericModel::EVENT_DELETE_POST:
                $this->indexer->deleteEntry($model, $entry);

                break;
        }
    }

}
