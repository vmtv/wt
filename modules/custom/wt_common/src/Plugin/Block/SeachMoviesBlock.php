<?php

namespace Drupal\wt_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\wt_common\Form\WtCommonSearchMoviesForm;

/**
 * Provides a 'Search Movies' Block
 *
 * @Block(
 *   id = "search_movies_block",
 *   admin_label = @Translation("Search Movies Block"),
 * )
 */
class SeachMoviesBlock extends BlockBase {

  protected $entity_manager;
  protected $lang_id;
  protected $user;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $this->entity_manager = \Drupal::entityManager();
    $this->lang_id = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $this->user = \Drupal::currentUser();

    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm(WtCommonSearchMoviesForm::class);
    return $form;
    $menus = [];
    $config = $this->getConfiguration();
    $menu = (isset($config['menu'])) ? $config['menu'] : 'main';
    $image_style = (isset($config['image_style']))
      ? $config['image_style'] : 'crop_194x194';

    $menu_tree = \Drupal::menuTree();
    $entity_manager = \Drupal::entityManager();
    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu);
    $activeTrail = reset($parameters->activeTrail);

    if (!empty($activeTrail)) {
      $parameters->setRoot($activeTrail);
      $parameters->setMinDepth(1);
      $parameters->setMaxDepth(2);
      $tree = $menu_tree->load($menu, $parameters);
      $manipulators = array(
        array('callable' => 'menu.default_tree_manipulators:checkAccess'),
        array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
      );

      $tree = $menu_tree->transform($tree, $manipulators);

      if (!empty($tree)) {
        foreach ($tree as $element) {
          if ($element->hasChildren) {
            $url = $element->link->getUrlObject()->toString();

            if (empty($url)
              && ($entity = $this->getTranslatedMenuLinkEntity($element->link))) {
              $children = [];

              foreach ($element->subtree as $subelement) {
                if ($subentity = $this->getTranslatedMenuLinkEntity($subelement->link)) {
                  $children[] = [
                    'title' => $subentity->getTitle(),
                    'url' => $subentity->getUrlObject(),
                  ];
                }
              }

              if (!empty($children)) {
                $options = $entity->getUrlObject()->getOptions();
                $menu_icon = '';

                if (isset($options['menu_icon']) && isset($options['menu_icon']['fid'])) {
                  $file = File::load($options['menu_icon']['fid']);

                  if (!empty($file)) {
                    $menu_icon = [
                      '#theme' => 'image_style',
                      '#uri' => $file->getFileUri(),
                      '#style_name' => $image_style,
                      '#cache' => [
                        'tags' => ['file:' . $file->id()],
                      ],
                    ];
                  }
                }

                $menus[] = [
                  'title' => $entity->getTitle(),
                  'menu_icon' => $menu_icon,
                  'children' => $children,
                ];
              }
            }
          }
        }
      }
    }

    $cache = [
      '#cache' => [
        'contexts' => ['url.path'],
      ],
    ];

    if (empty($menus)) {
      return $cache;
    }

    return [
      '#theme' => 'wt_common_menu_block',
      '#menus' => $menus,
    ] + $cache;
  }
}
