# Custom Tables Datasource for Drupal Search API

This Drupal 10 module provides a simple implementation of a custom datasource for 
Drupal Search API. 
Our custom datasource plugin consumes data from custom tables inside the drupal database
so that they can be indexed by the corresponding Search API index on any supported server (database, solr, etc). 

After you install the module, you have to select the Custom Tables Datasource in Search API -> Your index -> Datasources 

After some data is updated, you can call the tracking manager service to update the index, like this: 
 
`
        /** @var \Drupal\custom_tables_datasource\Plugin\search_api\datasource\CustomEntityTrackingManager  */
        $tracking_manager = \Drupal::getContainer()->get('search_api.custom_tables_datasource.tracking_manager');        
        $tracking_manager->dataUpdate($updated_data);

`

@see https://www.drupal.org/docs/8/modules/search-api/getting-started/adding-an-index
@see https://www.drupal.org/docs/8/modules/search-api/developer-documentation/providing-a-new-datasource-plugin
