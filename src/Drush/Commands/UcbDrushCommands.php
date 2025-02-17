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
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Drupal\Core\Config;

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


        $config = \Drupal::config('pantheon_domain_masking.settings');



        $subpath = $config->get('subpath', '');
        $enabled = \filter_var($config->get('enabled', 'no'), FILTER_VALIDATE_BOOLEAN);
        if($enabled) {


            $this->logger()->success(dt('Loading basic_page nodes...'));
            $nids = \Drupal::entityQuery('node')->condition('type', 'basic_page')->accessCheck(FALSE)->execute();

            $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);

            foreach ($nodes as $node) {
                foreach ($node->get(OverridesSectionStorage::FIELD_NAME) as $sectionItem) {
                    $section = $sectionItem->getValue()['section'];
                    $sectionconfig = $section->getLayoutSettings();

                    if (array_key_exists('background_image', $sectionconfig)) {
                        $this->logger()->success(dt('background_image exists...'));
                        if (!empty(trim($sectionconfig['background_image_styles']))) {
                            $this->logger()->success(dt('background_image is not empty...'));

                            $overlay_selection = $sectionconfig['background_image_styles'];
                            $overlay_styles = "";
                            $new_styles = "";

                            if ($overlay_selection == "black") {
                                $overlay_styles = "linear-gradient(rgb(20, 20, 20, 0.5), rgb(20, 20, 20, 0.5))";
                            } elseif ($overlay_selection == "white") {
                                $overlay_styles = "linear-gradient(rgb(255, 255, 255, 0.7), rgb(255, 255, 255, 0.7))";
                            } else {
                                $overlay_styles = "none";
                            }

                            $mid = $sectionconfig['background_image'];


                            if (!is_null($mid)) {
                                $this->logger()->success(dt('MID is not null'));
                                $this->logger()->success(dt('MID: ' . $mid));


                                $mediaobject = \Drupal::entityTypeManager()->getStorage('media')->load($mid);

                                $this->logger()->success(dt(print_r($sectionconfig, True)));
                                $this->logger()->success(dt('MID Loaded: ' . $mid));

                                $fid = $mediaobject->field_media_image->target_id;
                                $this->logger()->success(dt('FID: ' . $fid));


                                $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);

                                $this->logger()->success(dt('FID Loaded: ' . $fid));


//                                $url = $file->createFileUrl();
                                $url = "https://www.colorado.edu/" . $subpath . $file->createFileUrl();

                                $this->logger()->success(dt('URL: ' . $url));


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
                    }
                }

                $node->save();
            }
        }
        else
        {
            $this->logger()->success(dt('Domain masking not enabled.  Exiting.'));
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
     * Store a migration report.
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

            $node->setPublished(false);
            $node->save();
            fclose($myfile);
        }
        else {
            $node->set('body', ['value' => $report, 'format' => 'full_html']);
            $node->setPublished(false);
            $node->save();
        }
    }



    /**
     * Store a site health report.
     */
    #[CLI\Command(name: 'ucb_drush_commands:store-site-health-report')]
    #[CLI\Usage(name: 'ucb_drush_commands:store-site-health-report', description: 'Store a site health report')]
    public function storeSiteHealthReport($options = []) {

        $myfile = fopen("sites/default/files/site-health-report.html", "r");
        $report = fread($myfile, filesize("sites/default/files/site-health-report.html"));

        $node = NULL;

        try {
            $alias = \Drupal::service('path_alias.manager')->getPathByAlias('/site-health-report');
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
                'title' => 'Site Health Report',
                'body' => [
                    'value' => $report,
                    'format' => 'full_html',
                ],
            ]);

            $node->setPublished(false);
            $node->save();
            fclose($myfile);
        }
        else {
            $node->set('body', ['value' => $report, 'format' => 'full_html']);
            $node->setPublished(false);
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


    /**
     * Restore article form settings for syndication-enabled sites
     */
    #[CLI\Command(name: 'ucb_drush_commands:restore-article-form-settings', aliases: ['rafs'])]
    #[CLI\Usage(name: 'ucb_drush_commands:restore-article-form-settings', description: 'Restore Article form settings for syndication-enabled sites')]
    public function restoreArticleFormSettings() {

        if(\Drupal::hasService('ucb_article_syndication'))
        {
            $service = \Drupal::service('ucb_article_syndication');
            $service->showSyndicationFields();
        }
    }


    /**
     * Grants permissions to Site Manager and updates administerusersbyrole settings.
     */
    #[CLI\Command(name: 'ucb_drush_commands:update-site-manager-permissions', aliases: ['usmp'])]
    #[CLI\Usage(name: 'ucb_drush_commands:update-site-manager-permissions', description: 'Grants permissions to Site Manager and updates administerusersbyrole settings')]
    public function updateSiteManagerPermissions() {
        // Ensure that the "administerusersbyrole" module is fully installed before proceeding.
        if (!\Drupal::moduleHandler()->moduleExists('administerusersbyrole')) {
            $this->logger()->error('administerusersbyrole module is not installed. Skipping role and config update.');
            return;
        }

        // Load and update administerusersbyrole.settings.yml, in case this is a pre-existing site
        $config = \Drupal::configFactory()->getEditable('administerusersbyrole.settings');
        $config->merge([
            'roles' => [
                'site_manager' => 'perm',
                'site_owner' => 'perm',
            ],
        ])->save();

        $this->logger()->notice('Updated administerusersbyrole.settings.yml to set site_manager to "perm".');

        // Permissions for site_manager.
        $site_manager_permissions = [
            'view users by role',
            'cancel users by role',
            'edit users by role',
            'role-assign users by role',
            'cancel users with role site_manager',
            'edit users with role site_manager',
            'view users with role site_manager',
            'role-assign users with role site_manager',
            'cancel users with role site_owner',
            'edit users with role site_owner',
            'view users with role site_owner',
            'role-assign users with role site_owner',
        ];

        // Update permissions for site_manager.
        $role = \Drupal\user\Entity\Role::load('site_manager');
        if ($role) {
            foreach ($site_manager_permissions as $permission) {
                if (!$role->hasPermission($permission)) {
                    $role->grantPermission($permission);
                }
            }
            $role->save();
            $this->logger()->notice("Updated permissions for site_manager role.");
        } else {
            $this->logger()->error("site_manager role does not exist.");
        }
    }
}
