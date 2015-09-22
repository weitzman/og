<?php

/**
 * @file
 * Contains \Drupal\og\Plugin\Field\FieldWidget\OgComplex.
 */

namespace Drupal\og\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityInterface;
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
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $parent = parent::formElement($items, $delta, $element, $form, $form_state);
    $parent['target_id']['#selection_handler'] = 'default:og';
    return $parent;
  }

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

    $entity = \Drupal::entityManager()
      ->getStorage($this->getFieldSetting('target_type'))
      ->load($matches[1]);

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
    $this->otherGroupsWidget($parent_form['other_groups'], $form_state);
    return $parent_form;
  }

  /**
   * Adding the other groups widget to the form.
   *
   * @param $elements
   *   The widget array.
   */
  private function otherGroupsWidget(&$elements, FormStateInterface $form_state) {
    // @todo check permission.
    if ($this->fieldDefinition->getTargetEntityTypeId() == 'user') {
      $description = $this->t('As groups administrator, associate this user with groups you do <em>not</em> belong to.');
    }
    else {
      $description = $this->t('As groups administrator, associate this content with groups you do <em>not</em> belong to.');
    }

    $elements = [
      '#type' => 'container',
      '#title' => $this->t('Other widgets'),
      '#description' => $description,
      '#prefix' => '<div id="og-group-ref-other-groups">',
      '#suffix' => '</div>',
      '#cardinality' => -1, // todo: check cardinality.
      '#cardinality_multiple' => 1, // todo: check cardinality.
      '#theme' => 'field_multiple_value_form',
      '#field_name' => $this->fieldDefinition->getName(),
    ];

    $elements[] = $this->otherGroupsSingle();
  }

  /**
   * Generating other groups autocomplete element.
   *
   * @param EntityInterface|NULL $entity
   *   The entity object.
   * @return array
   *   A single entity reference input.
   */
  public function otherGroupsSingle(EntityInterface $entity = NULL) {
    return [
      'target_id' => [
        '#type' => "entity_autocomplete",
        '#target_type' => $this->fieldDefinition->getTargetEntityTypeId(),
        '#selection_handler' => 'default:og',
        '#default_value' => $entity ? $entity : NULL,
      ],
      '_weight' => [
        '#type' => 'weight',
        '#title_display' => 'invisible',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $parent_values = $values;

    // Get the groups from the other groups widget.
    foreach ($form[$this->fieldDefinition->getName()]['other_groups'] as $key => $value) {
      if (!is_int($key)) {
        continue;
      }

      preg_match("/.+\(([\w.]+)\)/", $value['target_id']['#value'], $matches);

      if (empty($matches[1])) {
        continue;
      }

      $parent_values[] = [
        'target_id' => $matches[1],
        '_weight' => $value['_weight']['#value'],
        '_original_delta' => $value['_weight']['#delta'],
      ];
    }
    return $parent_values;
  }

}
