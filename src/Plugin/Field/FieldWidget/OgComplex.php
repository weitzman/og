<?php

/**
 * @file
 * Contains \Drupal\og\Plugin\Field\FieldWidget\OgComplex.
 */

namespace Drupal\og\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
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
   * Get the field definition property.
   *
   * @return FieldDefinitionInterface
   */
  public function getFieldDefinition() {
    return $this->fieldDefinition;
  }

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
      $parent_form['other_groups'] = $this->otherGroupsWidget($items, $form_state);
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
    $field_name = $this->fieldDefinition->getName();
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $parents = $form['#parents'];

    $target_type = $this->fieldDefinition->getTargetEntityTypeId();
    $user_groups = Og::getEntityGroups(User::load(\Drupal::currentUser()->id()));
    $user_groups_target_type = isset($user_groups[$target_type]) ? $user_groups[$target_type] : [];
    $user_group_ids = array_map(function($group) {
      return $group->id();
    }, $user_groups_target_type);

    $widget_id = OgGroupAudienceHelper::getWidgets(
      $this->fieldDefinition->getTargetEntityTypeId(),
      $this->fieldDefinition->getTargetBundle(),
      $this->fieldDefinition->getName(),
      'default'
    );

    $element = [
      '#required' => $this->fieldDefinition->isRequired(),
      '#multiple' => $cardinality !== 1,
      '#title' => $this->fieldDefinition->getLabel(),
      '#description' => FieldFilteredMarkup::create(\Drupal::token()->replace($this->fieldDefinition->getDescription())),
    ];

    $widget = OgGroupAudienceHelper::renderWidget($this->fieldDefinition, $widget_id)->formElement($items, 0, $element, $form, $form_state);

    if ($widget_id == 'entity_reference_autocomplete_tags') {
      // The auto complete tags widget return the form element wrapped in
      // 'target_id' key. If the element won't be extracted the selected groups
      // will not processed correct.
      $widget = $widget['target_id'];
    }

    $widget = $widget + $element;

    if ($widget_id == 'entity_reference_autocomplete') {
      OgGroupAudienceHelper::autoCompleteHelper($widget, $this, $cardinality, $field_name, $form, $form_state, $user_group_ids, $parents);
    }

    return $widget;
  }

  /**
   * Adding the other groups widget to the form.
   *
   * @param $elements
   *   The widget array.
   */
  protected function otherGroupsWidget(FieldItemListInterface $items, FormStateInterface $form_state) {
    if ($this->fieldDefinition->getTargetEntityTypeId() == 'user') {
      $description = $this->t('As groups administrator, associate this user with groups you do <em>not</em> belong to.');
    }
    else {
      $description = $this->t('As groups administrator, associate this content with groups you do <em>not</em> belong to.');
    }

    $field_wrapper = Html::getClass($this->fieldDefinition->getName()) . '-add-another-group';

    $elements = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#title' => $this->t('Other groups'),
      '#description' => $description,
      '#prefix' => '<div id="' . $field_wrapper . '">',
      '#suffix' => '</div>',
      '#cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      '#cardinality_multiple' => TRUE,
      '#theme' => 'field_multiple_value_form',
      '#field_name' => $this->fieldDefinition->getName(),
      '#max_delta' => 1,
    ];

    $elements['add_more'] = [
      '#type' => 'button',
      '#value' => $this->t('Add another item'),
      '#name' => 'add_another_group',
      '#ajax' => [
        'callback' => [$this, 'addMoreAjax'],
        'wrapper' => $field_wrapper,
        'effect' => 'fade',
      ],
    ];

    $delta = 0;

    $target_type = $this->fieldDefinition->getTargetEntityTypeId();

    $user_groups = Og::getEntityGroups(User::load(\Drupal::currentUser()->id()));
    $user_groups_target_type = isset($user_groups[$target_type]) ? $user_groups[$target_type] : [];
    $user_group_ids = array_map(function($group) {
      return $group->id();
    }, $user_groups_target_type);

    $other_groups_weight_delta = round(count($user_groups) / 2);

    foreach ($items->referencedEntities() as $group) {
      if (in_array($group->id(), $user_group_ids)) {
        continue;
      }

      $elements[$delta] = $this->otherGroupsSingle($delta, $group, $other_groups_weight_delta);
      $delta++;
    }

    if (!$form_state->get('other_group_delta')) {
      $form_state->set('other_group_delta', $delta);
    }

    // Get the trigger element and check if this the add another item button.
    $trigger_element = $form_state->getTriggeringElement();

    if ($trigger_element['#name'] == 'add_another_group') {
      // Increase the number of other groups.
      $delta = $form_state->get('other_group_delta') + 1;
      $form_state->set('other_group_delta', $delta);
    }

    // Add another auto complete field.
    for ($i = $delta; $i <= $form_state->get('other_group_delta'); $i++) {
      // Also add one to the weight delta, just to make sure.
      $elements[$i] = $this->otherGroupsSingle($i, NULL, $other_groups_weight_delta + 1);
    }

    return $elements;
  }

  /**
   * Generating other groups auto complete element.
   *
   * @param $delta
   *   The delta of the new element. Need to be the last delta in order to be
   *   added in the end of the list.
   * @param EntityInterface|NULL $entity
   *   The entity object.
   * @return array
   *   A single entity reference input.
   */
  public function otherGroupsSingle($delta, EntityInterface $entity = NULL, $weight_delta = 10) {
    return [
      'target_id' => [
        // @todo Allow this to be configurable with a widget setting.
        '#type' => 'entity_autocomplete',
        '#target_type' => $this->fieldDefinition->getTargetEntityTypeId(),
        '#selection_handler' => 'og:default',
        '#selection_settings' => [
          'other_groups' => TRUE,
          'field_mode' => 'admin',
        ],
        '#default_value' => $entity,
      ],
      '_weight' => [
        '#type' => 'weight',
        '#title_display' => 'invisible',
        '#delta' => $weight_delta,
        '#default_value' => $delta,
      ],
    ];
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

  /**
   * Wrapping formSingleElement method.
   */
  public function getFormSingleElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    return $this->formSingleElement($items, $delta, $element, $form, $form_state);
  }

}
