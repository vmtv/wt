<?php
/**
 * @file
 * Contains \Drupal\wt_common\Controller\WtCommonController.
 */
namespace Drupal\wt_common\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Language\Language;
use Drupal\node\Entity\Node;
use Drupal\wt_common\Imdb\TitleSearchAdvanced;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\Request;

class WtCommonController extends ControllerBase {

  public function search_movies() {
    $request = \Drupal::request();
    $config = \Drupal::config('config.wt_common');
    $language = \Drupal::languageManager()->getCurrentLanguage();
    $search_params = [];

    foreach (['type', 'genre', 'year', 'min_rating','keys'] as $field) {
      $value = trim($request->query->get($field, ''));

      if (!empty($value)) {
        $search_params[$field] = $value;
      }
    }

    $page = (int) $request->query->get('page', '');
    $page = ($page > 1) ? $page : 1;
    $search_params['page'] = $page;

    $user = \Drupal::currentUser();
    $search_type = ($user->hasPermission('administer wt_common')) ? 'admin' : 'user';

    $list = $this->{'search_movies_' . $search_type}($request, $config, $language, $search_params);

    $build = [
      '#theme' => 'wt_common_movies_list',
      '#list' => $list,
      '#page' => $page,
      '#search_type' => $search_type,
      '#attached' => [
        'library' => [
          'wt_common/wt_common',
        ],
      ],
      '#cache' => [
        'contexts' => ['url.query_args', 'user.permissions'],
      ],
    ];

    if ($request->isXmlHttpRequest() && $request->isMethod('POST')) {
      $build['#is_ajax'] = TRUE;
      $rendered = \Drupal::service('renderer')->renderRoot($build);
      $response = new CacheableResponse($rendered);
      $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));

      return $response;
    }

    return $build;
  }

  /**
   * @param Request $request
   * @param $config ImmutableConfig
   * @param Language $language
   * @param array $search_params
   */
  private function search_movies_admin(
    Request $request,
    ImmutableConfig $config,
    Language $language,
    array $search_params
  ) {
    $imdb_config = new \Imdb\Config();
    $imdb_config->language = $language->getId();
    $search = new TitleSearchAdvanced($imdb_config);

    // Page.
    $search->setPage($search_params['page']);

    // Type.
    $title_types = (isset($search_params['type'])) ? [$search_params['type']] : [
      TitleSearchAdvanced::MOVIE,
      TitleSearchAdvanced::TV_MOVIE,
      TitleSearchAdvanced::DOCUMENTARY,
    ];
    $search->setTitleTypes($title_types);

    // Another filters.
    foreach ([
      'genre' => 'Genre',
      'year' => 'Year',
      'min_rating' => 'UserRating',
      'keys' => 'Title',
    ] as $field => $method_suffix) {
      if (isset($search_params[$field])) {
        $value = $search_params[$field];
        $value = ($field == 'min_rating') ? [$value, ''] : $value;
        $search->{'set' . $method_suffix}($value);
      }
    }

    $imdb_search_limit = (int) $config->get('imdb_search_limit');

    if ($imdb_search_limit > 0) {
      $search->setCount($imdb_search_limit);
    }

    $list = $search->search();

    if (!empty($list)) {
      $select_options = wt_common_get_movie_node_select_options();
      $imdb_ids = array_keys($list);
      $exists = wt_common_get_existing_movie_nids_by_imdb_ids($imdb_ids);

      foreach ($list as $imdbid => $item) {
        if (!isset($exists[$imdbid])) {
          $node = Node::create([
            'type' => 'movie',
            'title' => $item['title'],
            'field_thumbnail_url' => $item['thumbnail_url'],
            'field_year' => $item['year'],
            'field_rating' => $item['rating'],
            'field_length' => $item['length'],
            'field_imdb_id' => $item['imdbid'],
            'status' => NODE_NOT_PUBLISHED,
            'promote' => NODE_NOT_PROMOTED,
          ]);

          foreach ($select_options as $field => $values) {
            if (!empty($item[$field])) {
              $field_values = explode(",", $item[$field]);

              foreach ($field_values as $k => $v) {
                if (($key = array_search(trim($v), $values)) !== FALSE) {
                  $field_values[$k] = ['value' => $key];
                }
                else {
                  unset($field_values[$k]);
                }
              }

              if (!empty($field_values)) {
                $field_values = ($field != 'type') ? $field_values : array_slice($field_values, 0, 1);
                $node->set('field_' . $field, $field_values);
              }
            }
          }

          $video = wt_common_set_movie_node_video($node, [], $config);
          $video_thumbnail_url = (!empty($video)) ? $video['thumbnail'] : '';

          $node->save();
          $nid = $node->id();
          $status = (int) $node->isPublished();
        }
        else {
          $nid = $exists[$imdbid]['nid'];
          $status = $exists[$imdbid]['status'];
          $video_thumbnail_url = '';

          if (!empty($exists[$imdbid]['video_thumbnail_url'])) {
            $video_thumbnail_url = $exists[$imdbid]['video_thumbnail_url'];
          }
          else {
            $providers = array_keys(array_filter($exists[$imdbid], function($item) {
              return ($item == 'api_error');
            }));

            if (!empty($providers)) {
              $node = Node::load($nid);
              $video = wt_common_set_movie_node_video($node, $providers, $config);
              $video_thumbnail_url = (!empty($video)) ? $video['thumbnail'] : '';
              $node->save();
            }
          }
        }

        if (!empty($video_thumbnail_url)) {
          $list[$imdbid]['nid'] = $nid;
          $list[$imdbid]['status'] = $status;
          $list[$imdbid]['video_thumbnail_url'] = $video_thumbnail_url;
        }
        else {
          unset($list[$imdbid]);
        }
      }
    }

    return $list;
  }

  /**
   * @param Request $request
   * @param $config ImmutableConfig
   * @param Language $language
   * @param array $search_params
   */
  private function search_movies_user(
    Request $request,
    ImmutableConfig $config,
    Language $language,
    array $search_params
  ) {
    $index = Index::load('movie');
    $list = [];

    if (!empty($index)) {
      $query = $index->query();
      $query->setLanguages([$language->getId()]);

      $user_search_items_per_page = (int) $config->get('user_search_items_per_page');
      $user_search_items_per_page = ($user_search_items_per_page > 0) ? $user_search_items_per_page : 12;
      $query->range((($search_params['page'] - 1) * $user_search_items_per_page), $user_search_items_per_page);

      foreach ([
        'type' => ['field_type', '='],
        'genre' => ['field_genre', '='],
        'year' => ['field_year', '='],
        'min_rating' => ['field_rating', '>='],
      ] as $field => $info) {
        if (isset($search_params[$field])) {
          $query->addCondition($info[0], $search_params[$field], $info[1]);
        }
      }

      if (isset($search_params['keys'])) {
        $parse_mode = \Drupal::service('plugin.manager.search_api.parse_mode')->createInstance('direct');
        $parse_mode->setConjunction('OR');
        $query->setParseMode($parse_mode);
        $query->keys($search_params['keys']);
        $query->setFulltextFields(['title']);
        $query->sort('search_api_relevance', 'DESC');
      }
      else {
        $query->sort('title');
      }

      $query->addCondition('status', NODE_PUBLISHED);
      $results = $query->execute()->getResultItems();

      foreach ($results as $res) {
        $node = $res->getOriginalObject()->getEntity();

        if ($node instanceof Node && $node->getType() == 'movie') {
          $nid = $node->id();

          $item = [
            'nid' => $nid,
            'status' => (int) $node->isPublished(),
            'title' => $node->getTitle(),
          ];

          foreach (['year', 'rating', 'length', 'thumbnail_url', 'video_thumbnail_url'] as $field) {
            $value = $node->get('field_' . $field)->getValue();
            $value = (!empty($value)) ? $value[0]['value'] : '';
            $item[$field] = $value;
          }

          $list[$nid] = $item;
        }
      }
    }

    return $list;
  }
}
