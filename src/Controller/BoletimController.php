<?php

namespace Drupal\boletim\Controller;

use Drupal\Core\Controller\ControllerBase;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Html;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

class BoletimController extends ControllerBase {

  public function index() {

    $form = \Drupal::formBuilder()->getForm('Drupal\boletim\Form\BoletimForm');

    return [
      '#theme'   => 'boletim',
      '#form' => $form,
      '#cache' => [
        'max-age' => 8600,
      ],  
      '#attached' => [
        'library' => [
          'boletim/boletim',
        ],
      ],       
    ];

  }

  public function send(NodeInterface $node, String $email) {

    $tos = explode(",", $email);
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'boletim';
    $key = 'boletim_key';
    $params['from_name'] = 'Boletim FFLCH';
    $params['from_mail'] = 'boletimfflch@usp.br';
    $params['subject'] = $node->title->value;
    $params['message'] = $node->body->value;
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = true;

    foreach($tos as $to) {
      $to = trim($to);
      $result = $mailManager->mail($module, $key, $to, $langcode, $params, $reply , $send);
    
      if ($result['result'] != true) {
        $message = t('There was a problem sending your email notification to @email.', array('@email' => $to));
        drupal_set_message($message, 'error');
        \Drupal::logger('boletim')->error($message);
      } else {
        $message = t('An email notification has been sent to @email ', array('@email' => $to));
        drupal_set_message($message);
        \Drupal::logger('boletim')->notice($message);
      }
  
    }  
      $url = Url::fromRoute('entity.node.edit_form', ['node' => $node->nid->value])->toString();
      $response = new RedirectResponse($url);
      $response->send();
  }

}

