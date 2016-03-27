<?php

/**
 * @file
 * Contains \Drupal\og\Plugin\Field\FieldWidget\OgComplex.
 */

namespace Drupal\og\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteTagsWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\og\Og;
use Drupal\og\OgAccess;
use Drupal\og\OgGroupAudienceHelper;

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

    $parent_form[$this->fieldDefinition->getFieldStorageDefinition()->getName() . '_other_groups'] = [];

    // Adding the other groups widget.
    if ($this->isGroupAdmin()) {
      $parent_form[$this->fieldDefinition->getFieldStorageDefinition()->getName() . '_other_groups'] = $this->otherGroupsWidget($items, $form, $form_state);
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

    $items_new = $this->getAutoCompleteItems($items, $form, $form_state);
    $multiple = parent::formMultipleElements($items_new, $form, $form_state);

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

    return $widget;
  }

  /**
   * Adding the other groups widget to the form.
   *
   * @param FieldItemListInterface $items
   * @param array $form
   * @param FormStateInterface $form_state
   * @return mixed
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

    $this->fieldDefinition->otherGroup = TRUE;

    $handler = OgGroupAudienceHelper::renderWidget($this->fieldDefinition, $widget_id);
    $items = $this->getAutoCompleteItems($items, $form, $form_state, TRUE);

    $widget = $handler->formElement($items, 0, $element, $form, $form_state);

    if ($handler instanceof EntityReferenceAutocompleteWidget) {
      return $this->AutoCompleteHandler($widget, $handler);
    }

    return $widget;
  }

  /**
   * Creating a custom auto complete widget for the other groups widget.
   *
   * @param $widget
   *   The form API element.
   * @param WidgetBase $handler
   *   The handler object.
   *
   * @return mixed
   *   Form API element.
   */
  protected function AutoCompleteHandler($widget, WidgetBase $handler) {
    // Wrapping the element with array and #tree => TRUE will make sure FAPI
    // will pass the selected group in array. We need this to so for
    // self::massageFormValues() will treat all the other group widget the same.
    $widget = [$widget] + [
      '#tree' => TRUE,
    ];

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

    if (!$widget['target_id']['#multiple']) {
      // Not a field with a multiple cardinality. Return a single element form.
      return $widget;
    }

    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Remove empty values. The form fields may be empty.
    $values = array_filter($values, function ($item) {
      return !empty($item['target_id']);
    });

    dpm($form_state->getValue($this->fieldDefinition->getName() . '_other_groups'));

//    foreach ($form_state->getValue($this->fieldDefinition->getName() . '_other_groups') as $other_group) {
//      $values[] = $other_group['target_id'];
//    }

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

  /**
   * Ajax callback for the "Add another item" button.
   *
   * This returns the new page content to replace the page content made obsolete
   * by the form submission.
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    // Ensure the widget allows adding additional items.
    if ($element['#cardinality'] != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      return;
    }

    // Add a DIV around the delta receiving the Ajax effect.
    $delta = $element['#max_delta'];
    $element[$delta]['#prefix'] = '<div class="ajax-new-content">' . (isset($element[$delta]['#prefix']) ? $element[$delta]['#prefix'] : '');
    $element[$delta]['#suffix'] = (isset($element[$delta]['#suffix']) ? $element[$delta]['#suffix'] : '') . '</div>';

    return $element;
  }

  /**
   * @return FieldItemListInterface
   */
  protected function getAutoCompleteItems(FieldItemListInterface $items, $form, FormStateInterface $form_state, $other_groups = FALSE) {
    $new_items = clone $items;
    // Get the groups which already referenced.
    $referenced_groups_ids = [];

    foreach ($new_items->getEntity()->get($this->fieldDefinition->getName())->referencedEntities() as $entity) {
      $referenced_groups_ids[] = $entity->id();
    }
    $referenced_groups_ids = array_unique($referenced_groups_ids);

    // Get all the entities we can referenced to.
    $field_mode = $other_groups ? 'admin' : 'default';
    $referenceable_groups = Og::getSelectionHandler($this->fieldDefinition, ['handler_settings' => ['field_mode' => $field_mode]])->getReferenceableEntities();

    $gids = [];
    $handler_settings = $this->fieldDefinition->getSetting('handler_settings');
    foreach ($handler_settings['target_bundles'] as $target_bundle) {
      $gids += array_keys($referenceable_groups[$target_bundle]);
    }

    $entity_ids = array_filter($gids, function($gids) use($referenced_groups_ids) {
      return in_array($gids, $referenced_groups_ids) ? $gids : NULL;
    });

    $entities = \Drupal::entityTypeManager()
      ->getStorage($handler_settings['target_type'])
      ->loadMultiple($entity_ids);

    $new_items->setValue($entities);

    if (!$form_state->getTriggeringElement()) {
      $field_state = static::getWidgetState($form['#parents'], $this->fieldDefinition->getName(), $form_state);
      $field_state['items_count'] = count($entities);
      static::setWidgetState($form['#parents'], $this->fieldDefinition->getName(), $form_state, $field_state);
    }

    return $new_items;
  }

}
