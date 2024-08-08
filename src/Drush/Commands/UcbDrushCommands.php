<?php

namespace Drupal\ucb_drush_commands\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;
use \Drupal\Core\Url;
use Drupal\ucb_default_content\DefaultContent;

/**
 * A Drush commandfile.
 */
final class UcbDrushCommands extends DrushCommands {

  /**
   * Constructs a UcbDrushCommands object.
   */
  public function __construct(
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
    );
  }


  /**
   * Store a report.
   */
  #[CLI\Command(name: 'ucb_drush_commands:store-report')]
  #[CLI\Usage(name: 'ucb_drush_commands:shortcode-convert', description: 'Store a report')]
  public function storeReport($options = []) {

    $myfile = fopen("sites/default/files/migration-report.html", "r");
    $report = fread($myfile, filesize("sites/default/files/migration-report.html"));



    $node = null;


    try {
      $this->logger()->success(dt("Test 1"));
      $alias = \Drupal::service('path_alias.manager')->getPathByAlias('/migration-report');
      $this->logger()->success(dt($alias));
      $this->logger()->success(dt("Test 2"));

      $params = Url::fromUri("internal:" . $alias)->getRouteParameters();

      $this->logger()->success(dt("Test 3"));
      $entity_type = key($params);
      $this->logger()->success(dt("Test 4"));
      $node = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);
      $this->logger()->success(dt("Test 5"));

    }
    catch(\Exception $e) {
      $this->logger()->success(dt($e->getMessage()));
    }

    if(is_null($node))
    {
      $node = Node::create([
        'type' => 'basic_page',
        'title' => 'Migration Report',
        'body' => [
          'value' => $report,
          'format' => 'full_html',
        ],
      ]);

      $node->save();
      fclose($myfile);
    }
    else
    {
      $node->set('body',  ['value' => $report, 'format' => 'full_html']);
      $node->save();
    }
  }





  /**
   * Convert shortcodes in to CKEditor5 HTML.
   */
  #[CLI\Command(name: 'ucb_drush_commands:shortcode-convert', aliases: ['scc'])]
  #[CLI\Usage(name: 'ucb_drush_commands:shortcode-convert', description: 'Usage description')]
  public function shortcodeConvert($arg1, $options = ['option-name' => 'default']) {
  }


  /**
   * Create default 404 page.
   */
  #[CLI\Command(name: 'ucb_drush_commands:create-404', aliases: ['c404'])]
  #[CLI\Usage(name: 'ucb_drush_commands:create-404', description: 'Create default 404 page')]
  public function create404Page() {
    DefaultContent::create_404_page();
  }
}
