<?php

namespace Drupal\custom_tables_datasource\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\custom_tables_datasource\Plugin\DataType\CustomDataType;
use Drupal\custom_tables_datasource\Plugin\search_api\datasource\CustomEntityTrackingManager;

/**
 * A Drush commandfile.
 */
class CustomTablesDatasourceCommands extends DrushCommands {

  /**
   * Constructs a CustomTablesDatasourceCommands object.
   */
  public function __construct( ContainerInterface $container) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container
    );
  }


  /**
   * Prints values from `my_custom_table`
   *
   * @usage custom_tables_datasource-getCustomValues 
   *  ## Runs the command, 
   * 
   * @command custom_tables_datasource:getCustomValues
   * @aliases mits_gcv
   *
   */
  public function getCustomValues()
  {
    // $this->logger()->success(dt('Running drush mits_tok to print all drupal tokens...'));

    $rows = [];

    $ids = [1, 2];
    $query = \Drupal::database()->select('my_custom_table', 'mct')
      ->fields('mct')
      ->condition('id', $ids, 'IN');
    $result = $query->execute();

    while ($row = $result->fetchAssoc()) {

        $row_id = $row['id'];
        $table_row = [];
        $test = new CustomDataType($row, 'mitsos');
        foreach ($test->getProperties() as $key => $property) {
          $table_row [ $property->getName() ] = $property->getValue();
          // echo "property name: " . $property->getName() . " = ". $property->getValue() . " \n";
        } 

        $rows[$row_id] = $table_row;
        
      }


    return new RowsOfFields($rows);

  }



    /**
   * Prints values from `my_custom_table`
   *
   * @usage custom_tables_datasource-getCustomValues 
   *  ## Runs the command, 
   * 
   * @command custom_tables_datasource:getCustomValues
   * @aliases mits_ucv
   *
   */
  public function updateCustomValues($id = NULL, $updated_name="Kalamarakis")
  {
    // $this->logger()->success(dt('Running drush mits_tok to print all drupal tokens...'));

    if (!$id) {
      $id = 1;
    }

    // Update data
    $query = \Drupal::database()->update('my_custom_table')
      ->condition('id', $id, '=')
      ->fields(['name' => $updated_name, 'description' => 'updated by ' . $updated_name]);
      
    $result = $query->execute();

    // Get data
    $query = \Drupal::database()->select('my_custom_table', 'mct')
    ->fields('mct')
    ->condition('id', $id, '=');
    $result = $query->execute();


    while ($row = $result->fetchAssoc()) {

        $row_id = $row['id'];
        $row['name'] = '';

        // This is how we will update the index after we get an updated item from ERP...
        // Create the updated data object -- 
        // NOTE: We do not really need to do this.  All we need is the ID of the changed row. 
        // TODO: Reimplement CustomEntityTrackingManager::dataUpdate() etc to accept only row ID
        $updated_data = new CustomDataType($row, 'mitsos');
        /** @var \Drupal\custom_tables_datasource\Plugin\search_api\datasource\CustomEntityTrackingManager  */
        $tracking_manager = \Drupal::getContainer()->get('search_api.custom_tables_datasource.tracking_manager');        
        $tracking_manager->dataUpdate($updated_data);
      }
    
    return;
  }

}

