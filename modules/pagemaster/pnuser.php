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

Loader::includeOnce('modules/pagemaster/common.php');

/**
 * List of publications
 *
 * @param $args['tid']
 * @author kundi
 */
function pagemaster_user_main($args)
{
    $dom = ZLanguage::getModuleDomain('pagemaster');

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
        return LogUtil::registerError(__f('Error! Missing argument [%s].', 'tid', $dom));
    }

    $pubtype = PMgetPubType($tid);
    if (empty($pubtype)) {
        return LogUtil::registerError(__('Error! No such publication type found.', $dom));
    }

    if (empty($template)) {
        if (!empty($pubtype['filename'])) {
            // template comes from pubtype
            $sec_template = $pubtype['filename'];
            $template     = 'output/publist_'.$pubtype['filename'].'.htm';
        } else {
            // do not check permission for dynamic template
            $sec_template = '';
            // standart template
            $template     = 'pagemaster_generic_publist.htm';
        }
    } else {
        // template comes from parameter
        $sec_template = $template;
        $template     = 'output/publist_'.$template.'.htm';
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
        $cacheid  = 'publist'.$tid
                   .'|'.(!empty($filter) ? $filter : 'nofilter')
                   .'|'.(!empty($orderby) ? $orderby : 'noorderby')
                   .'|'.(!empty($startnum) ? $startnum : 'nostartnum');
    } else {
        $cachetid = false;
        $cacheid  = null;
    }

    // buils the output
    $render = pnRender::getInstance('pagemaster', $cachetid, $cacheid, true);

    if ($cachetid) {
        $render->cache_lifetime = $cachelifetime;
        if ($render->is_cached($template, $cacheid)) {
            return $render->fetch($template, $cacheid);
        }
    }

    if (isset($args['itemsperpage'])) {
        $itemsperpage = (int)$args['itemsperpage'];
    } elseif (FormUtil::getPassedValue('itemsperpage') != null) {
        $itemsperpage = (int)FormUtil::getPassedValue('itemsperpage');
    } else {
        $itemsperpage = ((int)$pubtype['itemsperpage'] > 0 ? (int)$pubtype['itemsperpage'] : -1 );
    }

    $countmode = ($itemsperpage != 0) ? 'both' : 'no';

    $orderby   = PMcreateOrderBy($orderby);

    $pubfields = PMgetPubFields($tid, 'pm_lineno');
    if (empty($pubfields)) {
        LogUtil::registerError(__('Error! No publication fields found.', $dom));
    }

    // Uses the API to get the list of publications
    $result = pnModAPIFunc('pagemaster', 'user', 'pubList',
                           array('tid'                => $tid,
                                 'pubfields'          => $pubfields,
                                 'pubtype'            => $pubtype,
                                 'countmode'          => $countmode,
                                 'startnum'           => $startnum,
                                 'filter'             => $filter,
                                 'orderby'            => $orderby,
                                 'itemsperpage'       => $itemsperpage,
                                 'checkPerm'          => false, // already checked
                                 'handlePluginFields' => $handlePluginFields,
                                 'getApprovalState'   => $getApprovalState));

    // Assign the data to the output
    $render->assign('tid',     $tid);
    $render->assign('pubtype', $pubtype);
    $render->assign('publist', $result['publist']);
    $render->assign('core_titlefield', PMgetTitleField($pubfields));

    // Assign the pager values if needed
    if ($itemsperpage != 0) {
        $render->assign('pager', array('numitems'     => $result['pubcount'],
                                       'itemsperpage' => $itemsperpage));
    }

    // Check if template is available
    if ($template != 'pagemaster_generic_publist.htm' && !$render->template_exists($template)) {
        // FIXME modvar for alerts
        //LogUtil::registerStatus(__f('Template [%s] not found', $template, $dom));
        $template = 'pagemaster_generic_publist.htm';
    }

    if ($rss) {
        echo $render->display($template, $cacheid);
        pnShutDown();
    }

    return $render->fetch($template, $cacheid);
}

/**
 * View a publication
 * @author kundi
 *
 * @param $args['tid']
 * @param $args['pid']
 * @param $args['id'] (optional)
 * @param $args['template'] (optional)
 * @return publication view output
 */
function pagemaster_user_viewpub($args)
{
    $dom = ZLanguage::getModuleDomain('pagemaster');

    // get the input parameters
    $tid      = isset($args['tid']) ? $args['tid'] : FormUtil::getPassedValue('tid');
    $pid      = isset($args['pid']) ? $args['pid'] : FormUtil::getPassedValue('pid');
    $id       = isset($args['id']) ? $args['id'] : FormUtil::getPassedValue('id');
    $template = isset($args['template']) ? $args['template'] : FormUtil::getPassedValue('template');
    $cachelt  = isset($args['cachelifetime']) ? $args['cachelifetime'] : FormUtil::getPassedValue('cachelifetime');

    // essential validation
    if (empty($tid) || !is_numeric($tid)) {
        return LogUtil::registerError(__f('Error! Missing argument [%s].', 'tid', $dom));
    }
    if ((empty($pid) || !is_numeric($pid)) && (empty($id) || !is_numeric($id))) {
        return LogUtil::registerError(__f('Error! Missing argument [%s].', 'id | pid', $dom));
    }

    $pubtype = PMgetPubType($tid);
    if (empty($pubtype)) {
        return LogUtil::registerError(__f('Error! No such publication type found.', $dom));
    }

    // get the pid if it was not passed
    if (empty($pid)) {
        $pid = pnModAPIFunc('pagemaster', 'user', 'getPid',
                            array('tid' => $tid,
                                  'id'  => $id));
    }

    // determine the template to use
    if (empty($template)) {
        if (!empty($pubtype['filename'])) {
            // template for the security check
            $sec_template = $pubtype['filename'];
            // template comes from pubtype
            $template     = 'output/viewpub_'.$pubtype['filename'].'.htm';
        } else {
            // do not check permission for dynamic template
            $sec_template = '';
            // standart template
            $template     = 'var:viewpub_template_code';
        }
    } else {
        // template for the security check
        $sec_template = $template;
        // template comes from parameter
        $template     = 'output/viewpub_'.$template.'.htm';
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
    	// second clause allow developer to add an edit button on the "viewpub" template
        $cachetid = true;
        $cacheid = 'viewpub'.$tid.'|'.$pid;
    } else {
        $cachetid = false;
        $cacheid  = null;
    }

    // build the output
    $render = pnRender::getInstance('pagemaster', $cachetid, $cacheid, true);

    if ($cachetid) {
        $render->cache_lifetime = $cachelt;
        if ($render->is_cached($template, $cacheid)) {
            return $render->fetch($template, $cacheid);
        }
    }

    // not cached or cache disabled, then get the Pub from the DB
    $pubfields = PMgetPubFields($tid);
    if (empty($pubfields)) {
        LogUtil::registerError(__('Error! No publication fields found.', $dom));
    }

    $pubdata = pnModAPIFunc('pagemaster', 'user', 'getPub',
                            array('tid'                => $tid,
                                  'id'                 => $id,
                                  'pid'                => $pid,
                                  'pubtype'            => $pubtype,
                                  'pubfields'          => $pubfields,
                                  'checkPerm'          => false, //check later, together with template
                                  'getApprovalState'   => true,
                                  'handlePluginFields' => true));

    if (!$pubdata) {
        return LogUtil::registerError(__('No such publication found.', $dom));
    }

    $core_title            = PMgetTitleField($pubfields);
    $pubtype['titlefield'] = $core_title;

    // assign each field of the pubdata to the output
    $render->assign($pubdata);

    // process the output
    $render->assign('pubtype',            $pubtype);
    $render->assign('core_tid',           $tid);
    $render->assign('core_approvalstate', $pubdata['__WORKFLOW__']['state']);
    $render->assign('core_titlefield',    $core_title);
    $render->assign('core_title',         $pubdata[$core_title]);
    $render->assign('core_uniqueid',      $tid.'-'.$pubdata['core_pid']);
    $render->assign('core_creator',       ($pubdata['core_author'] == pnUserGetVar('uid')) ? true : false);

    // Check if template is available
    if ($template != 'var:viewpub_template_code' && !$render->template_exists($template)) {
        //LogUtil::registerStatus(__f('Template [%s] not found', $template, $dom));
        $template = 'var:viewpub_template_code';
    }

    if ($template == 'var:viewpub_template_code') {
        $render->compile_check = true;
        $render->assign('viewpub_template_code', PMgen_viewpub_tplcode($tid, $pubdata, $pubtype, $pubfields));
    }

    return $render->fetch($template, $cacheid);
}

/**
 * Edit/Create a publication
 *
 * @param $args['tid']
 * @param $args['id']
 * @author kundi
 */
function pagemaster_user_pubedit()
{
    $dom = ZLanguage::getModuleDomain('pagemaster');

    // get the input parameters
    $tid = FormUtil::getPassedValue('tid');
    $id  = FormUtil::getPassedValue('id');
    $pid = FormUtil::getPassedValue('pid');

    // essential validation
    if (empty($tid) || !is_numeric($tid)) {
        return LogUtil::registerError(__f('Error! Missing argument [%s].', 'tid', $dom));
    }

    $pubtype = PMgetPubType($tid);
    if (empty($pubtype)) {
        return LogUtil::registerError(__('Error! No such publication type found.', $dom));
    }

    $pubfields = PMgetPubFields($tid, 'pm_lineno');
    if (empty($pubfields)) {
        LogUtil::registerError(__('Error! No publication fields found.', $dom));
    }

    // no security check needed - the security check will be done by the handler class.
    // see the init-part of the handler class for details.
    Loader::LoadClass('pagemaster_user_editpub', 'modules/pagemaster/classes/FormHandlers');
    $formHandler = new pagemaster_user_editpub();

    if (empty($id) && !empty($pid)) {
        $id = pnModAPIFunc('pagemaster', 'user', 'getId',
                           array('tid' => $tid,
                                 'pid' => $pid));
        if (empty($id)) {
            return LogUtil::registerError(__('Error! No such publication found.', $dom));
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

    // get actual state for selecting pnForm Template
    $stepname = 'initial';

    if (!empty($id)) {
        $obj = array('id' => $id);
        PmWorkflowUtil::getWorkflowForObject($obj, $formHandler->tablename, 'id', 'pagemaster');
        $stepname = $obj['__WORKFLOW__']['state'];
    }

    // create the output object
    $render = FormUtil::newpnForm('pagemaster');

    $render->assign('pubtype', $pubtype);

    // resolve the template to use
    $alert = SecurityUtil::checkPermission('pagemaster::', '::', ACCESS_ADMIN) && pnModGetVar('pagemaster', 'display_alerts', false);

    // individual step
    $template_step = 'input/pubedit_'.$pubtype['formname'].'_'.$stepname.'.htm';

    if (!empty($stepname) && $render->template_exists($template_step)) {
        return $render->pnFormExecute($template_step, $formHandler);
    } elseif ($alert) {
        // FIXME configvar for alerts
        //LogUtil::registerError(__f('Notice: Template [%s] not found.', $template_step, $dom));
    }

    // generic edit
    $template_all = 'input/pubedit_'.$pubtype['formname'].'_all.htm';

    if ($render->template_exists($template_all)) {
        return $render->pnFormExecute($template_all, $formHandler);
    } elseif ($alert) {
        //LogUtil::registerError(__f('Notice: Template [%s] not found.', $template_all, $dom));
    }

    $hookAction = empty($id) ? 'new' : 'modify';

    // autogenerated edit template
    $render->force_compile = true;
    $render->assign('editpub_template_code', PMgen_editpub_tplcode($tid, $pubfields, $pubtype, $hookAction));

    return $render->pnFormExecute('var:editpub_template_code', $formHandler);
}

/**
 * Executes a Workflow command over a direct URL Request
 *
 * @param $args['tid']
 * @param $args['id']
 * @param $args['goto'] redirect to after execution
 * @param $args['schema'] optional workflow shema
 * @param $args['commandName'] commandName
 * @author kundi
 */
function pagemaster_user_executecommand()
{
    $dom = ZLanguage::getModuleDomain('pagemaster');

    // get the input parameters
    $tid         = FormUtil::getPassedValue('tid');
    $id          = FormUtil::getPassedValue('id');
    $commandName = FormUtil::getPassedValue('commandName');
    $schema      = FormUtil::getPassedValue('schema');
    $goto        = FormUtil::getPassedValue('goto');

    // essential validation
    if (empty($tid) || !is_numeric($tid)) {
        return LogUtil::registerError(__f('Error! Missing argument [%s].', 'tid', $dom));
    }

    if (!isset($id) || empty($id) || !is_numeric($id)) {
        return LogUtil::registerError(__f('Error! Missing argument [%s].', 'id', $dom));
    }

    if (empty($commandName)) {
        return LogUtil::registerError(__f('Error! Missing argument [%s].', 'commandName', $dom));
    }

    if (empty($schema)) {
        $pubtype = PMgetPubType($tid);
        $schema  = str_replace('.xml', '', $pubtype['workflow']);
    }

    $tablename = 'pagemaster_pubdata'.$tid;

    $pub = DBUtil::selectObjectByID($tablename, $id, 'id');
    if (!$pub) {
        return LogUtil::registerError(__('Error! No such publication found.', $dom));
    }

    PmWorkflowUtil::executeAction($schema, $pub, $commandName, $tablename, 'pagemaster');

    if (!empty($goto)) {
        switch ($goto)
        {
            case 'edit':
                return pnRedirect(pnModURL('pagemaster', 'user', 'pubedit',
                                           array('tid' => $tid,
                                                 'id'  => $pub['id'])));
            case 'stepmode':
                return pnRedirect(pnModURL('pagemaster', 'user', 'pubedit',
                                           array('tid'  => $tid,
                                                 'id'   => $pub['id'],
                                                 'goto' => 'stepmode')));
            default:
                return pnRedirect($goto);
        }
    }

    return pnRedirect(pnModURL('pagemaster', 'user', 'viewpub',
                               array('tid' => $tid,
                                     'id'  => $pub['id'])));
}
