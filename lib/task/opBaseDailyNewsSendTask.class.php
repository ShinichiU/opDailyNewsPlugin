<?php

class opBaseDailyNewsSendTask extends opBaseSendMailTask
{
  protected
    $inactiveMemberIds = null,
    $connection = null,
    $transport = null,
    $sendCount = 0;

  protected function execute($arguments = array(), $options = array())
  {
    parent::execute($arguments, $options);
    $this->connection = Doctrine_Manager::connection();
  }

  protected function getMemberConfig($memberId)
  {
    $value = $this->connection->fetchRow("SELECT value FROM member_config WHERE name='daily_news' AND member_id = ?", array($memberId));

    return $value['value'];
  }

  protected function getMember($memberId)
  {
    return $this->connection->fetchRow("SELECT id, name FROM member WHERE (is_active = 1 OR is_active IS NULL) AND id = ?", array($memberId));
  }

  protected function getInactivaMemberIds()
  {
    if (null !== $this->inactiveMemberIds)
    {
      return $this->inactiveMemberIds;
    }

    $results = array();
    $stmt =  $this->connection->execute('SELECT id FROM member WHERE is_active = 0');
    while ($r = $stmt->fetch(Doctrine::FETCH_NUM))
    {
      $results[] = $r[0];
    }
    $this->inactiveMemberIds = $results;
    return $results;
  }

  protected function getFriendIds($memberId)
  {
    $this->getInactivaMemberIds();

    $results = array();
    $stmt =  $this->connection->execute('SELECT member_id_to FROM member_relationship WHERE member_id_from = ? AND is_friend = 1', array($memberId));
    while ($r = $stmt->fetch(Doctrine::FETCH_NUM))
    {
      if (!in_array($r[0], $this->inactiveMemberIds))
      {
        $results[] = $r[0];
      }
    }
    return $results;
  }

  protected function getMemberPcEmailAddress($memberId)
  {
    $memberConfig = $this->connection->fetchRow("SELECT value FROM member_config WHERE name = 'pc_address' AND member_id = ?", array($memberId));
    if ($memberConfig)
    {
      return $memberConfig['value'];
    }

    $memberConfig = $this->connection->fetchRow("SELECT value FROM member_config WHERE name = 'mobile_address' AND member_id = ?", array($memberId));
    if ($memberConfig)
    {
      return $memberConfig['value'];
    }

    return false;
  }

  protected function getMailTemplate($templateName, $require = false)
  {
    $notificationMail = $this->connection->fetchRow("SELECT id FROM notification_mail WHERE name = ?", array($templateName));

    if ($notificationMail)
    {
      $notificationMailTrans = $this->connection->fetchRow("SELECT title, template FROM notification_mail_translation WHERE id = ? AND lang = 'ja_JP'", array($notificationMail['id']));
      if ($notificationMailTrans)
      {
        return $notificationMailTrans;
      }
    }

    if ($require)
    {
      throw new LogicException(sprintf("Not found template: %s", $templateName));
    }

    return false;
  }

  protected function getTransport()
  {
    if ($host = sfConfig::get('op_mail_smtp_host'))
    {
      $transport = new Zend_Mail_Transport_Smtp($host, sfConfig::get('op_mail_smtp_config', array()));
    }
    elseif ($envelopeFrom = sfConfig::get('op_mail_envelope_from'))
    {
      $transport = new Zend_Mail_Transport_Sendmail('-f'.$envelopeFrom);
    }
    else
    {
      $transport = new Zend_Mail_Transport_Sendmail();
    }
    return $transport;
  }

  protected function sendMail($subject, $address, $from, $body)
  {
    if (null === $this->transport)
    {
      $this->transport = $this->getTransport();
    }
    $this->sendCount++;
    if ($this->sendCount > 100)
    {
      unset($this->transport);
      $this->sendCount = 0;
      $this->transport = $this->getTransport();
    }

    $subject = mb_convert_kana($subject, 'KV');

    $mailer = new Zend_Mail('iso-2022-jp');
    $mailer->setHeaderEncoding(Zend_Mime::ENCODING_BASE64)
      ->setFrom($from)
      ->addTo($address)
      ->setSubject(mb_encode_mimeheader($subject, 'iso-2022-jp'))
      ->setBodyText(mb_convert_encoding($body, 'JIS', 'UTF-8'), 'iso-2022-jp', Zend_Mime::ENCODING_7BIT);

    if ($envelopeFrom = sfConfig::get('op_mail_envelope_from'))
    {
      $mailer->setReturnPath($envelopeFrom);
    }
    $mailer->send($this->transport);
  }
}
