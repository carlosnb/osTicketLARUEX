<?php
//Note that ticket obj is initiated in tickets.php.
if(!defined('OSTSCPINC') || !$thisstaff || !is_object($equipment) || !$equipment->getId()) die('Invalid path');

//Make sure the staff is allowed to access the page.
if(!@$thisstaff->isStaff() || !$equipment->checkStaffPerm($thisstaff)) die('Access Denied');

//Re-use the post info on error...savekeyboards.org (Why keyboard? -> some people care about objects than users!!)
$info=($_POST && $errors)?Format::input($_POST):array();

//Get the goodies.
$lock  = $equipment->getLock();  //Ticket lock obj
if (!$lock && $cfg->getTicketLockMode() == Lock::MODE_ON_VIEW)
    $lock = $equipment->acquireLock($thisstaff->getId());
$mylock = ($lock && $lock->getStaffId() == $thisstaff->getId()) ? $lock : null;
$id    = $equipment->getId();    // Equipment ID.
$dept  = $equipment->getDept();  //Dept
$role  = $thisstaff->getRole($dept);

if (!$errors['err']) {

    if ($lock && $lock->getStaffId()!=$thisstaff->getId())
        $errors['err'] = sprintf(__('%s is currently locked by %s'),
                __('Este equipamiento'),
                $lock->getStaffName());
}

?>
<div>
    <div class="sticky bar">
       <div class="content">
        <div class="pull-right flush-right">
            <?php
            if ($role->hasPerm(EquipmentModel::PERM_DELETE)) {
            ?>
            <span class="action-button pull-right" data-placement="bottom" data-dropdown="#action-dropdown-more" data-toggle="tooltip" title="<?php echo __('More');?>">
                <i class="icon-caret-down pull-right"></i>
                <span ><i class="icon-cog"></i></span>
            </span>
            <div id="action-dropdown-more" class="action-dropdown anchor-right">
                <ul>
                    <?php
                    if ($role->hasPerm(EquipmentModel::PERM_DELETE)) {
                       ?>
                      <li class="danger"><a class="equipment-action" href="#equipments/<?php
                      echo $equipment->getId(); ?>/status/delete"
                      data-redirect="equipment.php"><i class="icon-trash"></i> <?php
                      echo 'Eliminar equipamiento'; ?></a></li>
                    <?php
                    }
                    ?>
                </ul>
            </div>
            <?php
            }
            ?>
            
            <?php
            if ($role->hasPerm(EquipmentModel::PERM_EDIT)) { ?>
                <span class="action-button pull-right">
                    <a class="equipment-action"
                       data-dialog-config="{'size':'large'}"
                       data-placement="bottom" 
                       data-toggle="tooltip" 
                       title="<?php echo __('Edit'); ?>" 
                       href="#equipments/<?php echo $equipment->getId(); ?>/edit">
                        <i class="icon-edit"></i>
                    </a>
                </span>
            <?php
            }
            
            if ($role->hasPerm(EquipmentModel::PERM_TRANSFER)) {?>
            <span class="action-button pull-right">
            <a class="equipment-action" id="equipment-transfer" data-placement="bottom" data-toggle="tooltip" title="<?php echo __('Transfer'); ?>"
                data-redirect="equipment.php"
                href="#equipments/<?php echo $equipment->getId(); ?>/transfer"><i class="icon-share"></i></a>
            </span>
            <?php
            }
            ?>
            
            <span class="action-button pull-right" data-placement="bottom" data-dropdown="#action-dropdown-print" data-toggle="tooltip" title="<?php echo __('Print'); ?>">
                <i class="icon-caret-down pull-right"></i>
                <a id="equipment-print" href="equipment.php?id=<?php echo $equipment->getId(); ?>&a=print"><i class="icon-print"></i></a>
            </span>
            <div id="action-dropdown-print" class="action-dropdown anchor-right">
              <ul>
                 <li><a class="no-pjax" target="_blank" href="equipment.php?id=<?php echo $equipment->getId(); ?>&a=print&notes=0"><i
                 class="icon-file-alt"></i> <?php echo 'Asunto del item'; ?></a>
                 <li><a class="no-pjax" target="_blank" href="equipment.php?id=<?php echo $equipment->getId(); ?>&a=print&notes=1"><i
                 class="icon-file-text-alt"></i> <?php echo __('Thread + Internal Notes'); ?></a>
              </ul>
            </div>
            
            <a href="#post-note" id="post-note" class="post-response action-button"
                    data-placement="bottom" data-toggle="tooltip"
                    title="<?php echo __('Post Internal Note'); ?>">
                <i class="icon-file-text"></i>
            </a>
            <?php // Status change options
            echo EquipmentStatus::status_options(array(
                    'status' => $equipment->getStatus()->getState(), 
                    'dept_id' => $equipment->getDeptId()
                ));
            ?>
        </div>
        <div class="flush-left">
             <h2><a href="equipment.php?id=<?php echo $equipment->getId(); ?>"
             title="<?php echo __('Reload'); ?>"><i class="icon-refresh"></i>
             <?php echo sprintf('%s', $equipment->getName()); ?></a>
            </h2>
        </div>
    </div>
  </div>
</div>
<table class="ticket_info" cellspacing="0" cellpadding="0" width="940" border="0">
    <tr>
        <td width="50%">
            <table border="0" cellspacing="" cellpadding="4" width="100%">
                <tr>
                    <th width="100"><?php echo __('Status');?>:</th>
                    <td><?php 
                        //echo ($S = $equipment->getStatus()) ? $S->display() : '';
                        echo ($S = $equipment->getStatus()) ? $S->getName() : '';
                    ?></td>
                </tr>
                <tr>
                    <th><?php echo __('Create Date');?>:</th>
                    <td><?php echo Format::datetime($equipment->getCreateDate()); ?></td>
                </tr>
            </table>
        </td>
        <td width="50%">
            <table cellspacing="0" cellpadding="4" width="100%" border="0">
                <tr>
                    <th><?php echo __('Department');?>:</th>
                    <td><?php echo Format::htmlchars($equipment->getDeptName()); ?></td>
                </tr>
                <?php
                if ($equipment->isActive()) { ?>
                <tr>
                    <th><?php echo 'Activo desde';?>:</th>
                    <td><?php echo Format::datetime($equipment->getActivationDate()); ?></td>
                </tr>
                <?php
                } elseif ($equipment->isInactive()) { ?>
                <tr>
                    <th><?php echo __('Inactivo desde');?>:</th>
                    <td><?php echo Format::datetime($equipment->getDeactivationDate()); ?></td>
                </tr>
                <?php
                }  elseif ($equipment->isRetired()) { ?>
                <tr>
                    <th><?php echo __('Retirado desde');?>:</th>
                    <td><?php echo Format::datetime($equipment->getRetireDate()); ?></td>
                </tr>
                <?php
                }
                ?>
            </table>
        </td>
    </tr>
</table>
<br>
<?php
foreach (DynamicFormEntry::forEquipment($equipment->getId()) as $form) {
    // Skip core fields shown earlier in the ticket view
    // TODO: Rewrite getAnswers() so that one could write
    //       ->getAnswers()->filter(not(array('field__name__in'=>
    //           array('email', ...))));
    $answers = $form->getAnswers()->exclude(Q::any(array(
        'field__flags__hasbit' => DynamicFormField::FLAG_EXT_STORED,
        'field__name__in' => array('subject', 'priority')
    )));
    $displayed = array();
    foreach($answers as $a) {
        if (!($v = $a->display()))
            continue;
        $displayed[] = array($a->getLocal('label'), $v);
    }
    if (count($displayed) == 0)
        continue;
    ?>
    <table class="ticket_info custom-data" cellspacing="0" cellpadding="0" width="940" border="0">
    <thead>
        <th colspan="2"><?php echo Format::htmlchars($form->getTitle()); ?></th>
    </thead>
    <tbody>
<?php
    foreach ($displayed as $stuff) {
        list($label, $v) = $stuff;
?>
        <tr>
            <td width="200"><?php
                echo Format::htmlchars($label);
            ?>:</th>
            <td><?php
                echo $v;
            ?></td>
        </tr>
<?php } ?>
    </tbody>
    </table>
<?php } ?>
<div class="clear"></div>

<?php
$tcount = $equipment->getThreadEntries($types)->count();
?>
<ul class="tabs clean threads" id="equipment_tabs" >
    <li class="active"><a id="equipment-thread-tab" href="#equipment_thread"><?php
        echo sprintf(__('Hilo del equipamiento (%d)'), $tcount); ?></a></li>
    <?php if ($equipment->isBookable()) { ?>
    <li><a id="equipment-reservations-tab" href="#reservations"
            data-url="<?php
        echo sprintf('#equipments/%d/reservations', $equipment->getId()); ?>"><?php
        echo __('Reservas');
        if ($equipment->getNumReservations())
            echo sprintf('&nbsp;(<span id="equipment-reservations-count">%d</span>)', $equipment->getNumReservations());
        ?></a>
    </li>
    <li><a id="equipment-hist-reservations-tab" href="#hist-reservations"
            data-url="<?php
        echo sprintf('#equipments/%d/hist-reservations', $equipment->getId()); ?>"><?php
        echo __('Reservas archivadas');
        if ($count=$equipment->getNumHistReservations())
            echo sprintf('&nbsp;(<span id="equipment-hist-reservations-count">%d</span>)', $count);
        ?></a>
    </li>
    <?php } ?>
</ul>

<div id="equipment_tabs_container">
<div id="equipment_thread" class="tab_content">

<?php
    // Render ticket thread
    $equipment->getThread()->render(
            array('M', 'R', 'N'),
            array(
                'html-id'   => 'equipmentThread',
                'mode'      => Thread::MODE_STAFF,
                'sort'      => $thisstaff->thread_view_order
                )
            );
?>
<div class="clear"></div>
<?php
if ($errors['err'] && isset($_POST['a'])) {
    // Reflect errors back to the tab.
    $errors[$_POST['a']] = $errors['err'];
} elseif($msg) { ?>
    <div id="msg_notice"><?php echo $msg; ?></div>
<?php
} elseif($warn) { ?>
    <div id="msg_warning"><?php echo $warn; ?></div>
<?php
} ?>

<div class="sticky bar stop actions" id="response_options"
>
    <ul class="tabs" id="response-tabs">
        <li><a href="#note" <?php
            echo isset($errors['postnote']) ?  'class="error"' : ''; ?>
            id="post-note-tab"><?php echo __('Publicar nota interna');?></a></li>
    </ul>
    <form id="note" class="tab_content spellcheck exclusive save"
        data-lock-object-id="equipment/<?php echo $equipment->getId(); ?>"
        data-lock-id="<?php echo $mylock ? $mylock->getId() : ''; ?>"
        action="equipment.php?id=<?php echo $equipment->getId(); ?>#note"
        name="note" method="post" enctype="multipart/form-data">
        <?php csrf_token(); ?>
        <input type="hidden" name="id" value="<?php echo $equipment->getId(); ?>">
        <input type="hidden" name="locktime" value="<?php echo $cfg->getLockTime() * 60; ?>">
        <input type="hidden" name="a" value="postnote">
        <input type="hidden" name="lockCode" value="<?php echo $mylock ? $mylock->getCode() : ''; ?>">
        <table width="100%" border="0" cellspacing="0" cellpadding="3">
            <?php
            if($errors['postnote']) {?>
            <tr>
                <td width="120">&nbsp;</td>
                <td class="error"><?php echo $errors['postnote']; ?></td>
            </tr>
            <?php
            } ?>
            <tr>
                <td width="120" style="vertical-align:top">
                    <label><strong><?php echo __('Internal Note'); ?>:</strong><span class='error'>&nbsp;*</span></label>
                </td>
                <td>
                    <div>
                        <div class="faded" style="padding-left:0.15em"><?php
                        echo __('Note title - summary of the note (optional)'); ?></div>
                        <input type="text" name="title" id="title" size="60" value="<?php echo $info['title']; ?>" >
                        <br/>
                        <span class="error">&nbsp;<?php echo $errors['title']; ?></span>
                    </div>
                    <br/>
                    <div class="error"><?php echo $errors['note']; ?></div>
                    <textarea name="note" id="internal_note" cols="80"
                        placeholder="<?php echo __('Note details'); ?>"
                        rows="9" wrap="soft"
                        class="<?php if ($cfg->isRichTextEnabled()) echo 'richtext';
                            ?> draft draft-delete" <?php
    list($draft, $attrs) = Draft::getDraftAndDataAttrs('ticket.note', $equipment->getId(), $info['note']);
    echo $attrs; ?>><?php echo $_POST ? $info['note'] : $draft;
                        ?></textarea>
                <div class="attachments">
                <?php
                    print $note_form->getField('attachments')->render();
                ?>
                </div>
                </td>
            </tr>
            <tr><td colspan="2">&nbsp;</td></tr>
            <!--<tr>
                <td width="120">
                    <label><?php echo __('Equipment Status');?>:</label>
                </td>
                <td>
                    <div class="faded"></div>
                    <select name="note_status_id">
                        
                    </select>
                    &nbsp;<span class='error'>*&nbsp;<?php echo $errors['note_status_id']; ?></span>
                </td>
            </tr>-->
        </table>

       <p style="text-align:center;">
           <input class="save pending" type="submit" value="<?php echo __('Post Note');?>">
           <input class="" type="reset" value="<?php echo __('Reset');?>">
       </p>
   </form>
 </div>
 </div>
</div>
<div style="display:none;" class="dialog" id="print-options">
    <h3><?php echo __('Equipment Print Options');?></h3>
    <a class="close" href=""><i class="icon-remove-circle"></i></a>
    <hr/>
    <form action="equipment.php?id=<?php echo $equipment->getId(); ?>"
        method="post" id="print-form" name="print-form" target="_blank">
        <?php csrf_token(); ?>
        <input type="hidden" name="a" value="print">
        <input type="hidden" name="id" value="<?php echo $equipment->getId(); ?>">
        <fieldset class="notes">
            <label class="fixed-size" for="notes"><?php echo __('Print Notes');?>:</label>
            <label class="inline checkbox">
            <input type="checkbox" id="notes" name="notes" value="1"> <?php echo __('Print <b>Internal</b> Notes/Comments');?>
            </label>
        </fieldset>
        <fieldset>
            <label class="fixed-size" for="psize"><?php echo __('Paper Size');?>:</label>
            <select id="psize" name="psize">
                <option value="">&mdash; <?php echo __('Select Print Paper Size');?> &mdash;</option>
                <?php
                  $psize =$_SESSION['PAPER_SIZE']?$_SESSION['PAPER_SIZE']:$thisstaff->getDefaultPaperSize();
                  foreach(Export::$paper_sizes as $v) {
                      echo sprintf('<option value="%s" %s>%s</option>',
                                $v,($psize==$v)?'selected="selected"':'', __($v));
                  }
                ?>
            </select>
        </fieldset>
        <hr style="margin-top:3em"/>
        <p class="full-width">
            <span class="buttons pull-left">
                <input type="reset" value="<?php echo __('Reset');?>">
                <input type="button" value="<?php echo __('Cancel');?>" class="close">
            </span>
            <span class="buttons pull-right">
                <input type="submit" value="<?php echo __('Print');?>">
            </span>
         </p>
    </form>
    <div class="clear"></div>
</div>
<div style="display:none;" class="dialog" id="confirm-action">
    <h3><?php echo __('Please Confirm');?></h3>
    <a class="close" href=""><i class="icon-remove-circle"></i></a>
    <hr/>
    <p class="confirm-action" style="display:none;" id="claim-confirm">
        <?php echo sprintf(__('Are you sure you want to <b>claim</b> (self assign) %s?'), __('this ticket'));?>
    </p>
    <p class="confirm-action" style="display:none;" id="answered-confirm">
        <?php echo __('Are you sure you want to flag the ticket as <b>answered</b>?');?>
    </p>
    <p class="confirm-action" style="display:none;" id="unanswered-confirm">
        <?php echo __('Are you sure you want to flag the ticket as <b>unanswered</b>?');?>
    </p>
    <p class="confirm-action" style="display:none;" id="overdue-confirm">
        <?php echo __('Are you sure you want to flag the ticket as <font color="red"><b>overdue</b></font>?');?>
    </p>
    <p class="confirm-action" style="display:none;" id="delete-confirm">
        <font color="red"><strong><?php echo sprintf(
            __('Are you sure you want to DELETE %s?'), __('este equipamiento'));?></strong></font>
        <br><br><?php echo __('Deleted data CANNOT be recovered, including any associated attachments.');?>
    </p>
    <div><?php echo __('Please confirm to continue.');?></div>
    <form action="equipment.php?id=<?php echo $equipment->getId(); ?>" method="post" id="confirm-form" name="confirm-form">
        <?php csrf_token(); ?>
        <input type="hidden" name="id" value="<?php echo $equipment->getId(); ?>">
        <input type="hidden" name="a" value="process">
        <input type="hidden" name="do" id="action" value="">
        <hr style="margin-top:1em"/>
        <p class="full-width">
            <span class="buttons pull-left">
                <input type="button" value="<?php echo __('Cancel');?>" class="close">
            </span>
            <span class="buttons pull-right">
                <input type="submit" value="<?php echo __('OK');?>">
            </span>
         </p>
    </form>
    <div class="clear"></div>
</div>
<script type="text/javascript">
$(function() {
    // Post Reply or Note action buttons.
    $('a.post-response').click(function (e) {
        var $r = $('ul.tabs > li > a'+$(this).attr('href')+'-tab');
        if ($r.length) {
            // Make sure ticket thread tab is visible.
            var $t = $('ul#equipment_tabs > li > a#equipment-thread-tab');
            if ($t.length && !$t.hasClass('active'))
                $t.trigger('click');
            // Make the target response tab active.
            if (!$r.hasClass('active'))
                $r.trigger('click');

            // Scroll to the response section.
            var $stop = $(document).height();
            var $s = $('div#response_options');
            if ($s.length)
                $stop = $s.offset().top-125

            $('html, body').animate({scrollTop: $stop}, 'fast');
        }

        return false;
    });
    
    $(document).off('.equipment-action');
    $(document).on('click.equipment-action', 'a.equipment-action', function(e) {
        e.preventDefault();
        var url = 'ajax.php/'
        +$(this).attr('href').substr(1)
        +'?_uid='+new Date().getTime();
        var $options = $(this).data('dialogConfig');
        var $redirect = $(this).data('redirect');
        $.dialog(url, [201], function (xhr) {
            if (!!$redirect)
                window.location.href = $redirect;
            else
                $.pjax.reload('#pjax-container');
        }, $options);

        return false;
    });

});
</script>
