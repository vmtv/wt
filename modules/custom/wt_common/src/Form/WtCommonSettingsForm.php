<?php
/**
 * @file
 * Contains \Drupal\wt_common\Form\WtCommonSettingsForm.
 */
namespace Drupal\wt_common\Form;

use Drupal\file\Entity\File;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;

class WtCommonSettingsForm extends ConfigFormBase {

  protected $fields = [];

  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  public function getFormId() {
    return 'wt_common_settings_form';
  }

  protected function getEditableConfigNames() {
    return ['config.wt_common'];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    global $base_url;

    $config = $this->config('config.wt_common');

    $google_api_key = $config->get('google_api_key');

    $form['#tree'] = TRUE;

    $form['imdb_search_limit'] = [
      '#title' => $this->t('IMDB Search Limit'),
      '#description' => $this->t('Limit number of results to be retured by IMDB Api. Default: 50.'),
      '#type' => 'textfield',
      '#default_value' => $config->get('imdb_search_limit'),
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => TRUE,
    ];
    $this->fields[] = 'imdb_search_limit';

    $form['google_api_keys'] = [
      '#title' => $this->t('Google Api Keys'),
      '#type' => 'textarea',
      '#default_value' => $config->get('google_api_keys'),
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => TRUE,
    ];
    $this->fields[] = 'google_api_keys';

    $form['vimeo_client_id'] = [
      '#title' => $this->t('Vimeo Client Id'),
      '#type' => 'textfield',
      '#default_value' => $config->get('vimeo_client_id'),
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => TRUE,
    ];
    $this->fields[] = 'vimeo_client_id';

    $form['vimeo_client_secret'] = [
      '#title' => $this->t('Vimeo Client Secret'),
      '#type' => 'textfield',
      '#default_value' => $config->get('vimeo_client_secret'),
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => TRUE,
    ];
    $this->fields[] = 'vimeo_client_secret';

    $form['vimeo_acceess_token'] = [
      '#title' => $this->t('Vimeo Access Token'),
      '#type' => 'textfield',
      '#default_value' => $config->get('vimeo_acceess_token'),
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => TRUE,
    ];
    $this->fields[] = 'vimeo_acceess_token';

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::service('config.factory')->getEditable('config.wt_common');
    $data = $config->getRawData();

    foreach ($this->fields as $field) {
      $value = $form_state->getValue($field);
      $data[$field] = $value;
    }

    $config->setData($data)->save();
    parent::submitForm($form, $form_state);
  }
}
