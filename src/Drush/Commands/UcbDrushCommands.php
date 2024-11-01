<?php

namespace Drupal\ucb_drush_commands\Drush\Commands;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\ucb_default_content\DefaultContent;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;


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
     * Fix section background images.
     */
    #[CLI\Command(name: 'ucb_drush_commands:section-bg-fix', aliases: ['sbgf'])]
    #[CLI\Usage(name: 'ucb_drush_commands:section-bg-fix', description: 'Fix section background images')]
    public function sectionBackgroundFix()
    {
        $this->logger()->success(dt('Loading basic_page nodes...'));
        $nids = \Drupal::entityQuery('node')->condition('type', 'basic_page')->accessCheck(FALSE)->execute();

        $nodes =  \Drupal\node\Entity\Node::loadMultiple($nids);

        foreach ($nodes as $node)
        {
            foreach($node->get(OverridesSectionStorage::FIELD_NAME) as $sectionItem)
            {
                $section = $sectionItem->getValue()['section'];
                $sectionconfig = $section->getLayoutSettings();

                if(array_key_exists('background_image', $sectionconfig))
                {

                    $overlay_selection = $sectionconfig['background_image_styles'];
                    $overlay_styles = "";
                    $new_styles = "";

                    if ($overlay_selection == "black")
                    {
                        $overlay_styles = "linear-gradient(rgb(20, 20, 20, 0.5), rgb(20, 20, 20, 0.5))";
                    }
                    elseif ($overlay_selection == "white")
                    {
                        $overlay_styles = "linear-gradient(rgb(255, 255, 255, 0.7), rgb(255, 255, 255, 0.7))";
                    }
                    else
                    {
                        $overlay_styles = "none";
                    }

                    $fid = $sectionconfig['background_image'] + 1;
                    $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);


                    $this->logger()->success(dt(print_r($sectionconfig, True)));
                    $this->logger()->success(dt('FID: ' . $fid));

                    $url = $file->createFileUrl(TRUE);


                    $crop = \Drupal::service('focal_point.manager')->getCropEntity($file, 'focal_point');
                    if ($crop) {
                        // Get the x and y position from the crop.
                        $fp_abs = $crop->position();
                        $x = $fp_abs['x'];
                        $y = $fp_abs['y'];

                        // Get the original width and height from the image.
                        $image_factory = \Drupal::service('image.factory');
                        $image = $image_factory->get($file->getFileUri());
                        $width = $image->getWidth();
                        $height = $image->getHeight();

                        // Convert the absolute x and y positions to relative values.
                        $fp_rel = \Drupal::service('focal_point.manager')->absoluteToRelative($x, $y, $width, $height);
                        $position_vars = $fp_rel['x'] . '% ' . $fp_rel['y'] . '%;';
//                        }


                        $media_image_styles = [
                            'background:  ' . $overlay_styles . ', url(' . $url . ');',
                            'background-position: ' . $position_vars . ';',
                            'background-size: cover;',
                            'background-repeat: no-repeat;',
                        ];

                        $new_styles = implode(' ', $media_image_styles);

                    }
                    //            $sectionconfig['background_image_styles'] = "TESTX";
                    $sectionconfig['background_image_styles'] = $new_styles;
                    $section->setLayoutSettings($sectionconfig);
                    $sectionItem->setValue($section);
                }
            }

            $node->save();
        }
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
