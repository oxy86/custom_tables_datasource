<?php

namespace Drupal\custom_tables_datasource\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "custom_tables_datasource_block",
 *   admin_label = @Translation("Custom Tables Datasource Block"),
 *   category = @Translation("Custom")
 * )
 */
class ExampleBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = [
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

}
