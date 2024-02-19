<?php

namespace Drupal\custom_tables_datasource\Controller;


use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller routines for custom_tables_datasource routes.
 */
class CustomTablesDatasourceController extends ControllerBase {

  

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    // $instance->cartProvider = $container->get('commerce_cart.cart_provider');
    return $instance;
  }

  /**
   * Outputs a view of the data
   *
   * @return array
   *   A render array.
   */
  public function dataPage() {
    $build = [];
    $cacheable_metadata = new CacheableMetadata();
    $cacheable_metadata->addCacheContexts(['user', 'session']);
    $block_1 = (object) array('1' => 'foo');

    $blocks = [ 
      "1" => $block_1
    ];

    if (!empty($blocks)) {

      foreach ($blocks as $block_id => $block) {
        $build[$block_id] = [
          '#type' => 'details',
          '#title' => $this->t('Row #@num: local and Solr indexing data', ['@num' => 1]),
          '#markup' => '<h3>' . $this->t('Our data that would be sent to Solr during indexing:') . '</h3>',

        ];
        $cacheable_metadata->addCacheableDependency($block);
      }
    }
    else {
      $build['empty'] = [
        '#theme' => 'custom_tables_datasource_empty_page',
      ];
    }
    $build['#cache'] = [
      'contexts' => $cacheable_metadata->getCacheContexts(),
      'tags' => $cacheable_metadata->getCacheTags(),
      'max-age' => $cacheable_metadata->getCacheMaxAge(),
    ];

    return $build;
  }


}
