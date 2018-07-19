<?php
/*********************************************************************
    class.equipment.php

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
include_once(INCLUDE_DIR.'class.staff.php');
include_once(INCLUDE_DIR.'class.reservation.php');
include_once(INCLUDE_DIR.'class.lock.php');
include_once(INCLUDE_DIR.'class.export.php');
include_once(INCLUDE_DIR.'class.template.php');
include_once(INCLUDE_DIR.'class.variable.php');
require_once(INCLUDE_DIR.'class.dynamic_forms.php');

class EquipmentModel extends VerySimpleModel {
    static $meta = array(
        'table' => EQUIPMENT_TABLE,
        'pk' => array('id'),
        'joins' => array(
            'status' => array(
                'constraint' => array('status_id' => 'EquipmentStatus.id')
            ),
            'dept' => array(
                'constraint' => array('dept_id' => 'Dept.id'),
            ),
            'lock' => array(
                'constraint' => array('lock_id' => 'Lock.lock_id'),
                'null' => true,
            ),
            'thread' => array(
                'reverse' => 'EquipmentThread.equipment',
                'list' => false,
                'null' => true,
            ),
            'cdata' => array(
                'reverse' => 'EquipmentCData.equipment',
                'list' => false,
            ),
            'reservations' => array(
                'reverse' => 'EquipmentReservation.equipment'
            ),
            'entries' => array(
                'constraint' => array(
                    "'E'" => 'DynamicFormEntry.object_type',
                    'id' => 'DynamicFormEntry.object_id',
                ),
                'list' => true,
            ),
        )
    );
    
    const PERM_CREATE   = 'equipment.create';
    const PERM_EDIT     = 'equipment.edit';
    const PERM_TRANSFER = 'equipment.transfer';
    const PERM_RETIRE   = 'equipment.retire';
    const PERM_DELETE   = 'equipment.delete';
    
    static protected $perms = array(
            self::PERM_CREATE => array(
                'title' =>
                /* @trans */ 'Create',
                'desc'  =>
                /* @trans */ 'Ability to create equipment'),
            self::PERM_EDIT => array(
                'title' =>
                /* @trans */ 'Edit',
                'desc'  =>
                /* @trans */ 'Ability to edit equipment'),
            self::PERM_TRANSFER => array(
                'title' =>
                /* @trans */ 'Transfer',
                'desc'  =>
                /* @trans */ 'Ability to transfer equipment between departments'),
            self::PERM_RETIRE => array(
                'title' =>
                /* @trans */ 'Retire',
                'desc'  =>
                /* @trans */ 'Ability to retire equipment'),
            self::PERM_DELETE => array(
                'title' =>
                /* @trans */ 'Delete',
                'desc'  =>
                /* @trans */ 'Ability to delete equipment')
            );
    
    function getId() {
        return $this->id;
    }

    static function getSources() {
        static $translated = false;
        if (!$translated) {
            foreach (static::$sources as $k=>$v)
                static::$sources[$k] = __($v);
        }

        return static::$sources;
    }
    
    static function getPermissions() {
        return self::$perms;
    }
}

RolePermission::register(/* @trans */ 'Equipment', EquipmentModel::getPermissions(), true);

class EquipmentCData extends VerySimpleModel {
    static $meta = array(
        'pk' => array('equipment_id'),
        'table' => EQUIPMENT_CDATA_TABLE,
        'joins' => array(
            'equipment' => array(
                'constraint' => array('equipment_id' => 'EquipmentModel.id'),
            ),
        ),
    );
}

class Equipment extends EquipmentModel
implements Threadable {
    
    var $_entries;
    var $_answers;
    
    function __onload() {
        $this->loadDynamicData();
    }
    
    function __cdata($field, $ftype=null) {

        foreach ($this->getDynamicData() as $e) {
            // Make sure the form type matches
            if (!$e->form
                    || ($ftype && $ftype != $e->form->get('type')))
                continue;

            // Get the named field and return the answer
            if ($a = $e->getAnswer($field))
                return $a;
        }

        return null;
    }

    function loadDynamicData($force=false) {
        if (!isset($this->_answers) || $force) {
            $this->_answers = array();
            foreach (DynamicFormEntryAnswer::objects()
                ->filter(array(
                    'entry__object_id' => $this->getId(),
                    'entry__object_type' => ObjectModel::OBJECT_TYPE_EQUIPMENT
                )) as $answer
            ) {
                $tag = mb_strtolower($answer->field->name) ?: 'field.' . $answer->field->id;
                $this->_answers[$tag] = $answer;
            }
        }
        return $this->_answers;
    }
    
    
    
    function hasState($state) {
        return  strcasecmp($this->getState(), $state) == 0;
    }
    
    function isNew() {
        return $this->hasState('new');
    }

    function isActive() {
        return $this->hasState('active');
    }

    function isInactive() {
        return $this->hasState('inactive');
    }
    
    function isRetired() {
        return $this->hasState('retired');
    }

    function isLocked() {
        return null !== $this->getLock();
    }
    
    function checkStaffPerm($staff, $perm=null) {
        // Must be a valid staff
        if (!$staff instanceof Staff && !($staff=Staff::lookup($staff)))
            return false;

        // Check access based on department
        if (!$staff->canAccessDept($this->getDeptId())) {
            return false;
        }

        // At this point staff has view access unless a specific permission is
        // requested
        if ($perm === null)
            return true;

        // Permission check requested -- get role.
        if (!($role=$staff->getRole($this->getDeptId())))
            return false;

        // Check permission based on the effective role
        return $role->hasPerm($perm);
    }
    
    function isBookable() {
        return $this->ht['bookable']>0;
    }

    // Deprecated
    function getOldAuthToken() {
        return md5($this->getId() . SECRET_SALT);
    }

    function getName(){
        return $this->name;
    }
    
    function getSubject() {
        return (string) $this->_answers['description'];
    }
    
    function getDescription() {
        return $this->__cdata('description', ObjectModel::OBJECT_TYPE_EQUIPMENT);
    }

    function getCreateDate() {
        return $this->created;
    }
    
    function getActivationDate() {
        return $this->activation;
    }
    
    function getDeactivationDate() {
        return $this->deactivation;
    }
    
    function addDynamicData($data) {

        $tf = EquipmentForm::getInstance($this->id, true);
        foreach ($tf->getFields() as $f)
            if (isset($data[$f->get('name')]))
                $tf->setAnswer($f->get('name'), $data[$f->get('name')]);

        $tf->save();

        return $tf;
    }
    
    function getDynamicData() {
        if (!isset($this->_entries)) {
            $this->_entries = DynamicFormEntry::forObject($this->id, 'E')->all();
        }

        return $this->_entries ?: array();
    }
    
    function getRetireDate() {
        return $this->retirement;
    }

    function getUpdateDate() {
        return $this->updated;
    }

    function getStatusId() {
        return $this->status_id;
    }
    
    function getDeptId() {
        return $this->dept_id;
    }
    
    function getDeptName() {
        if ($this->dept instanceof Dept)
            return $this->dept->getFullName();
    }

    function getDept() {
        return $this->dept;
    }

    /**
     * setStatusId
     *
     * Forceably set the ticket status ID to the received status ID. No
     * checks are made. Use ::setStatus() to change the ticket status
     */
    // XXX: Use ::setStatus to change the status. This can be used as a
    //      fallback if the logic in ::setStatus fails.
    function setStatusId($id) {
        $this->status_id = $id;
        return $this->save();
    }

    function getStatus() {
        return $this->status;
    }

    function getState() {
        if (!$this->getStatus()) {
            return '';
        }
        return $this->getStatus()->getState();
    }

    function getHashtable() {
        return $this->ht;
    }

    function getUpdateInfo() {
        global $cfg;

        return array();

    }

    function getLock() {
        $lock = $this->lock;
        if ($lock && !$lock->isExpired())
            return $lock;
    }

    function acquireLock($staffId, $lockTime=null) {
        global $cfg;

        if (!isset($lockTime))
            $lockTime = $cfg->getLockTime();

        if (!$staffId or !$lockTime) //Lockig disabled?
            return null;

        // Check if the ticket is already locked.
        if (($lock = $this->getLock()) && !$lock->isExpired()) {
            if ($lock->getStaffId() != $staffId) //someone else locked the equipment.
                return null;

            //Lock already exits...renew it
            $lock->renew($lockTime); //New clock baby.

            return $lock;
        }
        // No lock on the ticket or it is expired
        $this->lock = Lock::acquire($staffId, $lockTime); //Create a new lock..

        if ($this->lock) {
            $this->save();
        }

        // load and return the newly created lock if any!
        return $this->lock;
    }

    function releaseLock($staffId=false) {
        if (!($lock = $this->getLock()))
            return false;

        if ($staffId && $lock->staff_id != $staffId)
            return false;

        if (!$lock->delete())
            return false;

        $this->lock = null;
        return $this->save();
    }
    
    function getThreadId() {
        if ($this->thread)
            return $this->thread->id;
    }

    function getThread() {
        return $this->thread;
    }
    
    function getNumReservations() {
        return count($this->reservations);
    }
    
    function getNumHistReservations() {
        $count = 0;
        $sql='SELECT 1 FROM '.HIST_RESERVATION_TABLE.' T1 '
                .' WHERE equipment_id = '.$this->getId().';';
        if ($res = db_query($sql)) {
            $count=db_num_rows($res);
        }
        return $count;
    }

    function getThreadCount() {
        return $this->getClientThread()->count();
    }
    
    function getClientThread() {
        return $this->getThreadEntries(array('M', 'R'));
    }
    
    function getThreadEntries($type=false) {
        $entries = $this->getThread()->getEntries();
        if ($type && is_array($type))
            $entries->filter(array('type__in' => $type));

        return $entries;
    }

    function getBookingForm() {
        return BookingForm::instantiate();
    }

    function getAuthToken($user, $algo=1) {

        //Format: // <user type><algo id used>x<pack of uid & tid><hash of the algo>
        $authtoken = sprintf('%s%dx%s',
                ($user->getId() == $this->getOwnerId() ? 'o' : 'c'),
                $algo,
                Base32::encode(pack('VV',$user->getId(), $this->getId())));

        switch($algo) {
            case 1:
                $authtoken .= substr(base64_encode(
                            md5($user->getId().$this->getCreateDate().$this->getId().SECRET_SALT, true)), 8);
                break;
            default:
                return null;
        }

        return $authtoken;
    }
    
    function getTransferForm($source=null) {

        if (!$source)
            $source = array('dept' => array($this->getDeptId()));

        return TransferForm::instantiate($source);
    }
    
    //Dept Transfer...with alert.. done by staff
    function transfer(TransferForm $form, &$errors, $alert=true) {
        global $thisstaff, $cfg;

        // Check if staff can do the transfer
        if (!$this->checkStaffPerm($thisstaff, Equipment::PERM_TRANSFER))
            return false;

        $cdept = $this->getDept(); // Current department
        $dept = $form->getDept(); // Target department
        if (!$dept || !($dept instanceof Dept))
            $errors['dept'] = __('Department selection required');
        elseif ($dept->getid() == $this->getDeptId())
            $errors['dept'] = sprintf(
                    __('%s already in the department'), 'Equipamiento');
        else {
            $this->dept_id = $dept->getId();

            // Make sure the new department allows assignment to the
            // currently assigned agent (if any)
            /*if ($this->isAssigned()
                && ($staff=$this->getStaff())
                && $dept->assignMembersOnly()
                && !$dept->isMember($staff)
            ) {
                $this->staff_id = 0;
            }*/
        }

        if ($errors || !$this->save(true))
            return false;

        // Log transfer event
        $this->logEvent('transferred');

        // Post internal note if any
        $note = null;
        $comments = $form->getField('comments')->getClean();
        if ($comments) {
            $title = sprintf(__('%1$s transferred from %2$s to %3$s'),
                    'Equipamiento',
                    $cdept->getName(),
                    $dept->getName());

            $_errors = array();
            $note = $this->postNote(
                    array('note' => $comments, 'title' => $title),
                    $_errors, $thisstaff, false);
        }

        //Send out alerts if enabled AND requested
        if (!$alert || !$cfg->alertONTransfer())
            return true; //no alerts!!

         if (($email = $dept->getAlertEmail())
             && ($tpl = $dept->getTemplate())
             && ($msg=$tpl->getTransferAlertMsgTemplate())
         ) {
            $msg = $this->replaceVars($msg->asArray(),
                array('comments' => $note, 'staff' => $thisstaff));
            // Recipients
            $recipients = array();
            if ($cfg->alertDeptMembersONTransfer()) {
                foreach ($dept->getMembersForAlerts() as $M)
                    $recipients[] = $M;
            }

            // Always alert dept manager??
            if ($cfg->alertDeptManagerONTransfer()
                && $dept
                && ($manager=$dept->getManager())
            ) {
                $recipients[] = $manager;
            }
            $sentlist = $options = array();
            if ($note) {
                $options += array('thread'=>$note);
            }
            foreach ($recipients as $k=>$staff) {
                if (!is_object($staff)
                    || !$staff->isAvailable()
                    || in_array($staff->getEmail(), $sentlist)
                ) {
                    continue;
                }
                $alert = $this->replaceVars($msg, array('recipient' => $staff));
                $email->sendAlert($staff, $alert['subj'], $alert['body'], null, $options);
                $sentlist[] = $staff->getEmail();
            }
         }

         return true;
    }
    
    //Replace base variables.
    function replaceVars($input, $vars = array()) {
        global $ost;

        $vars = array_merge($vars, array('equipment' => $this));
        return $ost->replaceTemplateVariables($input, $vars);
    }

    //Status helper.

    function setStatus($status, $comments='', &$errors=array()) {
        global $thisstaff;
        
        if ($thisstaff && !($role = $thisstaff->getRole($this->getDeptId())))
            return false;
        
        if ($status && is_numeric($status))
            $status = EquipmentStatus::lookup($status);

        if (!$status || !$status instanceof EquipmentStatus)
            return false;
        
        // Double check permissions (when changing status)
        if ($role && $this->getStatusId()) {
            switch ($status->getState()) {
            case 'retired':
                if (!($role->hasPerm(EquipmentModel::PERM_RETIRE)))
                    return false;
                break;
            case 'deleted':
                // XXX: intercept deleted status and do hard delete
                if ($role->hasPerm(EquipmentModel::PERM_DELETE))
                    return $this->delete($comments);
                // Agent doesn't have permission to delete  tickets
                return false;
                break;
            }
        }

        $hadStatus = $this->getStatusId();
        if ($this->getStatusId() == $status->getId())
            return true;

        // Perform checks on the *new* status, _before_ the status changes
        $ecb = null;
        switch ($status->getState()) {
            case 'retired':
                $this->retirement = $this->updated = SqlFunction::NOW();
                $ecb = function($t) use ($status) {
                    $t->logEvent('equipment_retirement', 
                            array());
                };
                break;
            case 'active':
                $this->activation = $this->updated = SqlFunction::NOW();
                $ecb = function ($t) use ($status) {
                    $t->logEvent('equipment_edition', 
                            array('status' => array($status->getId(), $status->getName())), 
                            null, 
                            ($this->isInactive()?'inactive':($this->isRetired()?'retired':'new')));
                };
                break;
            case 'inactive':
                $this->deactivation = $this->updated = SqlFunction::NOW();
                $ecb = function ($t) use ($status) {
                    $t->logEvent('equipment_edition', 
                            array('status' => array($status->getId(), $status->getName())), 
                            null, 
                            ($this->isActive()?'active':'new'));
                };
                break;
            default:
                return false;

        }

        $this->status = $status;
        if (!$this->save())
            return false;
        
        // Log status change b4 reload — if currently has a status.
        if ($hadStatus) {
            $alert = false;
            if ($comments = ThreadEntryBody::clean($comments)) {
                // Send out alerts if comments are included
                $this->logNote(__('Status Changed'), $comments, $thisstaff, true);
            }
        }

        // Log events via callback
        if ($ecb)
            $ecb($this);
        elseif ($hadStatus)
            // Don't log the initial status change
            $this->logEvent('equipment_edition', array('status' => $status->getId()));

        return true;
    }

    function setState($state, $alerts=false) {
        switch (strtolower($state)) {
        case 'new':
            return $this->setStatus('new');
        case 'retired':
            return $this->setStatus('retired');
        case 'active':
            return $this->setStatus('active');
        case 'inactive':
            return $this->setStatus('inactive');
        }
        // FIXME: Throw and excception and add test cases
        return false;
    }
    
    function setLastMsgId($msgid) {
        return $this->lastMsgId=$msgid;
    }
    
    function setLastMessage($message) {
        $this->last_message = $message;
        $this->setLastMsgId($message->getId());
    }
   
    //Activity log - saved as internal notes WHEN enabled!!
    function logActivity($title, $note) {
        return $this->logNote($title, $note, 'SYSTEM', false);
    }

    // History log -- used for statistics generation (pretty reports)
    function logEvent($state, $data=null, $user=null, $annul=null) {
        $this->getThread()->getEvents()->log($this, $state, $data, $user, $annul);
    }
    
    //Insert Internal Notes
    function logNote($title, $note, $poster='SYSTEM', $alert=true) {
        // Unless specified otherwise, assume HTML
        if ($note && is_string($note))
            $note = new HtmlThreadEntryBody($note);

        $errors = array();
        return $this->postNote(
            array(
                'title' => $title,
                'note' => $note,
            ),
            $errors,
            $poster,
            $alert
        );
    }
    
    function postNote($vars, &$errors, $poster=false, $alert=true) {
        global $cfg, $thisstaff;

        //Who is posting the note - staff or system?
        if ($vars['staffId'] && !$poster)
            $poster = Staff::lookup($vars['staffId']);

        $vars['staffId'] = $vars['staffId'] ?: 0;
        if ($poster && is_object($poster)) {
            $vars['staffId'] = $poster->getId();
            $vars['poster'] = $poster->getName();
        }
        elseif ($poster) { //string
            $vars['poster'] = $poster;
        }
        elseif (!isset($vars['poster'])) {
            $vars['poster'] = 'SYSTEM';
        }
        if (!$vars['ip_address'] && $_SERVER['REMOTE_ADDR'])
            $vars['ip_address'] = $_SERVER['REMOTE_ADDR'];

        if (!($note=$this->getThread()->addNote($vars, $errors)))
            return null;

        if ($vars['note_status_id']
            && ($status=TicketStatus::lookup($vars['note_status_id']))
        ) {
            $this->setStatus($status);
        }

        return $note;
    }
    
    // Print equipment... export the equipment thread as PDF.
    function pdfExport($psize='Letter', $notes=false) {
        global $thisstaff;

        require_once(INCLUDE_DIR.'class.pdf.php');
        if (!is_string($psize)) {
            if ($_SESSION['PAPER_SIZE'])
                $psize = $_SESSION['PAPER_SIZE'];
            elseif (!$thisstaff || !($psize = $thisstaff->getDefaultPaperSize()))
                $psize = 'Letter';
        }

        $pdf = new Equipment2PDF($this, $psize, $notes);
        $name = 'Equipment-'.$this->getId().'.pdf';
        Http::download($name, 'application/pdf', $pdf->Output($name, 'S'));
        //Remember what the user selected - for autoselect on the next print.
        $_SESSION['PAPER_SIZE'] = $psize;
        exit;
    }

    function delete($comments='') {
        global $ost, $thisstaff;

        //delete just orphaned ticket thread & associated attachments.
        // Fetch thread prior to removing ticket entry
        $t = $this->getThread();

        if (!parent::delete())
            return false;

        $t->delete();
        
        // Drop dynamic data
        foreach ($this->getDynamicData() as $entry) {
            $entry->delete();
        }

        // Log delete
        $log = sprintf(__('Equipment #%1$s deleted by %2$s'),
            $this->getName(),
            $thisstaff ? $thisstaff->getName() : __('SYSTEM')
        );
        if ($comments)
            $log .= sprintf('<hr>%s', $comments);

        $ost->logDebug(
            sprintf( __('Equipment #%s deleted'), $this->getName()),
            $log
        );
        return true;
    }

    function save($refetch=false) {
        if ($this->dirty) {
            $this->updated = SqlFunction::NOW();
        }
        return parent::save($this->dirty || $refetch);
    }

    function update($forms, $vars, &$errors) {
        global $cfg, $thisstaff;
        
        if (!$forms || !$this->checkStaffPerm($thisstaff, Equipment::PERM_EDIT))
            return false;
        
        foreach ($forms as $form) {
            $form->setSource($vars);
            if (!$form->isValid(function($f) {
                return $f->isVisibleToStaff() && $f->isEditableToStaff();
            }, array('mode'=>'edit'))) {
                $errors = array_merge($errors, $form->errors());
            }
        }

        if ($errors)
            return false;
        
        // Update dynamic meta-data
        $changes = array();
        foreach ($forms as $f) {
            $changes += $f->getChanges();
            // Miramos los cambios por si se modifica el nombre o el flag reservable
            foreach ($changes as $fieldId => $values) {
                // Si se modifica lo actualizamos en el objeto equipment
                if (($field = DynamicFormField::lookup($fieldId))) {
                    if ($field->get('name') == 'name') {
                        $this->name = $values[1]; // El valor nuevo
                    }
                    if ($field->get('name') == 'bookable') {
                        $this->bookable = $values[1]=="No"?0:1; // El valor nuevo
                    }
                }
            }
            $f->save();
        }
        
        if ($vars['note']) {
            $_errors = array();
            $this->postNote(array(
                        'note' => $vars['note'],
                        'title' => _S('Equipment Updated'),
                        ),
                    $_errors,
                    $thisstaff);
        }

        if ($changes)
            $this->logEvent('edited', array('fields' => $changes));

        Signal::send('model.updated', $this);
        return $this->save();
    }
    
    // Threadable interface
    function postThreadEntry($type, $vars, $options=array()) {
        $errors = array();
        switch ($type) {
        case 'M':
            return $this->postMessage($vars, $vars['origin']);
        case 'N':
            return $this->postNote($vars, $errors);
        case 'R':
            return $this->postReply($vars, $errors);
        }
    }
    
    // Insert message from client
    function postMessage($vars, $origin='', $alerts=true) {
        global $cfg;

        if ($origin)
            $vars['origin'] = $origin;
        if (isset($vars['ip']))
            $vars['ip_address'] = $vars['ip'];
        elseif (!$vars['ip_address'] && $_SERVER['REMOTE_ADDR'])
            $vars['ip_address'] = $_SERVER['REMOTE_ADDR'];

        $errors = array();
        if (!($message = $this->getThread()->addMessage($vars, $errors)))
            return null;

        //$this->setLastMessage($message);

        return $message;
    }

   /*============== Static functions. Use Ticket::function(params); =============nolint*/
    
    /* Quick equipment stats */
    function getStaffStats($staff) {
        global $cfg;

        /* Unknown or invalid staff */
        if(!$staff || (!is_object($staff) && !($staff=Staff::lookup($staff))) || !$staff->isStaff())
            return null;
        
        // -- Routed to a department of mine
        if ($depts = $staff->getDepts())
            $visibility = Q::any(new Q(array('dept_id__in' => $depts)));

        $blocks = Equipment::objects()
            ->filter(Q::any($visibility))
            //->filter(array('status__state' => 'new'))
            ->aggregate(array('count' => SqlAggregate::COUNT('id')))
            ->values('status__state');

        $stats = array();
        $id = $staff->getId();
        foreach ($blocks as $S) {
            switch ($S['status__state']) {
                case 'new':
                    $stats['new'] += $S['count'];
                    break;
                case 'active':
                    $stats['available'] += $S['count'];
                    break;
                case 'inactive':
                    $stats['inactive'] += $S['count'];
                    break;
                case 'retired':
                    $stats['retired'] += $S['count'];
                    break;
            }
        }
        return $stats;
    }

    /*
     * The mother of all functions...You break it you fix it!
     *
     *  $autorespond and $alertstaff overrides config settings...
     */
    static function create($vars, &$errors, $autorespond=true,
            $alertstaff=true) {
        global $ost, $cfg, $thisclient, $thisstaff;
        
        // Don't enforce form validation for email
        $field_filter = function($type) {
            return function($f) {
                return true;
            };
        };

        Signal::send('equipment.create.before', null, $vars);

        // Create and verify the dynamic form entry for the new ticket
        $form = EquipmentForm::getNewInstance();
        $form->setSource($vars);
        
        $fields=array();
        $fields['dept_id'] = array('type'=>'int', 'required'=>0, 'error'=>__('Department selection is required'));

        if(!Validator::process($fields, $vars, $errors) && !$errors['err'])
            $errors['err'] = sprintf('%s — %s',
                __('Missing or invalid data'),
                __('Correct any errors below and try again'));

        if (!$form->isValid($field_filter('equipment'))) {
            $errors += $form->errors();
        }
        
        // Any errors above are fatal.
        if ($errors) {
            return 0;
        }

        Signal::send('equipment.create.validated', null, $vars);

        // OK...just do it.
        $statusId = $vars['statusId'];

        // Last minute checks
        $statusId = $statusId ?: 1; // Valor por defecto
        
        // Metemos los campos no visibles
        $campos = array(
            'created' => SqlFunction::NOW(),
            'updated' => SqlFunction::NOW());
        
        // Y ahora los visibles                                 // TODO: Eliminar
        foreach ($form->getFields() as $field) {
            $nombre = $field->getSelectName();
            switch ($nombre) {
                case "name":
                case "bookable":
                        $campos[$nombre] = $field->getClean();
                    break;
                case "dept_id":
                    $campos[$nombre] = $field->getClean()->getId();
                    break;
            }
        }
        
        //We are ready son...hold on to the rails.
        $equipment = new static($campos);

        if (!$equipment->save()) {
            return null;
        }
        
        if ($vars['default_formdata']['bookable'])
            $equipment->bookable = $vars['default_formdata']['bookable'];
        
        if (!($thread = EquipmentThread::create($equipment->getId()))) {
            return null;
        }

        /* -------------------- POST CREATE ------------------------ */

        $form->setEquipmentId($equipment->getId());
        $form->save();

        $equipment->loadDynamicData(true);

        // Start tracking ticket lifecycle events (created should come first!)
        $equipment->logEvent('created', null, $thisstaff);

        //post the message.
        //$vars['message'] = "Nuevo equipamiento añadido al sistema";
        //$vars['staff_id'] = $thisstaff->getId();
        //$message = $equipment->postMessage($vars , $origin, false);

        // If a message was posted, flag it as the orignal message. This
        // needs to be done on new ticket, so as to otherwise separate the
        // concept from the first message entry in a thread.
        if ($message instanceof ThreadEntry) {
            $message->setFlag(ThreadEntry::FLAG_ORIGINAL_MESSAGE);
            $message->save();
        }
        
        // Set status
        $status = EquipmentStatus::lookup($statusId);
        if (!$status || !$equipment->setStatus($status, false, $errors,
                    !strcasecmp($origin, 'staff'))) {
            $equipment->setStatusId($statusId);
        }
        
        /***** See if we need to send some alerts ****/
        //$equipment->onNewEquipment($message, $autorespond, $alertstaff);
        
        // Fire post-create signal (for extra email sending, searching)
        Signal::send('equipment.created', $equipment);

        return $equipment;
    }

    /* routine used by staff to open a new ticket */
    static function open($vars, &$errors) {
        global $thisstaff, $cfg;

        if (!$thisstaff)
            return false;

        $create_vars = $vars;
        $tform = EquipmentForm::objects()->one()->getForm($create_vars);

        if (!($equipment=self::create($create_vars, $errors, false)))
            return false;

        return $equipment;
    }

    static function agentActions($agent, $options=array()) {
        if (!$agent)
            return;

        require STAFFINC_DIR.'templates/equipment-actions.tmpl.php';
    }
}

class EquipmentForm extends DynamicForm {
    static $instance;
    static $defaultForm;
    static $internalForm;

    static $forms;
    
    static $cdata = array(
            'table' => EQUIPMENT_CDATA_TABLE,
            'object_id' => 'equipment_id',
            'object_type' => ObjectModel::OBJECT_TYPE_EQUIPMENT,
        );

    static function objects() {
        $os = parent::objects();
        return $os->filter(array('type'=>ObjectModel::OBJECT_TYPE_EQUIPMENT));
    }

    static function getDefaultForm() {
        if (!isset(static::$defaultForm)) {
            if (($o = static::objects()) && $o[0])
                static::$defaultForm = $o[0];
        }

        return static::$defaultForm;
    }

    static function getInstance($object_id=0, $new=false) {
        if ($new || !isset(static::$instance))
            static::$instance = static::getDefaultForm()->instanciate();

        static::$instance->object_type = ObjectModel::OBJECT_TYPE_EQUIPMENT;

        if ($object_id)
            static::$instance->object_id = $object_id;

        return static::$instance;
    }
    
    static function getNewInstance() {
        $o = static::objects()->one();
        static::$instance = $o->instanciate();
        return static::$instance;
    }

    static function getInternalForm($source=null, $options=array()) {
        if (!isset(static::$internalForm))
            static::$internalForm = new EquipmentInternalForm($source, $options);

        return static::$internalForm;
    }
}

class EquipmentInternalForm
extends AbstractForm {
    static $layout = 'GridFormLayout';

    function buildFields() {

        $fields = array(
                'dept_id' => new DepartmentField(array(
                    'id'=>1,
                    'label' => __('Department'),
                    'required' => true,
                    'layout' => new GridFluidCell(6),
                    ))
            );

        $mode = @$this->options['mode'];
        if ($mode && $mode == 'edit') {
            unset($fields['dept_id']);
        }

        return $fields;
    }
}

/*
 *  Generic user list.
 */
class EquipmentList extends ListObject implements TemplateVariable {

    function __toString() {
        return $this->getNames();
    }

    function getNames() {
        $list = array();
        foreach($this->storage as $item) {
            if (is_object($item))
                $list [] = $item->getName();
        }
        return $list ? implode(', ', $list) : '';
    }

    function getFull() {
        $list = array();
        foreach($this->storage as $item) {
            if (is_object($item))
                $list[] = sprintf("%s <%s>", $item->getName(), '<posibilidad de añadir otro dato>');
        }

        return $list ? implode(', ', $list) : '';
    }

    static function getVarScope() {
        return array(
            'names' => __('List of names'),
            'full' => __('List of names and email addresses'),
        );
    }
}

?>
