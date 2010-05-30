<?php slot('title', 'デイリーニュース設定'); ?>
<?
$form->getWidget('pc_template')->setAttribute('rows', 30);
$form->getWidget('pc_template')->setAttribute('cols', 70);
$form->getWidget('mobile_template')->setAttribute('rows', 30);
$form->getWidget('mobile_template')->setAttribute('cols', 30);
?>

<h3>デイリーニュース設定</h3>
<?php echo $form->renderFormTag(url_for('@opDailyNewsPlugin_index')) ?>
<table>
<?php echo $form ?>
<td colspan="2">
<input type="submit" value="<?php echo __('Modify') ?>">
</td>
</table>
</form>
