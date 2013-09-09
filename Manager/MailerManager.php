<?php

namespace CCDNMessage\MessageBundle\Manager;

/**
 *
 * @author Gaston Caldeiro <chugas488@gmail.com>
 * @version 1.0
 */
class MailerManager {
  
  protected $from;
  
  protected $mailer;
  
  public function __construct($from, $mailer) {
    $this->from = $from;
    $this->mailer = $mailer;
  }
  
  public function send($subject, $body, $from, $to) {
    $swift_message = \Swift_Message::newInstance()
        ->setSubject($subject)
        ->setFrom($this->from, $from)
        ->setContentType('text/html')
        ->setBody($body)
    ;

    $swift_message->setTo($to);
    return $this->mailer->send($swift_message);
  }
}

?>
