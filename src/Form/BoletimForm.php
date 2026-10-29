<?php

namespace Drupal\boletim\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\Entity\Node;
use Drupal\boletim\Utils;
use Drupal\Core\Datetime\DrupalDateTime;
use \Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

class BoletimForm extends FormBase {

  private $numero;

  const bundles = [
    ['noticias','Destaques','field_data_de_publicacao','last week saturday','now'],
    ['eventos','Eventos','field_inicio','now', 'Friday next week'],
    ['defesas','Próximas defesas','field_data_horario','now','Friday next week'],
    ['clipping','FFLCH na mídia','changed','last week saturday', 'now']
  ];

  public function getFormId() {
    return 'boletim_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $date = new DrupalDateTime();
    $this->numero = Utils::getNumeroBoletim();    
    $form['boletim']['title'] = array(
      '#type' => 'textfield',
      '#title' => t('Título'),
      '#default_value' => 'Boletim Acontece na FFLCH USP nº ' . $this->numero . ' - ' . $date->format('d/m/Y'),
    );
    
    foreach(self::bundles as $arr){
      $form['boletim'][] = Utils::getNodes($arr[0],$arr[1],$arr[2],$arr[3],$arr[4]);
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#prefix' => '<br><br>',
      '#value' => $this->t('Criar boletim'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

    /*$x = $form_state->getValue('um_texto_qualquer');
    if($x == 'José'){
      $form_state->setErrorByName('um_texto_qualquer',$this->t('José não vai...'));
    }*/
    
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    $timezone = \Drupal::currentUser()->getTimeZone();
    $config = \Drupal::config('boletim.settings');
    $body = $config->get('header.value');
    $old_date = null;

    foreach(self::bundles as $arr){
      $body .= "<b>$arr[1]</b><hr>";
      $nids = array_filter($form_state->getValue($arr[0]));
      $nodes = Node::loadMultiple(array_keys($nids));
      foreach ($nodes as $node) {
        $options = ['absolute' => TRUE];
        $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', 
                                 ['node' => $node->nid->value], $options);
        $url = $url->toString();
        $link = "<a href='{$url}'>{$node->title->value}</a><br>";
        if($arr[0] == 'noticias') { 
          if(!empty($node->get('field_imagem')->getValue())) {
            $uri = $node->field_imagem->entity->getFileUri();
	    $img_path = file_create_url($uri);
            $body .= "<img width='400' src='{$img_path}'><br>";
          }
          $body .= $link;
          if($node->field_linha_fina->value) {          
             $body .= Utils::removeTags($node->field_linha_fina->value) . "<br>";
          }
        }
        if($arr[0] == 'clipping'){
          $data = $node->field_data_de_publicacao_clippin->value;
          $date = new DrupalDateTime($data, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
          $date = $date->format('d/m/Y');
	  $artigo_uri = "<a href='{$node->field_link_da_materia_artigo->uri}'>{$node->title->value}</a>";
          $veiculo = $node->field_nome_do_veiculo->value;
          $resumo = Utils::removeTags($node->field_resumo->value);
          if(is_null($old_date) || $old_date != $date) {
             $body .= "$date<br>";
             $old_date = $date;  
          }
          $body .= "$artigo_uri ($veiculo)<br>$resumo<br>";
        }
	if($arr[0] == 'eventos'){
          $field_inicio = $node->field_inicio->value;
          $field_inicio = new DrupalDateTime($field_inicio, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
          $field_inicio->setTimezone(new \DateTimeZone($timezone));
          $field_inicio = $field_inicio->format('d/m/Y \- H:i');
          $body .= "$link$field_inicio<br>";
          if ($node->field_inscricao->value == 'cominscricao') {
            $field_inicio_inscricao = $node->field_inicio_inscricao->value;
            $field_inicio_inscricao = new DrupalDateTime($field_inicio_inscricao, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
            $field_inicio_inscricao = $field_inicio_inscricao->format('d/m/Y');
            $field_fim_inscricao = $node->field_fim_inscricao->value;
            $field_fim_inscricao = new DrupalDateTime($field_fim_inscricao, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
            $field_fim_inscricao = $field_fim_inscricao->format('d/m/Y');
            $body .= "Inscrições: $field_inicio_inscricao - $field_fim_inscricao<br>";
          }
        }
        if($arr[0] == 'defesas'){
          $data_horario = $node->field_data_horario->value;
          $data_horario = new DrupalDateTime($data_horario, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
          $data_horario->setTimezone(new \DateTimeZone($timezone));
          $date = $data_horario->format('d/m/Y');
          $hora = $data_horario->format('H:i');
          if(is_null($old_date) || $old_date != $date) {
             $body .= "$date<br>";
             $old_date = $date;  
          }
          $body .=  $hora . "<br>" . $link . "Programa: " . $node->field_programa->value . "<br>";
        }
        $body .= "<br>";
      }

    }

    $body .= $config->get('footer.value');

    $values = [
      'type' => 'boletim',
      'title' => $form_state->getValue('title'),
      'moderation_state' => 'published',
      'langcode' => 'pt-br',
      'body' => [['value' => $body, 'format' => 'full_html']],
      'field_numero' => $this->numero,
      'uid' => \Drupal::currentUser()->id(),
    ];

    $node = \Drupal::service('entity_type.manager')->getStorage('node')->create($values);
    $node->save();

    $url = Url::fromRoute('entity.node.edit_form', ['node' => $node->nid->value])->toString();
    $response = new RedirectResponse($url);
    $response->send();

  }

}
