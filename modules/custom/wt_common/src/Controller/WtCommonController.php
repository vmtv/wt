<?php
/**
 * @file
 * Contains \Drupal\wt_common\Controller\WtCommonController.
 */
namespace Drupal\wt_common\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\wt_common\Imdb\TitleSearchAdvanced;

class WtCommonController extends ControllerBase {

  public function search_movies() {
    $request = \Drupal::request();
    $config = \Drupal::config('config.wt_common');
    $language = \Drupal::languageManager()->getCurrentLanguage();
    $imdb_search_limit = (int) $config->get('imdb_search_limit');

    $imdb_config = new \Imdb\Config();
    $imdb_config->language = $language->getId();
    $search = new TitleSearchAdvanced($imdb_config);

    // Page.
    $page = (int) $request->query->get('page', '');
    $page = ($page > 1) ? $page : 1;
    $search->setPage($page);

    // Type.
    $title_type = $request->query->get('type', '');
    $title_types = (!empty($title_type)) ? [$title_type] : [
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
      $value = trim($request->query->get($field, ''));

      if (!empty($value)) {
        $value = ($field == 'min_rating') ? [$value, ''] : $value;
        $search->{'set' . $method_suffix}($value);
      }
    }

    if ($imdb_search_limit > 0) {
      $search->setCount($imdb_search_limit);
    }

    $list = $search->search();

    if (!empty($list)) {
      $select_options = wt_common_get_movie_node_select_options();
      $imdb_ids = array_keys($list);
      $exists = wt_common_get_existing_movie_nids($imdb_ids);

      foreach ($list as $imdbid => $item) {
        if (!isset($exists[$imdbid])) {
          $node = Node::create([
            'type' => 'movie',
            'title' => $item['title'],
            'field_thumbnail_url' => $item['thumbnail'],
            'field_year' => $item['year'],
            'field_rating' => $item['rating'],
            'field_length' => $item['length'],
            'field_imdb_id' => $item['imdbid'],
            'status' => 1,
            'promote' => 0,
            'uid' => 1,
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
          $video_thumbnail = (!empty($video)) ? $video['thumbnail'] : '';

          $node->save();
          $nid = $node->id();
        }
        else {
          $nid = $exists[$imdbid]['nid'];
          $video_thumbnail = '';

          if (!empty($exists[$imdbid]['video_thumbnail'])) {
            $video_thumbnail = $exists[$imdbid]['video_thumbnail'];
          }
          else {
            $providers = array_keys(array_filter($exists[$imdbid], function($item) {
              return ($item == 'api_error');
            }));

            if (!empty($providers)) {
              $node = Node::load($nid);
              $video = wt_common_set_movie_node_video($node, $providers, $config);
              $video_thumbnail = (!empty($video)) ? $video['thumbnail'] : '';
              $node->save();
            }
          }
        }

        if (!empty($video_thumbnail)) {
          $list[$imdbid]['nid'] = $nid;
          $list[$imdbid]['video_thumbnail'] = $video_thumbnail;
        }
        else {
          unset($list[$imdbid]);
        }
      }
    }

    $build = [
      '#theme' => 'wt_common_movies_list',
      '#list' => $list,
      '#page' => $page,
      '#attached' => [
        'library' => [
          'wt_common/wt_common',
        ],
      ],
      '#cache' => [
        'contexts' => ['url.query_args'],
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
}
