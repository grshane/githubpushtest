<?php

namespace Drupal\yamlform\Plugin\YamlFormElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\filter\Entity\FilterFormat;
use Drupal\yamlform\YamlFormElementBase;
use Drupal\yamlform\YamlFormSubmissionInterface;

/**
 * Provides a 'text_format' element.
 *
 * @YamlFormElement(
 *   id = "text_format",
 *   api = "https://api.drupal.org/api/drupal/core!modules!filter!src!Element!TextFormat.php/class/TextFormat",
 *   label = @Translation("Text format"),
 *   category = @Translation("Advanced"),
 *   multiline = TRUE
 * )
 */
class TextFormat extends YamlFormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    return parent::getDefaultProperties() + [
      'allowed_formats' => [],
      'hide_help' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, YamlFormSubmissionInterface $yamlform_submission) {
    parent::prepare($element, $yamlform_submission);
    $element['#after_build'] = [[$this, 'afterBuild']];
  }

  /**
   * Alter the 'text_format' element after it has been built.
   *
   * @param array $element
   *   An element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The element.
   */
  public function afterBuild(array $element, FormStateInterface $form_state) {
    if (empty($element['format'])) {
      return $element;
    }

    // Hide tips.
    if (!empty($element['#hide_help']) && isset($element['format']['help'])) {
      $element['format']['help']['#attributes']['style'] = 'display: none';
    }

    // Hide filter format if the select menu and help is hidden.
    if (!empty($element['#hide_help']) &&
      isset($element['format']['format']['#access']) && $element['format']['format']['#access'] === FALSE) {
      // Can't hide the format via #access but we can use CSS.
      $element['format']['#attributes']['style'] = 'display: none';
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultValue(array &$element) {
    if (isset($element['#default_value']) && is_array($element['#default_value'])) {
      $element['#format'] = $element['#default_value']['format'];
      $element['#default_value'] = $element['#default_value']['value'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formatHtml(array &$element, $value, array $options = []) {
    if (isset($value['value']) && isset($value['format'])) {
      return check_markup($value['value'], $value['format']);
    }
    else {
      return $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formatText(array &$element, $value, array $options = []) {
    if (isset($value['value']) && isset($value['format'])) {
      $html = check_markup($value['value'], $value['format']);
      // Convert any HTML to plain-text.
      $html = MailFormatHelper::htmlToText($html);
      // Wrap the mail body for sending.
      $html = MailFormatHelper::wrapMail($html);
      return $html;
    }
    else {
      return $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormat() {
    return filter_default_format();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormats() {
    $filters = FilterFormat::loadMultiple();
    $formats = parent::getFormats();
    foreach ($filters as $filter) {
      $formats[$filter->id()] = $filter->label();
    }
    return $formats;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $filters = FilterFormat::loadMultiple();
    $options = [];
    foreach ($filters as $filter) {
      $options[$filter->id()] = $filter->label();
    }
    $form['text_format'] = [
      '#type' => 'details',
      '#title' => $this->t('Text format settings'),
      '#open' => TRUE,
    ];
    $form['text_format']['allowed_formats'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed formats'),
      '#description' => $this->t('Please check the formats that are available for this element. Leave blank to allow all available formats.'),
      '#options' => $options,
    ];
    $form['text_format']['hide_help'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide help'),
      '#description' => $this->t("If checked, the 'About text formats' link will be hidden."),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $allowed_formats = $form_state->getValue('allowed_formats');
    $allowed_formats = array_filter($allowed_formats);
    $form_state->setValue('allowed_formats', $allowed_formats);
    parent::validateConfigurationForm($form, $form_state);
  }

}
