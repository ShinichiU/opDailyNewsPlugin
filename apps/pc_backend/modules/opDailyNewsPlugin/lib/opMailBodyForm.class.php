<?php

/**
 * opAtgBaseMailTitleForm
 *
 * @package    opAtgGeneralPlugin
 * @subpackage form
 * @author     Shogo Kawahara<kawahara@tejimaya.com>
 */
class opMailTemplateForm extends sfForm
{
  public function configure()
  {
    $this->setWidget('pc_title', new sfWidgetFormInput(array(), array('size' => 50)));
    $this->setWidget('pc_template', new sfWidgetFormTextarea());
    $this->setWidget('mobile_title', new sfWidgetFormInput(array(), array('size' => 50)));
    $this->setWidget('mobile_template', new sfWidgetFormTextarea());
    $this->setValidator('pc_title', new opValidatorString());
    $this->setValidator('pc_template', new opValidatorString());
    $this->setValidator('mobile_title', new opValidatorString());
    $this->setValidator('mobile_template', new opValidatorString());

    $notificationMailPc = Doctrine::getTable('NotificationMail')->findOneByName('pc_dailyNewsPlugin');
    $notificationMailMobile = Doctrine::getTable('NotificationMail')->findOneByName('mobile_dailyNewsPlugin');
    if ($notificationMailPc)
    {
      $this->setDefault('pc_title', $notificationMailPc->getTitle());
      $this->setDefault('pc_template', $notificationMailPc->getTemplate());
    }
    if ($notificationMailMobile)
    {
      $this->setDefault('mobile_title', $notificationMailMobile->getTitle());
      $this->setDefault('mobile_template', $notificationMailMobile->getTemplate());
    }

    $this->widgetSchema->setNameFormat('mail_template[%s]');
  }

  public function save()
  {
    if (!$this->isValid())
    {
      throw $this->getErrorSchema();
    }

    $notificationMailPc = Doctrine::getTable('NotificationMail')->findOneByName('pc_dailyNewsPlugin');
    if (!$notificationMailPc)
    {
      $notificationMailPc = new NotificationMail();
      $notificationMailPc->setName('pc_dailyNewsPlugin');
    }
    $notificationMailMobile = Doctrine::getTable('NotificationMail')->findOneByName('mobile_dailyNewsPlugin');
    if (!$notificationMailMobile)
    {
      $notificationMailMobile = new NotificationMail();
      $notificationMailMobile->setName('mobile_dailyNewsPlugin');
    }
    $notificationMailPc->setTitle($this->getValue('pc_title'));
    $notificationMailPc->setTemplate($this->getValue('pc_template'));
    $notificationMailPc->save();
    $notificationMailMobile->setTitle($this->getValue('mobile_title'));
    $notificationMailMobile->setTemplate($this->getValue('mobile_template'));
    $notificationMailMobile->save();
  }
}
