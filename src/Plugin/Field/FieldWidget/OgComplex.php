<?php

/**
 * @file
 * Contains \Drupal\og\Plugin\Field\FieldWidget\OgComplex.
 */

namespace Drupal\og\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteTagsWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\og\Og;
use Drupal\og\OgAccess;
use Drupal\og\OgGroupAudienceHelper;
use Drupal\user\Entity\User;

/**
 * Plugin implementation of the 'entity_reference autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "og_complex",
 *   label = @Translation("OG reference"),
 *   description = @Translation("An autocompletewidget for OG"),
 *   field_types = {
 *     "og_standard_reference",
 *     "og_membership_reference"
 *   }
 * )
 */
class OgComplex extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $parent = parent::formElement($items, $delta, $element, $form, $form_state);
    // todo: fix the definition in th UI level.
    $parent['target_id']['#selection_handler'] = 'og:default';
    $parent['target_id']['#selection_settings']['field_mode'] = 'default';

    return $parent;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $parent_form = parent::form($items, $form, $form_state, $get_delta);

    $parent_form['other_groups'] = [];

    // Adding the other groups widget.
    if ($this->isGroupAdmin()) {
      $parent_form['other_groups'] = $this->otherGroupsWidget($items, $form, $form_state);
    }

    return $parent_form;
  }

  /**
   * Special handling to create form elements for multiple values.
   *
   * Handles generic features for multiple fields:
   * - number of widgets
   * - AHAH-'add more' button
   * - table display and drag-n-drop value reordering
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $multiple = parent::formMultipleElements($items, $form, $form_state);

    $widget_id = OgGroupAudienceHelper::getWidgets(
      $this->fieldDefinition->getTargetEntityTypeId(),
      $this->fieldDefinition->getTargetBundle(),
      $this->fieldDefinition->getName(),
      'default'
    );

    $handler = OgGroupAudienceHelper::renderWidget($this->fieldDefinition, $widget_id);

    if ($handler instanceof EntityReferenceAutocompleteWidget) {
      // No need for extra work here since we already extending the auto
      // complete handler.
      return $multiple;
    }

    // Change the functionality of the original handler and call the other
    // handlers.
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();

    $element = [
      '#required' => $this->fieldDefinition->isRequired(),
      '#multiple' => $cardinality !== 1,
      '#title' => $this->fieldDefinition->getLabel(),
      '#description' => FieldFilteredMarkup::create(\Drupal::token()->replace($this->fieldDefinition->getDescription())),
    ];

    $widget = $handler->formElement($items, 0, $element, $form, $form_state);

    if ($handler instanceof EntityReferenceAutocompleteTagsWidget) {
      // The auto complete tags widget return the form element wrapped in
      // 'target_id' key. If the element won't be extracted the selected groups
      // will not processed correct.
      $widget = $widget['target_id'];
    }

    $widget = $widget + $element;

    return [$widget];
  }

  /**
   * Adding the other groups widget to the form.
   *
   * @param $elements
   *   The widget array.
   */
  protected function otherGroupsWidget(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {

    $widget_id = OgGroupAudienceHelper::getWidgets(
      $this->fieldDefinition->getTargetEntityTypeId(),
      $this->fieldDefinition->getTargetBundle(),
      $this->fieldDefinition->getName(),
      'admin'
    );

    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality() !== 1;
    $element = [
      '#required' => $this->fieldDefinition->isRequired(),
      '#multiple' => $cardinality,
      '#title' => $this->fieldDefinition->getLabel(),
      '#description' => FieldFilteredMarkup::create(\Drupal::token()->replace($this->fieldDefinition->getDescription())),
    ];

    $handler = OgGroupAudienceHelper::renderWidget($this->fieldDefinition, $widget_id);

    if ($handler instanceof EntityReferenceAutocompleteWidget && $cardinality) {
      $widget = $handler->formMultipleElements($items, $form, $form_state);
    }
    else {
      $widget = $handler->formElement($items, 0, $element, $form, $form_state);
    }

    return $this->clearGroups($widget, $this->getOtherGroups(), $handler);
  }

  /**
   * When rendering a widget we get the referenced groups. This methods will
   * remove the groups we don't want from the selections.
   *
   * @param $widget
   *   The form API element.
   * @param array $allowed_gids
   *   The groups IDs need to keep.
   * @param WidgetBase $handler
   *   The handler object.
   *
   * @return mixed
   *   Form API element.
   */
  protected function clearGroups($widget, array $allowed_gids, WidgetBase $handler) {

    if ($handler instanceof OptionsButtonsWidget) {
      $widget['#options'] = array_filter($widget['#options'], function($key) use($allowed_gids) {
        return in_array($key, $allowed_gids);
      }, ARRAY_FILTER_USE_KEY);
    }
    elseif ($handler instanceof OptionsSelectWidget) {
      $widget['#options'] = ['_none' => $this->t('- None -')] + array_filter($widget['#options'], function($key) use($allowed_gids) {
          return in_array($key, $allowed_gids);
        }, ARRAY_FILTER_USE_KEY);
    }
    elseif ($handler instanceof EntityReferenceAutocompleteWidget) {
      foreach ($widget as $key => &$value) {
        if (!is_int($key)) {
          continue;
        }

        $value['target_id']['#selection_handler'] = 'og:default';
        $value['target_id']['#selection_settings'] = [
          'other_groups' => TRUE,
          'field_mode' => 'admin',
        ];
      }
    }

    return $widget;
  }

  /**
   * Get the other groups IDs.
   *
   * @return array
   *   Other groups IDs.
   *
   * @throws \Exception
   */
  protected function getOtherGroups() {
    $groups = Og::getSelectionHandler($this->fieldDefinition, ['handler_settings' => ['field_mode' => 'admin']])->getReferenceableEntities();

    $gids = [];

    foreach ($this->getFieldSetting('handler_settings')['target_bundles'] as $target_bundle) {
      $gids += array_keys($groups[$target_bundle]);
    }

    return $gids;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Remove empty values. The form fields may be empty.
    $values = array_filter($values, function ($item) {
      return !empty($item['target_id']);
    });

    // Get the groups from the other groups widget.
    foreach ($form[$this->fieldDefinition->getName()]['other_groups'] as $key => $value) {
      if (!is_int($key)) {
        continue;
      }

      // Matches the entity label and ID. E.g. 'Label (123)'. The entity ID will
      // be captured in it's own group, with the key 'id'.
      preg_match("|.+\((?<id>[\w.]+)\)|", $value['target_id']['#value'], $matches);

      if (!empty($matches['id'])) {
        $values[] = [
          'target_id' => $matches['id'],
          '_weight' => $value['_weight']['#value'],
          '_original_delta' => $value['_weight']['#delta'],
        ];
      }
    }

    return $values;
  }

  /**
   * Determines if the current user has group admin permission.
   *
   * @return bool
   */
  protected function isGroupAdmin() {
    // @todo Inject current user service as a dependency.
    return \Drupal::currentUser()->hasPermission(OgAccess::ADMINISTER_GROUP_PERMISSION);
  }

}
