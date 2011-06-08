<?php
/**
 * Clip
 *
 * @copyright  (c) Clip Team
 * @link       http://code.zikula.org/clip/
 * @license    GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package    Clip
 * @subpackage Controller
 */

/**
 * Editor Controller.
 */
class Clip_Controller_Editor extends Zikula_AbstractController
{
    /**
     * Unobstrusive workaround to have a "list" method.
     *
     * @param string $func Name of the function invoked.
     * @param mixed  $args Arguments passed to the function.
     *
     * @return mixed Function output.
     */
    public function __call($func, $args)
    {
        switch ($func)
        {
            case 'list':
                return $this->view(isset($args[0])? $args[0]: array());
                break;
        }
    }

    /**
     * Post initialise.
     *
     * @return void
     */
    protected function postInitialize()
    {
        // In this controller we do not want caching.
        $this->view->setCaching(Zikula_View::CACHE_DISABLED);
    }

    /**
     * Accessible grouptypes/pubtypes list screen.
     */
    public function main()
    {
        //// Security
        $this->throwForbiddenUnless(Clip_Access::toClip(ACCESS_EDIT));

        $grouptypes = Clip_Util_Grouptypes::getTree(ACCESS_OVERVIEW, false);

        //// Output
        $this->view->assign('grouptypes', $grouptypes);

        return $this->view->fetch('clip_editor_main.tpl');
    }

    /**
     * Editor list screen.
     */
    public function view($args = array())
    {
        //// Pubtype
        // validate and get the publication type first
        $args['tid'] = isset($args['tid']) ? $args['tid'] : FormUtil::getPassedValue('tid');

        if (!Clip_Util::validateTid($args['tid'])) {
            return LogUtil::registerError($this->__f('Error! Invalid publication type ID passed [%s].', DataUtil::formatForDisplay($args['tid'])));
        }

        $pubtype = Clip_Util::getPubType($args['tid'])->mapTitleField();

        //// Security
        $this->throwForbiddenUnless(Clip_Access::toPubtype($pubtype));

        // define the arguments
        $apiargs = array(
            'tid'           => $args['tid'],
            'filter'        => isset($args['filter']) ? $args['filter'] : (FormUtil::getPassedValue('filter') ? null : 'core_online:eq:1'),
            'orderby'       => isset($args['orderby']) ? $args['orderby'] : FormUtil::getPassedValue('orderby', 'core_pid:desc'),
            'itemsperpage'  => (isset($args['itemsperpage']) && is_numeric($args['itemsperpage']) && $args['itemsperpage'] >= 0) ? (int)$args['itemsperpage'] : abs((int)FormUtil::getPassedValue('itemsperpage', $pubtype['itemsperpage'])),
            'handleplugins' => isset($args['handleplugins']) ? (bool)$args['handleplugins'] : false,
            'loadworkflow'  => isset($args['loadworkflow']) ? (bool)$args['loadworkflow'] : true,
            'checkperm'     => false,
            'countmode'     => 'both'
        );
        $args = array(
            'startnum'      => (isset($args['startnum']) && is_numeric($args['startnum'])) ? (int)$args['startnum'] : (int)FormUtil::getPassedValue('startnum', 0),
            'page'          => (isset($args['page']) && is_numeric($args['page'])) ? (int)$args['page'] : (int)abs(FormUtil::getPassedValue('page', 1))
        );

        // sets the function parameter
        $this->view->assign('func', 'list');

        //// Misc values
        if ($apiargs['itemsperpage'] < 10) {
            $apiargs['itemsperpage'] = $pubtype['itemsperpage'] >= 10 ? $pubtype['itemsperpage'] : 10;
        }

        if ($args['page'] > 1) {
            $apiargs['startnum'] = ($args['page']-1)*$apiargs['itemsperpage']+1;
        }

        Clip_Util::setArgs('adminlist', $args);

        //// Execution
        // fill the conditions of the list to get
        $apiargs['where'] = array();
        if (UserUtil::isLoggedIn() && $pubtype['enableeditown'] == 1) {
            $apiargs['where']['orWhere'] = array('core_author = ?', array(UserUtil::getVar('uid')));
        }
        $apiargs['where'][] = array('(core_language = ? OR core_language = ?)', array(ZLanguage::getLanguageCode(), ''));

        // uses the API to get the list of publications
        $result = ModUtil::apiFunc('Clip', 'user', 'getall', $apiargs);

        //// Output
        // assign the output variables
        $this->view->assign('pubtype',  $pubtype)
                   ->assign('publist',  $result['publist'])
                   ->assign('clipargs', Clip_Util::getArgs());

        // assign the pager values
        $this->view->assign('pager', array('numitems'     => $result['pubcount'],
                                           'itemsperpage' => $apiargs['itemsperpage']));

        // custom pubtype template check
        $customtpl = $pubtype['outputset'].'/editorlist.tpl';

        if ($this->view->template_exists($customtpl)) {
            return $this->view->fetch($customtpl);
        }

        return $this->view->fetch('clip_editor_list.tpl');
    }

    /**
     * History screen.
     */
    public function history($args = array())
    {
        //// Pubtype
        // validate and get the publication type first
        $args['tid'] = isset($args['tid']) ? $args['tid'] : FormUtil::getPassedValue('tid');

        if (!Clip_Util::validateTid($args['tid'])) {
            return LogUtil::registerError($this->__f('Error! Invalid publication type ID passed [%s].', DataUtil::formatForDisplay($args['tid'])));
        }

        $pubtype = Clip_Util::getPubType($args['tid'])->mapTitleField();

        //// Parameters
        // define the arguments
        $args = array(
            'tid' => $args['tid'],
            'pid' => isset($args['pid']) ? (int)$args['pid'] : (int)FormUtil::getPassedValue('pid')
        );

        //// Validation
        // validate the passed publication ID
        if (empty($args['pid']) || !is_numeric($args['pid'])) {
            return LogUtil::registerError($this->__f('Error! Missing argument [%s].', 'pid'));
        }

        //// Security
        // FIXME rework with Clip_Access to check the online/latest revision state access
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Clip:edit:', "{$args['tid']}:{$args['pid']}:", ACCESS_EDIT));

        //// Execution
        // get the collection of pubs
        $publist = Doctrine_Core::getTable('Clip_Model_Pubdata'.$args['tid'])
                       ->selectCollection("core_pid = '{$args['pid']}'", 'core_revision DESC');

        for ($i = 0; $i < count($publist); $i++) {
            $publist[$i]->clipProcess(array('handleplugins' => true, 'loadworkflow' => true));
        }

        //// Output
        $this->view->assign('pubtype', $pubtype)
                   ->assign('publist', $publist);

        return $this->view->fetch('clip_base_history.tpl');
    }
}