<?php

class opDailyNewsSendTask extends opBaseDailyNewsSendTask
{
  protected function configure()
  {
    $this->namespace        = 'opDailyNews';
    $this->name             = 'send';
    $this->briefDescription = '';
    $this->detailedDescription = <<<EOF
The [opDailyNews:send|INFO] task does things.
Call it with:

  [php symfony opDailyNews:send|INFO]
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    parent::execute($arguments, $options);
    sfContext::createInstance($this->createConfiguration('pc_frontend', 'prod'), 'pc_frontend');

    $this->logger = new sfFileLogger($this->dispatcher, array('file' => sfConfig::get('sf_log_dir').'/daily_news.log'));
    $this->logger->info('starting opDailyNews:send task');

    $adminMailAdress = opConfig::get('admin_mail_address');
    $this->dailyNewsDays = opConfig::get('daily_news_day');
    $today = time();

    // テンプレートロード
    $template = $this->getMailTemplate('pc_dailyNewsPlugin', true);
    $signature = $this->getMailTemplate('pc_signature');
    if ($signature)
    {
      $template['template'] =  $template['template']."\n".$signature['template'];
    }

    $helpers = array_unique(array_merge(array('Helper', 'Url', 'Asset', 'Tag', 'Escaping'), sfConfig::get('sf_standard_helpers')));
    sfContext::getInstance()->getConfiguration()->loadHelpers($helpers);

    $twigEnvironment = new Twig_Environment(new Twig_Loader_String());
    $tpl = $twigEnvironment->loadTemplate($template['template']);

    sfOpenPNEApplicationConfiguration::registerZend();

    // member_id 取得 & ループ
    $memberIds = $this->connection->execute("SELECT id FROM member WHERE (is_active = 1 OR is_active IS NULL)");
    while ($memberId = $memberIds->fetch(Doctrine::FETCH_NUM))
    {
      if (!$memberId[0])
      {
        continue;
      }

      $memberConfig = $this->getMemberConfig($memberId[0]);
      if (1 == $memberConfig && !$this->isDailyNewsDay())
      {
        continue;
      }

      $member = $this->getMember($memberId[0]);

      if (!$member)
      {
        continue;
      }

      $address = $this->getMemberPcEmailAddress($memberId[0]);

      if (!$address)
      {
        continue;
      }

      $params = array(
        'member'  => $member,
        'subject' => $template['title'],
        'communityTopics' => array(),
        'diaries' => $this->getFriendDiaryList($memberId[0]),
        'communityTopics' => $this->getCommunityTopicList($memberId[0]),
        'today'   => $today,
        'base_url' => sfConfig::get('op_base_url'),
      );

      $body = $tpl->render($params);

      try
      {
        $this->sendMail($params['subject'], $address, $adminMailAdress, $body);
        $this->logger->info(sprintf("sent daily news to member %d (usage memory:%s bytes)", $member['id'], number_format(memory_get_usage())));
      }
      catch (Zend_Mail_Transport_Exception $e)
      {
        $this->logger->err(sprintf("%s (member %d)",$e->getMessage(), $member['id']));
      }
    }
    $this->logger->info('end opDailyNews:send task');
  }

  protected function getFriendDiaryList($memberId, $limit = 5)
  {
    $friendIds = $this->getFriendIds($memberId);
    if (!$friendIds)
    {
      return array();
    }

    $sql = 'SELECT id, member_id, title FROM diary'
         . ' WHERE member_id IN ('.implode(',', $friendIds). ')'
         . ' AND public_flag IN (1, 2)'
         . ' ORDER BY created_at DESC'
         . ' LIMIT '.$limit;

    $stmt = $this->connection->execute($sql);
    $results = array();
    while ($r = $stmt->fetch(Doctrine::FETCH_ASSOC))
    {
      $r['member'] = $this->getMember($r['member_id']);
      $results[] = $r;
    }

    return $results;
  }

  protected function getCommunity($communityId)
  {
    return $this->connection->fetchRow("SELECT id, name FROM community WHERE id = ?", array($communityId));
  }

  protected function getJoinCommnityIds($memberId)
  {
    $results = array();
    $stmt =  $this->connection->execute("SELECT community_id FROM community_member WHERE member_id = ? AND is_pre = false", array($memberId));
    while ($r = $stmt->fetch(Doctrine::FETCH_NUM))
    {
      $results[] = $r[0];
    }

    return $results;
  }

  protected function getAshiatoList($memberId)
  {
    $results = array();
    $stmt =  $this->connection->execute("SELECT community_id FROM community_member WHERE member_id = ? AND position <> 'pre'", array($memberId));
    while ($r = $stmt->fetch(Doctrine::FETCH_NUM))
    {
      $results[] = $r[0];
    }

    return $results;
  }

  protected function getCommunityTopicList($memberId=null, $limit = 5)
  {
    $communityIds = $this->getJoinCommnityIds($memberId);
    if (!$communityIds)
    {
      return array();
    }
    $sql = 'SELECT id, community_id, member_id, name, body FROM community_topic'
         . ' WHERE community_id IN ('.implode(',', $communityIds).')'
         . ' ORDER BY updated_at DESC'
         . ' LIMIT '.$limit;

    $stmt = $this->connection->execute($sql);
    $results = array();
    while ($r = $stmt->fetch(Doctrine::FETCH_ASSOC))
    {
      $r['community'] = $this->getCommunity($r['community_id']);
      $results[] = $r;
    }

    return $results;
  }

  protected function isDailyNewsDay()
  {
    $day = date('w') - 1;
    if (0 > $day)
    {
      $day = 7;
    }

    return in_array($day, $this->dailyNewsDays);
  }
}
