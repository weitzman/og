<?php

/**
 * @file
 * Contains \Drupal\og\Plugin\Field\FieldWidget\OgComplex.
 */

namespace Drupal\og\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'entity_reference autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "og_complex",
 *   label = @Translation("OG reference"),
 *   description = @Translation("An autocompletewidget for OG"),
 *   field_types = {
 *     "og_membership_reference"
 *   }
 * )
 */
class OgComplex extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    // todo: issue #2 in OG 8 issue queue.
    $elements = parent::formMultipleElements($items, $form, $form_state);

    return $elements;
  }

  /**
   * Override the parent method. Additional to the entity reference validation
   * there is another validation: check if the given entities are groups.
   *
   * A user can change the ID in the brackets easily and reference the group
   * content to a non-group entity.
   */
  public function elementValidate($element, FormStateInterface $form_state, $form) {
    parent::elementValidate($element, $form_state, $form);

    preg_match("/.+\(([\w.]+)\)/", $element['#value'], $matches);

    if (!$matches[1]) {
      return;
    }

    $entity = entity_load($this->getFieldSetting('target_type'), $matches[1]);

    $params['%label'] = $entity->label();

    if (!$entity->hasField(OG_GROUP_FIELD)) {
      $form_state->setError($element, t('The entity %label is not defined as a group.', $params));
      return;
    }

    if (!$entity->get(OG_GROUP_FIELD)->value) {
      $form_state->setError($element, t('The entity %label is not a group.', $params));
      return;
    }

    // todo: Check the writing permission for the current user.
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $parent_form = parent::form($items, $form, $form_state, $get_delta);
    $this->otherGroupsWidget($parent_form['other_groups']);
    return $parent_form;
  }

  /**
   * Adding the other groups widget to the form.
   *
   * @param $elements
   *   The widget array.
   */
  private function otherGroupsWidget(&$elements) {
    // todo: check permission.
    if ($this->fieldDefinition->getTargetEntityTypeId() == 'user') {
      $description = $this->t('As groups administrator, associate this user with groups you do <em>not</em> belong to.');
    }
    else {
      $description = $this->t('As groups administrator, associate this content with groups you do <em>not</em> belong to.');
    }

    $elements['other_groups'] = [
      '#type' => 'fieldset',
      '#title' => t('Other groups'),
      '#description' => $description,
    ];
  }

}
