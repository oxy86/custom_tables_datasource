<?php

namespace Drupal\custom_tables_datasource\Plugin\search_api\datasource;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\external_entities\Entity\Query\External\Query as ExternalEntitiesQuery;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Datasource\DatasourcePluginBase;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\SearchApiException;
use Drupal\Core\State\StateInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\custom_tables_datasource\Plugin\DataType\CustomDataType;


// As per Documentation: https://www.drupal.org/docs/8/modules/search-api/developer-documentation/providing-a-new-datasource-plugin
// See example code from https://www.drupal.org/project/search_api_solr
// here: https://git.drupalcode.org/project/search_api_solr/-/blob/b48084da117e4173941b01680728616cb87eb5a9/src/Plugin/search_api/datasource/SolrDocument.php
/// also see: https://www.drupal.org/project/search_api/issues/3215149
// Another solution to "Getting Search API Index to see items indexed externally" through Search API Solr: 
// https://www.drupal.org/project/search_api_solr/issues/2717589

/**
 * Represents a datasource which exposes data from custom tables.
 *
 * @SearchApiDatasource(
 *   id = "custom_tables_datasource",
 *   label = @Translation("Custom Datasource"),
 *   description = @Translation("Exposes custom data from custom tables for indexing and searching."),
 * )
 */
class CustomEntity extends DatasourcePluginBase
{

  /**
   * The key for accessing last tracked ID information in site state.
   */
  protected const TRACKING_PAGE_STATE_KEY = 'search_api.custom_tables_datasource.custom_entity.last_ids';


  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;



  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;


  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected $entity_type_manager;



  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The new typed data manager.
   */
  protected $typed_data_manager;


  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|null
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;



  /**
   * Constructs a CustomEntity object.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'views' may be used to
   *   initialize the decorated views display.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->pluginId = $plugin_id;

    \Drupal::logger('CustomEntity')
    ->notice("__construct()" 
    . " - ID: ". $this->pluginId  
    . " - Index: " . $this->getIndex()->id()
    . " - table name: " . $this->getTableName()
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {

    // \Drupal::logger('CustomEntity')->notice("create() running...plugin_id: $plugin_id");

    /** @var static $datasource */
    // $datasource = new static(
    //   $configuration,
    //   $plugin_id,
    //   $plugin_definition
    // );

    /** @var static $datasource */
    $datasource = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $datasource->setDatabaseConnection($container->get('database'));
    $datasource->setLanguageManager($container->get('language_manager'));
    $datasource->setConfigFactory($container->get('config.factory'));
    $datasource->setState($container->get('state'));

    return $datasource;
  }



  /**
   * Retrieves the database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection.
   */
  public function getDatabaseConnection(): Connection
  {
    return $this->database ?: \Drupal::database();
  }

  /**
   * Sets the database connection.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The new database connection.
   *
   * @return $this
   */
  public function setDatabaseConnection(Connection $connection): self
  {
    $this->database = $connection;
    return $this;
  }



  /**
   * Retrieves the config factory.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory.
   */
  public function getConfigFactory()
  {
    return $this->configFactory ?: \Drupal::configFactory();
  }

  /**
   * Retrieves the config value for a certain key in our settings.
   *
   * @param string $key
   *   The key whose value should be retrieved.
   *
   * @return mixed
   *   The config value for the given key.
   */
  protected function getConfigValue($key)
  {
    return $this->getConfigFactory()->get('custom_tables_datasource.settings')->get($key);
  }

  /**
   * Sets the config factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The new config factory.
   *
   * @return $this
   */
  public function setConfigFactory(ConfigFactoryInterface $config_factory)
  {
    $this->configFactory = $config_factory;
    return $this;
  }


  /**
   * Retrieves the language manager.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The language manager.
   */
  public function getLanguageManager()
  {
    return $this->languageManager ?: \Drupal::languageManager();
  }

  /**
   * Sets the language manager.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The new language manager.
   */
  public function setLanguageManager(LanguageManagerInterface $language_manager)
  {
    $this->languageManager = $language_manager;
  }

  /**
   * Retrieves the state service.
   *
   * @return \Drupal\Core\State\StateInterface
   *   The entity type manager.
   */
  public function getState() {
    return $this->state ?: \Drupal::state();
  }

  /**
   * Sets the state service.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   *
   * @return $this
   */
  public function setState(StateInterface $state) {
    $this->state = $state;
    return $this;
  }



  /**
   * Retrieves the table name from our settings.
   *
   * @return string|NULL
   *   The table name or NULL
   */
  protected function getTableName()
  {
    return $this->getConfigValue('main_table');
  }



  /**
   * {@inheritdoc}
   */
  public function label()
  {
    return $this->t('Custom Tables Datasource');
  }


  /**
   * {@inheritdoc}
   */
  public function getItemId(ComplexDataInterface $item)
  {
    \Drupal::logger('CustomEntity')->notice("getItemId() running...");
    // Return the ID of the item, which is a row in the custom table.
    // Assuming the custom table has a column named 'id'.
    return $item->get('id')->getValue();
  }


  /**
   * Retrieves the value of a certain key in our custom entity
   *
   * @return string|NULL
   *   The value or NULL
   */
  public function getItemValue(ComplexDataInterface $item, string $key)
  {
    \Drupal::logger('CustomEntity')->notice("getItemValue() running...");

    foreach ($item->getProperties() as $prop_key => $property) {
      if ($prop_key == $key) {
        return $item->get($key)->getValue();  
      }
    } 
    return NULL;
  }



  /**
   * {@inheritdoc}
   */
  public function getItemLabel(ComplexDataInterface $item)
  {
    \Drupal::logger('CustomEntity')->notice("getItemLabel() running...");
    parent::getItemLabel($item);
  }

  /**
   * {@inheritdoc}
   */
  public function getItemUrl(ComplexDataInterface $item)
  {
    \Drupal::logger('CustomEntity')->notice("getItemUrl() running...");
    parent::getItemUrl($item);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids)
  {

    \Drupal::logger('CustomEntity')->notice("loadMultiple() running...ids count: " . count($ids));


    // Load multiple items from the custom table based on their IDs.
    $query = $this->getDatabaseConnection()
      ->select($this->getTableName(), 'mct')
      ->fields('mct')
      ->condition('id', $ids, 'IN');

    $result = $query->execute();

    $items = [];

    while ($row = $result->fetchAssoc()) {

      $row_id = $row['id'];

      \Drupal::logger('CustomEntity')->notice("loadMultiple() got sql result for row: $row_id" );
      
      // Create a unique hash from the name and our salt.
      $name = Crypt::hmacBase64(serialize($row_id), $this->getConfigValue('hash_salt'));

      // Wrap each row in a CustomDataType object, which implements ComplexDataInterface.
      /**  @var \Drupal\Core\TypedData\ComplexDataInterface  */
      $custom_data=new CustomDataType($row, $name);
      $items[$row_id] = $custom_data;

      \Drupal::logger('CustomEntity')->notice("loadMultiple() custom data has ID: " . $custom_data->get('id')->getValue()  );

      // Test saved custom data and log report
      foreach ($custom_data->getProperties() as $key => $property) {
        \Drupal::logger('CustomEntity')->notice("loadMultiple() CDI " . "custom data has property named: " . $property->getName() . " = ". $property->getValue() );
      }

       
    }

    return $items;
  }


  /**
   * {@inheritdoc}
   */
  public function getItemIds($page = NULL)
  {

    // This is called during the "Rebuild tracking information" phase

    //
    // We use a paging mechanism as advised by the Interface
    //

    // Build up the context for tracking the last ID for this batch page.
    $batch_page_context = [
      'index_id' => $this->getIndex()->id(),
      // The derivative plugin ID includes the entity type ID.
      'datasource_id' => $this->getPluginId(),
      'bundles' => ['custom_tables'],     // TODO: is this valid?
    ];
    // Hash the context to create a context key 
    $context_key = Crypt::hashBase64(serialize($batch_page_context));
    // Get the last ids from the state.
    $last_ids = $this->getState()->get(self::TRACKING_PAGE_STATE_KEY, []);

    \Drupal::logger('CustomEntity')
      ->notice(
        "getItemIds() running...page: " . $page 
        . " last_ids: " . implode_multi(',',$last_ids)
    );

    // Build the select query
    $query = $this->getDatabaseConnection()
      ->select($this->getTableName(), 'mct')
      ->fields('mct', ['id']);

    // Use the pager if we were asked to do so.
    if (isset($page)) {
      $page_size = $this->getConfigValue('tracking_page_size');   // default: 100
      assert($page_size, 'Tracking page size is not set.');

      // If known, use a condition on the last tracked ID for paging instead of
      // the offset, for performance reasons
      $offset = $page * $page_size;
      if ($page > 0) {
        // We only handle the case of picking up from where the last page left
        // off. (This will cause an infinite loop if anyone ever wants to index
        // Search API tasks in an index, so check for that to be on the safe
        // side. Also, the external_entities module doesn't reliably support
        // conditions on entity queries, so disable this functionality in that
        // case, too.)
        if (isset($last_ids[$context_key])
            && $last_ids[$context_key]['page'] == ($page - 1)
            && $this->getEntityTypeId() !== 'search_api_task'
            && !($query instanceof ExternalEntitiesQuery)) {
          $query->condition('id', $last_ids[$context_key]['last_id'], '>');
          $offset = 0;
        }
      }
      $query->range($offset, $page_size);

      // For paging to reliably work, a sort should be present.
      $query->orderBy('id');

    }

    // Get the array of all the IDs of the items that are available for indexing.
    $ids = $query->execute()->fetchCol();

    if (!$ids) {
      if (isset($page)) {
        // Clean up state tracking of last ID.
        unset($last_ids[$context_key]);
        $this->getState()->set(self::TRACKING_PAGE_STATE_KEY, $last_ids);
      }
      return NULL;
    }

    // Remember the last tracked ID for the next call.
    if (isset($page)) {
      $last_ids[$context_key] = [
        'page' => (int) $page,
        'last_id' => end($ids),
      ];
      $this->getState()->set(self::TRACKING_PAGE_STATE_KEY, $last_ids);
    }

    \Drupal::logger('CustomEntity')->notice("getItemIds() running...ids: " . implode(',', $ids));

    return $ids;
  }



  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions()
  {
    // Return an array of DataDefinitionInterface objects that describe the properties of the custom data.
    // Assuming the custom table has columns named 'id', 'name', 'url', 'description', 'price', and 'category'.

    \Drupal::logger('CustomEntity')->notice("getPropertyDefinitions() running...");

    $properties = [];
    $properties['id'] = DataDefinition::create('integer')
      ->setLabel($this->t('ID'))
      ->setDescription($this->t('The ID of the item.'));
    $properties['name'] = DataDefinition::create('string')
      ->setLabel($this->t('Name'))
      ->setDescription($this->t('The name of the item.'));
    $properties['url'] = DataDefinition::create('uri')
      ->setLabel($this->t('URL'))
      ->setDescription($this->t('The URL of the item.'));
    $properties['description'] = DataDefinition::create('string')
      ->setLabel($this->t('Description'))
      ->setDescription($this->t('The description of the item.'));
    $properties['price'] = DataDefinition::create('float')
      ->setLabel($this->t('Price'))
      ->setDescription($this->t('The price of the item.'));
    $properties['category'] = DataDefinition::create('string')
      ->setLabel($this->t('Category'))
      ->setDescription($this->t('The category of the item.'));


    \Drupal::logger('CustomEntity')->notice("getPropertyDefinitions() returning array with keys:" . implode(',',array_keys($properties)));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item)
  {
    // Add the field values of an item to a SearchApiItem object, which is used for indexing.

    \Drupal::logger('CustomEntity')->notice("addFieldValues() running...");

    /** @var \Drupal\custom_tables_datasource\Plugin\search_api\datasource\CustomDataType $my_custom_data */
    $my_custom_data = $item->getOriginalObject()->getValue();
    if (!($my_custom_data instanceof CustomDataType)) {
      throw new SearchApiException('Indexed data is not an instance of CustomDataType.');
    }

    // Loop over all indexed fields and add their values.
    foreach ($item->getFields() as $field) {
      $field_id = $field->getFieldIdentifier();

      // Skip fields that are not from this datasource.
      if (strpos($field_id, 'custom_tables_datasource') !== 0) {
        continue;
      }

      // Get the property name from the field ID.
      // Assuming the field ID is in the format 'custom_tables_datasource:PROPERTY'.
      $property = substr($field_id, strlen('custom_tables_datasource:') + 1);

      // Get the property value from the CustomDataType object.
      $value = $my_custom_data->get($property)->getValue();

      // Set the field value.
      $field->addValue($value);
    }
  }





  /**
   * Retrieves all indexes that are configured to index the given CustomDataType
   */
  public static function getIndexesForDataType(CustomDataType $data_type) {

    /** @var \Drupal\custom_tables_datasource\Plugin\search_api\datasource\CustomEntityTrackingManager  */
    $tracking_manager = \Drupal::getContainer()->get('search_api.custom_tables_datasource.tracking_manager');        
    return $tracking_manager-> getIndexesForDataType($data_type);

  }



}


/**
 * FOR DEBUG ONLY: Implode an associative multi-dimensional array into a  "[key=>value, key=>value,...]" string.
 * 
 * @param string $glue
 *  The glue
 * 
 * @param array $array
 *  The associative array to implode
 * 
 * @param string $symbol
 *  the symbol to be used between key and value.
 * 
 * 
 * @return string 
 *  A string with the imploded key-value pairs of the input array
 * 
 */
function implode_multi($glue, $array, $symbol = '=>')
{
  $string = "";
  foreach ($array as $key => $value) {
    if (!is_array($value)) {
      $string .= "'$key'" . $symbol . "'$value'";
    } else {
      $string .= "'$key'" . $symbol . "[ " . implode_multi($glue, $value, $symbol) . " ]";
    }
    $string .= $glue . ' ';
  }
  return $string;
}
