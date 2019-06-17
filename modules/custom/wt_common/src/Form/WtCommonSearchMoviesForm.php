<?php
/**
 * @file
 * Contains \Drupal\wt_common\Form\WtCommonSearchMoviesForm.
 */
namespace Drupal\wt_common\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class WtCommonSearchMoviesForm extends FormBase {

  public function getFormId() {
    return 'wt_common_search_movies_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = \Drupal::request();

    $select_options = wt_common_get_movie_node_select_options();
    $select_options['year'] = [];
    $select_options['min_rating'] = [];

    for ($year = date("Y") + 1; $year >= 1970; $year--) {
      $select_options['year'][$year] = $year;
    }

    for ($rating = 10; $rating >= 1; $rating -= 0.1) {
      $rating = number_format($rating, 1);
      $select_options['min_rating'][$rating] = $rating;
    }

    $labels = [
      'type' => $this->t('- Type -'),
      'genre' => $this->t('- Genre -'),
      'year' => $this->t('- Year -'),
      'min_rating' => $this->t('- Min. Rating -'),
    ];

    foreach ($select_options as $field => $values) {
      $form[$field] = [
        '#type' => 'select',
        '#default_value' => $request->query->get($field,''),
        '#options' => ['' => $labels[$field]] + $values,
        '#attributes' => [
          'autocomplete' => 'off',
        ],
        '#wrapper_attributes' => [
          'class' => ['col-md-6', 'col-sm-12', 'col-xs-12'],
        ],
      ];
    }

    $form['keys'] = [
      '#type' => 'textfield',
      '#default_value' => $request->query->get('keys',''),
      '#theme_wrappers' => [],
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => [$this->t('Please type a film name here ...')],
        'autocomplete' => 'off',
      ],
      '#prefix' => '<div class="input-group col-sm-12 mb-3">',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#button_type' => 'primary',
      '#suffix' => '</div>',
    ];

    $form['#action'] = Url::fromRoute('<front>')->toString();
    $form['#prefix'] = '<div class="d-block shadow bg-white rounded mt-3 mb-3 p-3">';
    $form['#suffix'] = '</div>';

    $form['#attached'] = [
      'library' => [
        'wt_common/wt_common',
      ],
    ];

    $form['#cache'] = [
      'contexts' => ['url.query_args'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $params = [];

    foreach (['type', 'genre', 'year', 'min_rating', 'keys'] as $field) {
      $value = trim($form_state->getValue($field));
      (empty($value)) ?: $params[$field] = $value;
    }

    $url = Url::fromRoute('wt_common.search', $params);
    $form_state->setRedirectUrl($url);
  }
}
