<?php
if(!defined('OSTADMININC') || !$thisstaff || !$thisstaff->isAdmin()) die('Access Denied');
$info = $qs = array();
if($sla && $_REQUEST['a']!='add'){
    $title=__('Update SLA Plan' /* SLA is abbreviation for Service Level Agreement */);
    $action='update';
    $submit_text=__('Save Changes');
    $info=$sla->getInfo();
    $info['id']=$sla->getId();
    $trans['name'] = $sla->getTranslateTag('name');
    $qs += array('id' => $sla->getId());
}else {
    $title=__('Add New SLA Plan' /* SLA is abbreviation for Service Level Agreement */);
    $action='add';
    $submit_text=__('Add Plan');
    $info['isactive']=isset($info['isactive'])?$info['isactive']:1;
    $info['enable_priority_escalation']=isset($info['enable_priority_escalation'])?$info['enable_priority_escalation']:1;
    $info['disable_overdue_alerts']=isset($info['disable_overdue_alerts'])?$info['disable_overdue_alerts']:0;
    $qs += array('a' => $_REQUEST['a']);
}
$info=Format::htmlchars(($errors && $_POST)?$_POST:$info);
?>
<form action="slas.php?<?php echo Http::build_query($qs); ?>" method="post" class="save">
 <?php csrf_token(); ?>
 <input type="hidden" name="do" value="<?php echo $action; ?>">
 <input type="hidden" name="a" value="<?php echo Format::htmlchars($_REQUEST['a']); ?>">
 <input type="hidden" name="id" value="<?php echo $info['id']; ?>">
 <h2><?php echo $title; ?>
    <?php if (isset($info['name'])) { ?><small>
    — <?php echo $info['name']; ?></small>
     <?php } ?>
</h2>
 <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2">
    <thead>
        <tr>
            <th colspan="2">
                <em><?php echo __('Tickets are marked overdue on grace period violation.');?></em>
            </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td width="180" class="required">
              <?php echo __('Name');?>:
            </td>
            <td>
                <input type="text" size="30" name="name" value="<?php echo $info['name']; ?>"
                    autofocus data-translate-tag="<?php echo $trans['name']; ?>"/>
                &nbsp;<span class="error">*&nbsp;<?php echo $errors['name']; ?></span>&nbsp;<i class="help-tip icon-question-sign" href="#name"></i>
            </td>
        </tr>
        <tr>
            <td width="180" class="required">
              <?php echo __('Grace Period');?>:
            </td>
            <td>
                <input type="text" size="10" name="grace_period" value="<?php echo $info['grace_period']; ?>">
                <em>( <?php echo __('in days');?> )</em>
                &nbsp;<span class="error">*&nbsp;<?php echo $errors['grace_period']; ?></span>&nbsp;<i class="help-tip icon-question-sign" href="#grace_period"></i>
            </td>
        </tr>
        <tr>
            <td width="180" class="required">
                <?php echo __('Status');?>:
            </td>
            <td>
                <input type="radio" name="isactive" value="1" <?php echo $info['isactive']?'checked="checked"':''; ?>><strong><?php echo __('Active');?></strong>
                <input type="radio" name="isactive" value="0" <?php echo !$info['isactive']?'checked="checked"':''; ?>><?php echo __('Disabled');?>
                &nbsp;<span class="error">*&nbsp;<?php echo $errors['isactive']; ?></span>
            </td>
        </tr>
        <tr>
            <td width="180">
                <?php echo __('Transient'); ?>:
            </td>
            <td>
                <input type="checkbox" name="transient" value="1" <?php echo $info['transient']?'checked="checked"':''; ?> >
                <?php echo __('SLA can be overridden on ticket transfer or help topic change'); ?>
                &nbsp;<i class="help-tip icon-question-sign" href="#transient"></i>
            </td>
        </tr>
        <tr>
            <td width="180">
                <?php echo __('Ticket Overdue Alerts');?>:
            </td>
            <td>
                <input type="checkbox" name="disable_overdue_alerts" value="1" <?php echo $info['disable_overdue_alerts']?'checked="checked"':''; ?> >
                    <?php echo __('<strong>Disable</strong> overdue alerts notices.'); ?>
                    <em><?php echo __('(Override global setting)'); ?></em>
            </td>
        </tr>
        <?php
            if ($sla && $_REQUEST['a']!='add') {
                ?>
            <tr>
                <td width="180">
                    <?php echo "Notificar incumplimiento a";?>:
                </td>
                <td>
                    <select name="alert_staff" data-quick-add>
                        <option value="null">&mdash; <?php echo 'Nadie'; ?> &mdash;</option>
                        <?php
                        if (($users=Staff::getStaffMembers())) {
                            echo sprintf('<OPTGROUP label="%s">',
                                    sprintf(__('Agents (%d)'), count($users)));
                            foreach ($users as $id => $name) {
                                $k="s$id";
                                $selected = ($info['alert_staff']==$k)?' selected="selected"':'';
                                ?>
                                <option value="<?php echo $k; ?>"<?php echo $selected; ?>><?php echo $name; ?></option>

                            <?php
                            }
                            echo '</OPTGROUP>';
                        }
                        if ($teams = Team::getTeams()) { ?>
                            <optgroup data-quick-add="team" label="<?php
                              echo sprintf(__('Teams (%d)'), count($teams)); ?>"><?php
                              foreach ($teams as $id => $name) {
                                  $k="t$id";
                                  $selected = $info['alert_staff']==$k ? 'selected="selected"' : '';
                                  ?>
                                  <option value="<?php echo $k; ?>"<?php echo $selected; ?>><?php echo $name; ?></option>
                              <?php
                              } ?>
                              <option value="0" data-quick-add data-id-prefix="t">— <?php echo __('Add New Team'); ?> —</option>
                            </optgroup>
                        <?php
                        } ?>
                    </select>
                    &nbsp;<span class="error">&nbsp;<?php echo $errors['alert_staff']; ?></span>
                    <i class="help-tip icon-question-sign" href="#alert_staff"></i>
                </td>
            </tr>
            <tr>
                <td width="180">
                    <?php echo "SLA enlazado";?>:
                </td>
                <td>
                    <select name="next_sla" data-quick-add>
                        <option value="null">&mdash; <?php echo 'Ninguno'; ?> &mdash;</option>
                        <?php
                        if (($slas=SLA::objects())) {
                            echo sprintf('<OPTGROUP label="%s">',
                                    sprintf(__('SLAs (%d)'), count($slas)));
                            foreach ($slas as $item) {
                                $selected = ($info['next_sla']==$item->getId())?' selected="selected"':'';
                                ?>
                                <option value="<?php echo $item->getId(); ?>"<?php echo $selected; ?>><?php echo $item->getName(); ?></option>
                            <?php
                            }
                            echo '</OPTGROUP>';
                        }
                        ?>
                    </select>
                    &nbsp;<span class="error">&nbsp;<?php echo $errors['next_sla']; ?></span>
                    <i class="help-tip icon-question-sign" href="#next_sla"></i>
                </td>
            </tr>
                <?php
            }
        ?>
        <tr>
            <th colspan="2">
                <em><strong><?php echo __('Internal Notes');?></strong>: <?php echo __("Be liberal, they're internal");?>
                </em>
            </th>
        </tr>
        <tr>
            <td colspan=2>
                <textarea class="richtext no-bar" name="notes" cols="21"
                    rows="8" style="width: 80%;"><?php echo $info['notes']; ?></textarea>
            </td>
        </tr>
    </tbody>
</table>
<p style="text-align:center;">
    <input type="submit" name="submit" value="<?php echo $submit_text; ?>">
    <input type="reset"  name="reset"  value="<?php echo __('Reset');?>">
    <input type="button" name="cancel" value="<?php echo __('Cancel');?>" onclick='window.location.href="slas.php"'>
</p>
</form>
