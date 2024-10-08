<?php

namespace Drupal\ucb_drush_commands\Drush\Commands;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\ucb_default_content\DefaultContent;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\paragraphs\Entity\Paragraph;
/**
 * A Drush commandfile.
 */
final class UcbDrushCommands extends DrushCommands {

    /**
     * The DefaultContent service.
     *
     * @var \Drupal\ucb_default_content\DefaultContent
     */
    protected $defaultContent;

    /**
     * Constructs a UcbDrushCommands object.
     */
    public function __construct(
        DefaultContent $defaultContent,
    ) {
        parent::__construct();
        $this->defaultContent = $defaultContent;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('ucb_default_content')
        );
    }


    /**
     * Attach missing paragraph to articles if needed.
     */
    #[CLI\Command(name: 'ucb_drush_commands:article-paragraphfix', aliases: ['apf'])]
    #[CLI\Usage(name: 'ucb_drush_commands:article-paragraphfix', description: 'Attach missing paragraph to articles if needed')]
    public function articleParagraphFix()
    {
        $this->logger()->success(dt('Loading article nodes...'));
        $nids = \Drupal::entityQuery('node')->condition('type', 'ucb_article')->accessCheck(FALSE)->execute();

        $nodes =  \Drupal\node\Entity\Node::loadMultiple($nids);

        foreach ($nodes as $node)
        {
            $this->logger()->success(dt('Node ' . $node->nid->getValue()[0]['value'] . '...'));
            if(count($node->get('field_ucb_related_articles_parag')) === 0)
            {
                $this->logger()->success(dt('Missing related articles paragraph...'));

            }
            $paragraph = Paragraph::create(['type' => 'ucb_related_articles_block']);
            $paragraph->save();
            $node->field_ucb_related_articles_parag->appendItem($paragraph);
            $node->save();
        }
    }


    /**
     * Store a report.
     */
    #[CLI\Command(name: 'ucb_drush_commands:store-report')]
    #[CLI\Usage(name: 'ucb_drush_commands:shortcode-convert', description: 'Store a report')]
    public function storeReport($options = []) {

        $myfile = fopen("sites/default/files/migration-report.html", "r");
        $report = fread($myfile, filesize("sites/default/files/migration-report.html"));

        $node = NULL;

        try {
            $alias = \Drupal::service('path_alias.manager')->getPathByAlias('/migration-report');
            $this->logger()->success(dt($alias));

            $params = Url::fromUri("internal:" . $alias)->getRouteParameters();

            $entity_type = key($params);
            $node = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);

        }
        catch (\Exception $e) {
            $this->logger()->success(dt($e->getMessage()));
        }

        if (is_null($node)) {
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
        else {
            $node->set('body', ['value' => $report, 'format' => 'full_html']);
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
        $this->defaultContent->create404Page();
    }

}
