<?php

namespace Drupal\boletim;

use Drupal\node\Entity\Node;
use Drupal\Core\Datetime\DrupalDateTime;
use \Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

class Utils{

  public static function getNodes($bundle, $title ,$after_field = 'changed', $from = 'now', $to = '+14 days'){

    $timezone = \Drupal::currentUser()->getTimeZone();
    $start = new \DateTime($from, new \DateTimeZone($timezone));
    $start->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $start->setTime(21,0);
    $start = DrupalDateTime::createFromDateTime($start);
    $end = new \DateTime($to, new \DateTimeZone($timezone));
    $end->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $end->setTime(23, 59);
    $end = DrupalDateTime::createFromDateTime($end);
    $attributes = array('id' => 'notsortable');

    $query = \Drupal::entityQuery('node')
             ->condition('type',$bundle);

    if ($after_field == 'field_data_de_publicacao') {
        $start = $start->format('Y-m-d');
        $end = $end->format('Y-m-d');
        $query->condition('status', 1);
        $attributes['id'] = 'sortable';
    }
    elseif ($after_field == 'changed') {
        $start = strtotime($start);
        $end = strtotime($end);
        $query->condition('field_publicar_no_fflch_na_midia', 1);
        $query->sort('field_data_de_publicacao_clippin', 'ASC');
        $attributes['id'] = 'sortable';
    }
    elseif ($after_field == 'field_data_horario') {
        $start = $start->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
        $end = $end->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
        $query->sort('field_data_horario', 'ASC');
    }

    else {
        $start = $start->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
        $end = $end->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    }

    $query->condition($after_field, $start, '>=');
    $query->condition($after_field, $end, '<=');
    $nids = $query->execute();
    
    $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);

    $aux = [];
    foreach ($nodes as $node) {
      $updated = DrupalDateTime::createFromTimestamp($node->changed->value);
      if($after_field == 'field_data_de_publicacao') {
        $data = $node->field_data_de_publicacao->value;
        $data = new DrupalDateTime($data, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
        $aux[$node->nid->value] = [
           'title' => $node->title->value, 
           'field_data_de_publicacao' => $data->format('d/m/Y'),
           'Atualizado' => $updated->format('d/m/Y'),
        ];
      }
      elseif($after_field == 'changed') {
        $data = $node->field_data_de_publicacao_clippin->value;
        $data = new DrupalDateTime($data, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
        $aux[$node->nid->value] = [
           'title' => $node->title->value, 
           'changed' => $data->format('d/m/Y'),
           'Atualizado' => $updated->format('d/m/Y'),
           'Veiculo' => $node->field_nome_do_veiculo->value,
	];
      }
      else {
        $data = $node->{$after_field}->value;
        $data = new DrupalDateTime($data, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
        $data->setTimezone(new \DateTimeZone($timezone));
        $aux[$node->nid->value] =  [
           'title' => $node->title->value,
           $after_field => $data->format('d/m/Y'),
           'Atualizado' => $updated->format('d/m/Y'),
        ];
       }
    }

    $header_fields = [
	'field_data_de_publicacao' => 'Data de Publicação',
	'field_inicio' => 'Início',
	'field_data_horario' => 'Date',
	'changed' => 'Data de Publicação no Veículo',
    ];

    $form = [];

    $form[$bundle .'-title'] = [
      '#markup' => "<br><div><h2>{$title}</h2></div>",
    ];

    $header = [
      'title' => t('Título'),
      $after_field => t($header_fields[$after_field]),
      'Atualizado' => t('Atualizado'),
    ]; 

    if($after_field == 'changed') {
      $header['Veiculo'] = t('Veículo');
    }

    $form[$bundle] = [
      '#type' => 'tableselect',
      '#header' => $header, 
      '#options' => $aux,
      '#empty' => t('Nenhum conteúdo encontrado!'),
      '#attributes' => $attributes,
    ];
    return $form;

  }

  public static function getNumeroBoletim(){
    $query = \Drupal::entityQuery('node')
           ->condition('type','boletim')
           ->range(0,1)
           ->sort('created', 'DESC')
           ->execute();

    $boletins = \Drupal\node\Entity\Node::loadMultiple($query);
    foreach($boletins as $boletim){
        $numero = $boletim->field_numero->value ? (int)$boletim->field_numero->value + 1 : 1;
    }
    return $numero;
  }

  public static function removeTags($string){
    $string = str_replace(["\r", "\n"], "", $string);
    return strip_tags($string);
  }

}
