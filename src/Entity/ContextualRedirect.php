<?php

namespace Drupal\contextual_aliases\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\redirect\Entity\Redirect;

class ContextualRedirect extends Redirect {

  public function preSave(EntityStorageInterface $storage_controller) {
    /** @var \Drupal\contextual_aliases\ContextualAliasStorage $aliasStorage */
    $aliasStorage = \Drupal::service('path.alias_storage');
    $parsed = parse_url($this->redirect_redirect->uri);
    $context = isset($parsed['path']) ? $aliasStorage->getSourceContext($parsed['path']) : NULL;
    if ($context) {
      $this->set('context', $context);
      $this->set('hash', Redirect::generateHash(
        $this->redirect_source->path,
        array_merge((array) $this->redirect_source->query, ['_context' => $context]),
        $this->language()->getId()
      ));
    }
    else if ($this->context->value) {
      $this->set('hash', Redirect::generateHash(
        $this->redirect_source->path,
        array_merge((array) $this->redirect_source->query, ['_context' => $this->context->value]),
        $this->language()->getId()
      ));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['context'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Context'))
      ->setDescription(t('Choose the context this redirect should apply in. <strong>If the target is bound to a context, this value will be overridden!</strong>'))
      ->setSetting('max_length', 32)
      ->setRequired(FALSE)
      ->setSetting('allowed_values_function', 'contextual_aliases_context_options')
      ->setDisplayOptions('form', [
        'type' => 'select',
      ]);
    return $fields;
  }

}