<?php
/**
 * PageMaster
 *
 * @copyright   (c) PageMaster Team
 * @link        http://code.zikula.org/pagemaster/
 * @license     GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package     Zikula_3rdParty_Modules
 * @subpackage  pagemaster
 */

/**
 * User Controller.
 */
class PageMaster_Controller_User extends Zikula_Controller
{
    /**
     * Main user function.
     */
    public function main($args)
    {
        return $this->view($args);
    }

    /**
     * Publications list.
     */
    public function view($args)
    {
        // get the input parameters
        $tid                = isset($args['tid']) ? $args['tid'] : FormUtil::getPassedValue('tid');
        $startnum           = isset($args['startnum']) ? $args['startnum'] : FormUtil::getPassedValue('startnum');
        $filter             = isset($args['filter']) ? $args['filter'] : FormUtil::getPassedValue('filter');
        $orderby            = isset($args['orderby']) ? $args['orderby'] : FormUtil::getPassedValue('orderby');
        $template           = isset($args['template']) ? $args['template'] : FormUtil::getPassedValue('template');
        $getApprovalState   = isset($args['getApprovalState']) ? (bool)$args['getApprovalState'] : FormUtil::getPassedValue('getApprovalState', false);
        $handlePluginFields = isset($args['handlePluginFields']) ? (bool)$args['handlePluginFields'] : FormUtil::getPassedValue('handlePluginFields', true);
        $rss                = isset($args['rss']) ? (bool)$args['rss'] : (bool)FormUtil::getPassedValue('rss');
        $cachelifetime      = isset($args['cachelifetime']) ? $args['cachelifetime'] : FormUtil::getPassedValue('cachelifetime');

        // essential validation
        if (empty($tid) || !is_numeric($tid)) {
            return LogUtil::registerError($this->__f('Error! Missing argument [%s].', 'tid'));
        }

        $pubtype = PageMaster_Util::getPubType($tid);
        if (empty($pubtype)) {
            return LogUtil::registerError($this->__f('Error! No such publication type [%s] found.', $tid));
        }

        if (empty($template)) {
            // template comes from pubtype
            $sec_template = $pubtype['outputset'];
            $template     = $pubtype['outputset'].'/list.tpl';
        } else {
            // template comes from parameter
            $template     = DataUtil::formatForOS($template);
            $sec_template = $pubtype['outputset']."_{$template}";
            $template     = $pubtype['outputset']."/list_{$template}.tpl";
        }

        // security check as early as possible
        if (!SecurityUtil::checkPermission('pagemaster:list:', "$tid::$sec_template", ACCESS_READ)) {
            return LogUtil::registerPermissionError();
        }

        // check if this view is cached
        if (empty($cachelifetime)) {
            $cachelifetime = $pubtype['cachelifetime'];
        }

        if (!empty($cachelifetime)) {
            $cachetid = true;
            $cacheid  = 'view'.$tid
                        .'|'.(!empty($filter) ? $filter : 'nofilter')
                        .'|'.(!empty($orderby) ? $orderby : 'noorderby')
                        .'|'.(!empty($startnum) ? $startnum : 'nostartnum');
        } else {
            $cachetid = false;
            $cacheid  = null;
        }

        // buils the output
        $this->view->setCache_Id($cacheid)
                   ->setCaching($cachetid)
                   ->add_core_data();

        if ($cachetid) {
            $this->view->setCache_lifetime($cachelifetime);
            if ($this->view->is_cached($template, $cacheid)) {
                return $this->view->fetch($template, $cacheid);
            }
        }

        $returnurl = System::getCurrentUrl();

        if (isset($args['itemsperpage'])) {
            $itemsperpage = (int)$args['itemsperpage'];
        } elseif (FormUtil::getPassedValue('itemsperpage') != null) {
            $itemsperpage = (int)FormUtil::getPassedValue('itemsperpage');
        } else {
            $itemsperpage = ((int)$pubtype['itemsperpage'] > 0 ? (int)$pubtype['itemsperpage'] : -1 );
        }

        $orderby = PageMaster_Util::createOrderBy($orderby);

        $pubfields = PageMaster_Util::getPubFields($tid, 'lineno');
        if (empty($pubfields)) {
            LogUtil::registerError($this->__('Error! No publication fields found.'));
        }

        $pubtype->mapValue('titlefield', PageMaster_Util::findTitleField($pubfields));

        // Uses the API to get the list of publications
        $result = ModUtil::apiFunc('PageMaster', 'user', 'getall',
                                   array('tid'                => $tid,
                                         'startnum'           => $startnum,
                                         'itemsperpage'       => $itemsperpage,
                                         'countmode'          => ($itemsperpage != 0) ? 'both' : 'no',
                                         'orderby'            => $orderby,
                                         'filter'             => $filter,
                                         'checkPerm'          => false, // already checked
                                         'handlePluginFields' => $handlePluginFields,
                                         'getApprovalState'   => $getApprovalState));

        // Assign the data to the output
        $this->view->assign('pubtype',   $pubtype)
                   ->assign('publist',   $result['publist'])
                   ->assign('returnurl', $returnurl);

        // Assign the pager values if needed
        if ($itemsperpage != 0) {
            $this->view->assign('pager', array('numitems'     => $result['pubcount'],
                                               'itemsperpage' => $itemsperpage));
        }

        // Check if template is available
        if (!$this->view->template_exists($template)) {
            $alert = SecurityUtil::checkPermission('pagemaster::', '::', ACCESS_ADMIN) && ModUtil::getVar('PageMaster', 'devmode', false);
            if ($alert) {
                LogUtil::registerStatus($this->__f('Notice: Template [%s] not found.', $template));
            }
            // return an error/void if a block template does not exists
            if (strpos($sec_template, $pubtype['outputset'].'_list_block_') === 0) {
                return $alert ? LogUtil::registerError($this->__f('Notice: Template [%s] not found.', $template)) : '';
            }
            $template = 'pagemaster_generic_list.tpl';
        }

        if ($rss) {
            echo $this->view->display($template, $cacheid);
            System::shutdown();
        }

        return $this->view->fetch($template, $cacheid);
    }

    /**
     * Display a publication.
     *
     * @param $args['tid']
     * @param $args['pid']
     * @param $args['id'] (optional)
     * @param $args['template'] (optional)
     *
     * @return Publication output.
     */
    public function display($args)
    {
        // get the input parameters
        $tid      = isset($args['tid']) ? $args['tid'] : FormUtil::getPassedValue('tid');
        $pid      = isset($args['pid']) ? $args['pid'] : FormUtil::getPassedValue('pid');
        $id       = isset($args['id']) ? $args['id'] : FormUtil::getPassedValue('id');
        $template = isset($args['template']) ? $args['template'] : FormUtil::getPassedValue('template');
        $cachelt  = isset($args['cachelifetime']) ? $args['cachelifetime'] : FormUtil::getPassedValue('cachelifetime');

        // essential validation
        if (empty($tid) || !is_numeric($tid)) {
            return LogUtil::registerError($this->__f('Error! Missing argument [%s].', 'tid'));
        }
        if ((empty($pid) || !is_numeric($pid)) && (empty($id) || !is_numeric($id))) {
            return LogUtil::registerError($this->__f('Error! Missing argument [%s].', 'id | pid'));
        }

        $pubtype = PageMaster_Util::getPubType($tid);
        if (empty($pubtype)) {
            return LogUtil::registerError($this->__f('Error! No such publication type [%s] found.', $tid));
        }

        // get the pid if it was not passed
        if (empty($pid)) {
            $pid = ModUtil::apiFunc('PageMaster', 'user', 'getPid',
                                    array('tid' => $tid,
                                          'id'  => $id));
        }

        // determine the template to use
        if (empty($template)) {
            // template for the security check
            $sec_template = $pubtype['outputset'];
            // template comes from pubtype
            $template     = $pubtype['outputset'].'/display.tpl';
        } else {
            // template comes from parameter
            $template     = DataUtil::formatForOS($template);
            $sec_template = $pubtype['outputset']."_{$template}";
            // check for related plain templates
            if (in_array($template, array('pending'))) {
                $simpletemplate = $pubtype['outputset']."/display_{$template}.tpl";
            } else {
                $template = $pubtype['outputset']."/display_{$template}.tpl";
            }
        }

        // security check as early as possible
        if (!SecurityUtil::checkPermission('pagemaster:full:', "$tid:$pid:$sec_template", ACCESS_READ)) {
            return LogUtil::registerPermissionError();
        }

        // check if this view is cached
        if (empty($cachelt)) {
            $cachelt = $pubtype['cachelifetime'];
        }

        if (!empty($cachelt) && !SecurityUtil::checkPermission('pagemaster:input:', "$tid:$pid:", ACCESS_ADMIN)) {
            // second clause allow developer to add an edit button on the "display" template
            $cachetid = true;
            $cacheid = 'display'.$tid.'|'.$pid;
        } else {
            $cachetid = false;
            $cacheid  = null;
        }

        // build the output
        $this->view->setCaching($cachetid)
                   ->setCache_Id($cacheid)
                   ->add_core_data();

        if ($cachetid) {
            $this->view->setCache_lifetime($cachelt);
            if ($this->view->is_cached($template, $cacheid)) {
                return $this->view->fetch($template, $cacheid);
            }
        }

        // fetch plain templates
        if (isset($simpletemplate)) {
            if (!$this->view->template_exists($simpletemplate)) {
                $simpletemplate = "pagemaster_general_{$template}.tpl";
                if (!$this->view->template_exists($simpletemplate)) {
                    $simpletemplate = '';
                }
            }
            if ($simpletemplate != '') {
                $this->view->assign('pubtype', $pubtype);
                return $this->view->fetch($simpletemplate, $cacheid);
            }
        }

        // not cached or cache disabled, then get the Pub from the DB
        $pubfields = PageMaster_Util::getPubFields($tid);
        if (empty($pubfields)) {
            LogUtil::registerError($this->__('Error! No publication fields found.'));
        }

        $pubtype->mapValue('titlefield', PageMaster_Util::findTitleField($pubfields));

        $pubdata = ModUtil::apiFunc('PageMaster', 'user', 'get',
                                    array('tid'                => $tid,
                                          'id'                 => $id,
                                          'pid'                => $pid,
                                          'checkPerm'          => false, //check later, together with template
                                          'getApprovalState'   => true,
                                          'handlePluginFields' => true));
        if (!$pubdata) {
            return LogUtil::registerError($this->__f('No such publication [%s - %s, %s] found.', array($tid, $pid, $id)));
        }

        // publication processing
        PageMaster_Util::pubPostProcess($pubdata);

        // assign each field of the pubdata to the output
        $this->view->assign('pubdata', $pubdata);

        // process the output
        $this->view->assign('pubtype', $pubtype);

        // Check if template is available
        if (!$this->view->template_exists($template)) {
            $alert = SecurityUtil::checkPermission('pagemaster::', '::', ACCESS_ADMIN) && ModUtil::getVar('PageMaster', 'devmode', false);
            if ($alert) {
                LogUtil::registerStatus($this->__f('Notice: Template [%s] not found.', $template));
            }
            // return an error/void if a block template does not exists
            if (strpos($sec_template, $pubtype['outputset'].'_display_block_') === 0) {
                return $alert ? LogUtil::registerError($this->__f('Notice: Template [%s] not found.', $template)) : '';
            }
            $template = 'var:display_template_code';
        }

        if ($template == 'var:display_template_code') {
            $this->view->setCompile_check(true)
                       ->assign('display_template_code', PageMaster_Generator::pubdisplay($tid, $pubdata));
        }

        return $this->view->fetch($template, $cacheid);
    }

    /**
     * Edit/Create a publication.
     *
     * @param $args['tid']
     * @param $args['id']
     */
    public function edit()
    {
        // get the input parameters
        $tid = FormUtil::getPassedValue('tid');
        $id  = FormUtil::getPassedValue('id');
        $pid = FormUtil::getPassedValue('pid');

        // essential validation
        if (empty($tid) || !is_numeric($tid)) {
            return LogUtil::registerError($this->__f('Error! Missing argument [%s].', 'tid'));
        }

        $pubtype = PageMaster_Util::getPubType($tid);
        if (!$pubtype) {
            return LogUtil::registerError($this->__f('Error! No such publication type [%s] found.', $tid));
        }

        $pubfields = PageMaster_Util::getPubFields($tid, 'pm_lineno');
        if (empty($pubfields)) {
            LogUtil::registerError($this->__('Error! No publication fields found.'));
        }

        $pubtype->mapValue('titlefield', PageMaster_Util::findTitleField($pubfields));

        // no security check needed - the security check will be done by the handler class.
        // see the init-part of the handler class for details.
        $formHandler = new PageMaster_Form_Handler_User_Pubedit();

        if (empty($id) && !empty($pid)) {
            $id = ModUtil::apiFunc('PageMaster', 'user', 'getId',
                                   array('tid' => $tid,
                                         'pid' => $pid));
            if (empty($id)) {
                return LogUtil::registerError($this->__f('Error! No such publication [%s - %s] found.', array($tid, $pid)));
            }
        }

        // cast values to ensure the type
        $id  = (int)$id;
        $pid = (int)$pid;

        $formHandler->tid       = $tid;
        $formHandler->id        = $id;
        $formHandler->pubtype   = $pubtype;
        $formHandler->pubfields = $pubfields;
        $formHandler->tablename = 'pagemaster_pubdata'.$tid;

        // get actual state for selecting form Template
        $stepname = 'initial';

        if (!empty($id)) {
            $obj = array('id' => $id);
            Zikula_Workflow_Util::getWorkflowForObject($obj, $formHandler->tablename, 'id', 'PageMaster');
            $stepname = $obj['__WORKFLOW__']['state'];
        }

        // adds the stepname to the pubtype
        $pubtype->mapValue('stepname', $stepname);

        // create the output object
        $render = FormUtil::newForm('PageMaster');

        $render->assign('pubtype', $pubtype)
               ->add_core_data();

        // resolve the template to use
        $alert = SecurityUtil::checkPermission('pagemaster::', '::', ACCESS_ADMIN) && ModUtil::getVar('PageMaster', 'devmode', false);

        // individual step
        $template = $pubtype['inputset']."/form_{$stepname}.tpl";

        if (!empty($stepname) && $render->template_exists($template)) {
            return $render->execute($template, $formHandler);
        } elseif ($alert) {
            LogUtil::registerStatus($this->__f('Notice: Template [%s] not found.', $template));
        }

        // generic edit
        $template = $pubtype['inputset'].'/form_all.tpl';

        if ($render->template_exists($template)) {
            return $render->execute($template, $formHandler);
        } elseif ($alert) {
            LogUtil::registerStatus($this->__f('Notice: Template [%s] not found.', $template));
        }

        // autogenerated edit template
        $render->setForce_compile(true);

        $render->assign('edit_template_code', PageMaster_Generator::pubedit($tid));

        return $render->execute('var:edit_template_code', $formHandler);
    }

    /**
     * Executes a Workflow command over a direct URL Request.
     *
     * @param $args['tid']
     * @param $args['id']
     * @param $args['goto'] redirect to after execution.
     * @param $args['schema'] optional workflow shema.
     * @param $args['commandName'] commandName
     */
    public function executecommand()
    {
        // get the input parameters
        $tid         = FormUtil::getPassedValue('tid');
        $id          = FormUtil::getPassedValue('id');
        $commandName = FormUtil::getPassedValue('commandName');
        $schema      = FormUtil::getPassedValue('schema');
        $goto        = FormUtil::getPassedValue('goto');

        // essential validation
        if (empty($tid) || !is_numeric($tid)) {
            return LogUtil::registerError($this->__f('Error! Missing argument [%s].', 'tid'));
        }

        if (!isset($id) || empty($id) || !is_numeric($id)) {
            return LogUtil::registerError($this->__f('Error! Missing argument [%s].', 'id'));
        }

        if (empty($commandName)) {
            return LogUtil::registerError($this->__f('Error! Missing argument [%s].', 'commandName'));
        }

        if (empty($schema)) {
            $pubtype = PageMaster_Util::getPubType($tid);
            $schema  = str_replace('.xml', '', $pubtype['workflow']);
        }

        $tablename = 'pagemaster_pubdata'.$tid;

        $pub = Doctrine_Core::getTable('PageMaster_Model_Pubdata'.$tid)
               ->find($id)
               ->toArray();

        if (!$pub) {
            return LogUtil::registerError($this->__f('Error! No such publication [%s] found.', $id));
        }

        Zikula_Workflow_Util::executeAction($schema, $pub, $commandName, $tablename, 'PageMaster');

        if (!empty($goto)) {
            switch ($goto)
            {
                case 'edit':
                    return System::redirect(ModUtil::url('PageMaster', 'user', 'edit',
                                            array('tid' => $tid,
                                                  'id'  => $pub['id'])));
                case 'stepmode':
                    return System::redirect(ModUtil::url('PageMaster', 'user', 'edit',
                                            array('tid'  => $tid,
                                                  'id'   => $pub['id'],
                                                  'goto' => 'stepmode')));
                default:
                    return System::redirect($goto);
            }
        }

        return System::redirect(ModUtil::url('PageMaster', 'user', 'display',
                                             array('tid' => $tid,
                                                   'id'  => $pub['id'])));
    }

    /**
     * Javascript hierarchical menu of edit links.
     *
     * @author rgasch
     * @param  $args['tid']
     * @param  $args['pid'] (optional)
     * @param  $args['edit'] (optional)
     * @param  $args['menu'] (optional)
     * @param  $args['orderby'] (optional)
     * @param  $args['returntype'] (optional)
     * @param  $args['source'] (optional)
     *
     * @return Publication menu and/or edit mask.
     */
    public function editlist($args=array())
    {
        $tid        = isset($args['tid']) ? $args['tid'] : FormUtil::getPassedValue('tid');
        $pid        = isset($args['pid']) ? $args['pid'] : FormUtil::getPassedValue('pid');
        $edit       = isset($args['edit']) ? $args['edit'] : FormUtil::getPassedValue('edit', 1);
        $menu       = isset($args['menu']) ? $args['menu'] : FormUtil::getPassedValue('menu', 1);
        $orderby    = isset($args['orderby']) ? $args['orderby'] : FormUtil::getPassedValue('orderby', 'core_title');
        $returntype = isset($args['returntype']) ? $args['returntype'] : FormUtil::getPassedValue('returntype', 'user');
        $source     = isset($args['source']) ? $args['source'] : FormUtil::getPassedValue('source', 'module');

        $pubData = ModUtil::apiFunc('PageMaster', 'user', 'editlist', $args);

        // create the output object
        $this->view->assign('allTypes',   $pubData['allTypes'])
                   ->assign('publist',    $pubData['pubList'])
                   ->assign('tid',        $tid)
                   ->assign('pid',        $pid)
                   ->assign('edit',       $edit)
                   ->assign('menu',       $menu)
                   ->assign('returntype', $returntype)
                   ->assign('source',     $source);

        return $this->view->fetch('pagemaster_user_editlist.tpl');
    }

    /**
     * @see PageMaster_Controller_User::display
     * @deprecated
     */
    public function viewpub($args)
    {
        return $this->display($args);
    }

    /**
     * @see PageMaster_Controller_User::edit
     * @deprecated
     */
    public function pubedit($args)
    {
        return $this->edit($args);
    }

    /**
     * @see PageMaster_Controller_User::editlist
     * @deprecated
     */
    public function pubeditlist($args)
    {
        return $this->editlist($args);
    }
}