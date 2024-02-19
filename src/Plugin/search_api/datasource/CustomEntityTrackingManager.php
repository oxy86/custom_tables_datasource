<?php

declare(strict_types=1);

namespace Drupal\custom_tables_datasource\Plugin\search_api\datasource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Task\TaskManagerInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\custom_tables_datasource\Plugin\DataType\CustomDataType;


/**
 * Provides hook implementations on behalf of the Custom Entity datasource.
 *
 * @see \Drupal\search_api\Plugin\search_api\datasource\CustomEntity
 */
class CustomEntityTrackingManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The Search API task manager.
   *
   * @var \Drupal\search_api\Task\TaskManagerInterface
   */
  protected $taskManager;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\search_api\Task\TaskManagerInterface $taskManager
   *   The task manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager, TaskManagerInterface $taskManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->taskManager = $taskManager;
  }

  /**
   *
   * Adds new entries to the tracking table for each index that tracks data of this type.
   *
   * @param \Drupal\custom_tables_datasource\Plugin\DataType\CustomDataType $my_data
   *   The new data.
   */
  public function dataInsert(CustomDataType $my_data) {
    $this->trackDataChange($my_data, TRUE);
  }

  /**
   *
   * Updates the corresponding tracking table entries for each index that tracks
   * this data type.
   *
   * @param \Drupal\custom_tables_datasource\Plugin\DataType\CustomDataType $my_data
   *   The updated data.
   *
   * @see search_api_entity_update()
   */
  public function dataUpdate(CustomDataType $my_data) {
    \Drupal::logger('CustomEntityTrackingManager')->notice("dataUpdate() running...");
    $this->trackDataChange($my_data);
  }

  /**
   * Queues a data entry for indexing.
   *
   * If "Index items immediately" is enabled for the index, the entity will be
   * indexed right at the end of the page request.
   *
  *
   * @param \Drupal\custom_tables_datasource\Plugin\DataType\CustomDataType $my_data
   *   The data entry to be indexed.
   * @param bool $new
   *   (optional) TRUE if this is a new entry, FALSE if it already existed (and
   *   should already be known to the tracker).
   */
  public function trackDataChange(CustomDataType $my_data, bool $new = FALSE) {

    \Drupal::logger('CustomEntityTrackingManager')->notice("trackDataChange() running...");
    $indexes = $this->getIndexesForDataType($my_data);
    if (!$indexes) {
      return;
    }

    $datasource_id = 'custom_tables_datasource';

    $item_ids = [];
    $id = $my_data->get('id')->getValue();
    $item_ids = [$id];


    $search_api_tasks_count = $this->taskManager->getTasksCount();
    \Drupal::logger('CustomEntityTrackingManager')->notice("trackDataChange() - search_api_tasks_count: " . $search_api_tasks_count);
    

    foreach ($indexes as $index) {
      if ($new) {
        $filtered_item_ids = static::filterValidItemIds($index, $datasource_id, $item_ids);
        $insert_task = CustomEntityTaskManager::INSERT_ITEMS_TASK_TYPE;
        if ($filtered_item_ids) {
          $index->trackItemsInserted($datasource_id, $filtered_item_ids);
        }
      }
      else {
        $filtered_item_ids = static::filterValidItemIds($index, $datasource_id, $item_ids);
        if ($filtered_item_ids) {
          $index->trackItemsUpdated($datasource_id, $filtered_item_ids);
        }
      }

    }

    $search_api_tasks_count = $this->taskManager->getTasksCount();
    \Drupal::logger('CustomEntityTrackingManager')->notice("trackDataChange() - NOW search_api_tasks_count: " . $search_api_tasks_count);

  }

  /**
   * 
   * Deletes all entries for this data from the tracking table for each index that tracks data of this type.
   *
   * @param \Drupal\custom_tables_datasource\Plugin\DataType\CustomDataType $my_data
   *   The deleted data.
   *
   * @see search_api_entity_delete()
   */
  public function dataDelete(CustomDataType $my_data) {

    $indexes = $this->getIndexesForDataType($my_data);
    if (!$indexes) {
      return;
    }

    $datasource_id = 'custom_tables_datasource';

    $delete_task = CustomEntityTaskManager::DELETE_ITEMS_TASK_TYPE;

    // Remove the search items
    $item_ids = [];
    $id = $my_data->get('id')->getValue();
    $item_ids = [$id];

    foreach ($indexes as $index) {
      $index->trackItemsDeleted($datasource_id, $item_ids);
    }

  }

  /**
   * Retrieves all indexes that are configured to index the given CustomDataType
   *
   * @param \Drupal\custom_tables_datasource\Plugin\DataType\CustomDataType $data_type
   * 
   *   The data type for which to check.
   *
   * @return \Drupal\search_api\IndexInterface[]
   *   All indexes that are configured to index the given data type (using the
   *   our custom datasource plugin).
   */
  public function getIndexesForDataType(CustomDataType $data_type): array {

    $datasource_id = 'custom_tables_datasource';

    /** @var \Drupal\search_api\IndexInterface[] $indexes */
    $indexes = [];
    try {
      $indexes = $this->entityTypeManager->getStorage('search_api_index')
        ->loadMultiple();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      // Can't really happen, but play it safe to appease static code analysis.
    }

    foreach ($indexes as $index_id =>  $index) {
      // Filter out indexes that don't contain the datasource in question.
      /**  @var \Drupal\search_api\IndexInterface */
      if (!$index->isValidDatasource($datasource_id)) {
        unset($indexes[$index_id]);
      }
    }

    return $indexes;
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for type "search_api_index".
   *
   * Detects changes in the index configuration and adds/removes items
   * to/from tracking accordingly.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index that was updated.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if a datasource referenced an unknown entity type.
   * @throws \Drupal\search_api\SearchApiException
   *   Never thrown, but static analysis tools think it could be.
   *
   * @see search_api_search_api_index_update()
   */
  public function indexUpdate(IndexInterface $index) {
    if (!$index->status()) {
      return;
    }
    /** @var \Drupal\search_api\IndexInterface $original */
    $original = $index->original ?? NULL;
    if (!$original || !$original->status()) {
      return;
    }

    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      if ($datasource->getBaseId() != 'entity'
          || !$original->isValidDatasource($datasource_id)) {
        continue;
      }
      $old_datasource = $original->getDatasource($datasource_id);
      $old_config = $old_datasource->getConfiguration();
      $new_config = $datasource->getConfiguration();

      // TODO -- WE DO NOT REALLY NEED THIS... AS INDEX CONFIG CHANGES DO NOT CONCERN US.

    }
  }

  /**
   * Filters a set of datasource-specific item IDs.
   *
   * Returns only those item IDs that are valid for the given datasource and
   * index. This method only checks the item language, though – whether an
   * entity with that ID actually exists, or whether it has a bundle included
   * for that datasource, is not verified.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index for which to validate.
   * @param string $datasource_id
   *   The ID of the datasource on the index for which to validate.
   * @param string[] $item_ids
   *   The item IDs to be validated.
   *
   * @return string[]
   *   All given item IDs that are valid for that index and datasource.
   */
  public static function filterValidItemIds(IndexInterface $index, string $datasource_id, array $item_ids): array {
    if (!$index->isValidDatasource($datasource_id)) {
      return $item_ids;
    }

    try {
      $config = $index->getDatasource($datasource_id)->getConfiguration();
    }
    catch (SearchApiException) {
      // Can't really happen, but play it safe to appease static code analysis.
      return $item_ids;
    }

    // If the entity type doesn't allow translations, we just accept all IDs.
    // (If the entity type were translatable, the config key would have been set
    // with the default configuration.)
    if (!isset($config['languages']['selected'])) {
      return $item_ids;
    }
    // WE DONT NEED THIS, OUR DATA IS NOT TRANSLATABLE...
    // $always_valid = [
    //   LanguageInterface::LANGCODE_NOT_SPECIFIED,
    //   LanguageInterface::LANGCODE_NOT_APPLICABLE,
    // ];
    // $valid_ids = [];
    // foreach ($item_ids as $item_id) {
    //   $pos = strrpos($item_id, ':');
    //   // Item IDs without colons are always invalid.
    //   if ($pos === FALSE) {
    //     continue;
    //   }
    //   $langcode = substr($item_id, $pos + 1);
    //   if (Utility::matches($langcode, $config['languages'])
    //       || in_array($langcode, $always_valid)) {
    //     $valid_ids[] = $item_id;
    //   }
    // }
    // return $valid_ids;

    return $item_ids;
  }

}
